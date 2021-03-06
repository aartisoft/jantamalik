<?php

require_once ("db.php");
require_once ("state_consituency.php");

// Create connection
$conn = new mysqli($servername, $user, $password, $database);

//To handle hindi
mysqli_query($conn, 'SET character_set_results=utf8');
mysqli_query($conn, 'SET names=utf8');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// get the list of seat/ constituencies
$time_total_before = microtime(true);
$state = $argv[1];
$constituency = $argv[2];
//echo "\nState: $state, Constituency: $constituency";

$url = "https://api.myneta.info/ver4.3/getDataLS2019BasicDetails.php?message=" . urlencode($constituency) . "&state=" . urlencode($state) . "&apikey=" . $ADR_key;
// save the json
$time_before = microtime(true);
file_put_contents($constituency . ".json", fopen($url, 'r'));
$time_after = microtime(true);

// parse the json for candidate details - filter them into good/bad buckets (arrays)
$file_name = file_get_contents($constituency . ".json");
$json = json_decode($file_name, true);
$total = 0;

// for every seat, check json is not null
if (empty($json) == false) {
    foreach ($json as $candidate) {
        //echo $candidate;
        // check for return 0 - missing state
        if ($candidate[0] === '0') {
            echo $state . ", " . $constituency . ", MISSING STATE \n";
            exit(0);
        }
        // check for return 1 - data coming soon
        if ($candidate[0] === '1') {
            echo $state . ", " . $constituency . ", COMING SOON \n";
            exit(0);
        }
        // check for return 2 - doesn't exist or spelling mistake
        if ($candidate[0] === '2') {
            echo $state . ", " . $constituency . ", SPELLING \n";
            exit(0);
        }

        $i = 0;
        foreach ($candidate as $candidate_data) {
            ++$i;
            if ($i === 1) {
                $name = $candidate_data;
            } else if ($i === 2) {
                $hindi_constituency = $array[6];
            } else if ($i === 3) {
                $party = $candidate_data;
            } else if ($i === 4) {
                $hindi_state = $array[1];
            } else if ($i === 5) {
                $date_of_election = $candidate_data;
            } else if ($i === 6) {
                $sex = $candidate_data;
            } else if ($i === 7) {
                $age = $candidate_data;
            } else if ($i === 8) {
                $serious_ipc_counts = $candidate_data;
            } else if ($i === 9) {
                $cases = $candidate_data;
            } else if ($i === 10) {
                $education = $candidate_data;
            } else if ($i === 11) {
                $total_assets = $candidate_data;
            } else if ($i === 12) {
                $movable_assets = $candidate_data;
            } else if ($i === 13) {
                $immovable_assets = $candidate_data;
            } else if ($i === 14) {
                $liabilities = $candidate_data;
            } else if ($i === 15) {
                $pan_given = $candidate_data;
            } else if ($i === 16) {
                $self_income = $candidate_data;
            } else if ($i === 17) {
                $total_income = $candidate_data;
            } else if ($i === 18) {
                $self_profession = $candidate_data;
            } else if ($i === 19) {
                $position = $candidate_data;
            } else if ($i === 20) {
                $url = $candidate_data;
            }
        }   // json parsing of one array row over
        ++$total;

        $sql = "SELECT * FROM loksabha_2019 WHERE constituency = '$constituency' AND name='$name'";
        $result = mysqli_query($conn, $sql) or die('Error:' . mysqli_error($conn));
        if (mysqli_num_rows($result) > 0) {
            $sql = "UPDATE loksabha_2019 SET party = '$party',  date_of_election = '$date_of_election', sex = '$sex',  age = '$age', "
                    . "serious_ipc_counts = '$serious_ipc_counts',  cases = '$cases', education = '$education',  total_assets = '$total_assets', "
                    . "movable_assets = '$movable_assets',  immovable_assets = '$immovable_assets', liabilities = '$liabilities',  pan_given = '$pan_given',"
                    . "self_income = '$self_income',  total_income = '$total_income', self_profession = '$self_profession',  position = '$position', "
                    . "url = '$url' "
                    . "WHERE state = '$state' AND constituency = '$constituency' AND name='$name'";
            $tmp = mysqli_query($conn, $sql);

            if (!$tmp)
                die('Error:' . $sql . mysqli_error($conn));
        } else {
            $sql = "INSERT INTO loksabha_2019 (name, constituency, party, state, date_of_election, sex, age, serious_ipc_counts,
								cases, education, total_assets, movable_assets, immovable_assets, liabilities, pan_given,
								self_income, total_income, self_profession, position, url)
				VALUES ('$name', '$constituency', '$party', '$state', '$date_of_election', '$sex', '$age', '$serious_ipc_counts',
								'$cases', '$education', '$total_assets', '$movable_assets', '$immovable_assets', '$liabilities', '$pan_given',
								'$self_income', '$total_income', '$self_profession', '$position', '$url')";

            if ($conn->query($sql) === TRUE) {
                //nothing to do
            } else {
                echo "Error: " . $sql . "<br>" . $conn->error;
            }
        }
        //echo "sql: $sql\n";
    }

    //echo $constituency_name_hindi;
    //echo $state_name_hindi;
    //check if case of NEW(insert) or CORRECTION(update)
    echo $state . ", " . $constituency . " Total: $total \n";
} else {
    echo $state . ", " . $constituency . ", FAIL: $json" . "\n";
}

$time_total_after = microtime(true);
//echo "\nTotal " . ($time_total_after - $time_total_before) . " API " . ($time_after - $time_before) . "\n";
//ADR import complete into DB
// Filter Congress and BJP
$filter_illegal_funding = mysqli_query($conn, "UPDATE loksabha_2019 SET bucket = '1' WHERE party='BJP' OR party='INC' ");
if (!$filter_illegal_funding)
    die('Error:' . $filter_illegal_funding . ' -> ' . mysqli_error($conn));

// Filter serious crime charges
$filter_criminals = mysqli_query($conn, "UPDATE loksabha_2019 SET bucket = '2' WHERE bucket='' AND serious_ipc_counts >0 ");
if (!$filter_criminals)
    die('Error:' . $filter_criminals . ' -> ' . mysqli_error($conn));

// Filter candidate_age > 64
$filter_elders = mysqli_query($conn, "UPDATE loksabha_2019 SET bucket = '3' WHERE bucket='' AND age > 64 ");
if (!$filter_elders)
    die('Error:' . $filter_elders . ' -> ' . mysqli_error($conn));

// Filter education < Graduate
$filter_not_graduate = mysqli_query($conn, "UPDATE loksabha_2019 SET bucket = '4' WHERE bucket='' "
        . "AND (education!='Graduate' AND education!='Graduate Professional' AND education!='Post Graduate' AND education!='Doctorate') ");
if (!$filter_not_graduate)
    die('Error:' . $filter_not_graduate . ' -> ' . mysqli_error($conn));
