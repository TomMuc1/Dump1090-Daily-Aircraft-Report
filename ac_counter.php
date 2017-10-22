<?php

#phpinfo();
#var_dump(ini_get_all());
#ini_set('error_reporting', E_ALL);

// set path to aircraft.json file
$user_set_array['url_json'] = 'http://127.0.0.1/dump1090/data/';

// set email and/or logfile and/or database option to true or false
$user_set_array['email'] = false;    $user_set_array['log'] = true;
$user_set_array['database'] = false;

// set path to directory where log files to store to
$user_set_array['log_directory'] = '/home/pi/ac_counter_log';

// set your google email-address daily reports to send to
$user_set_array['email_address'] = 'YOUR_EMAIL@gmail.com';

// set the absolute limit of alert-messages default is 500
$user_set_array['mailer_limit'] = 500;

// set to true for units metric instead nautical
$user_set_array['metric'] = false;

// set parameters for database connection
$user_set_array['db_user'] = 'root';    $user_set_array['db_pass'] = 'YOUR_PASSWORD';
$user_set_array['db_name'] = 'adsb';    $user_set_array['db_host'] = '127.0.0.1';

// set only to true for script function test run -> will 3 times email/log/db after about 1/2/3 minutes
$user_set_array['test_mode'] = true;

// below a sample create statement for database table
// CREATE TABLE daily_report (id INT NOT NULL AUTO_INCREMENT, date VARCHAR(100), transponder VARCHAR(100), messages VARCHAR(100), flight VARCHAR(100), category VARCHAR(100), squawk VARCHAR(100), first_seen VARCHAR(100), first_latitude VARCHAR(100), first_longitude VARCHAR(100), first_altitude VARCHAR(100), last_seen VARCHAR(100), last_latitude VARCHAR(100), last_longitude VARCHAR(100), last_altitude VARCHAR(100), low_dist VARCHAR(100), high_dist VARCHAR(100), low_rssi VARCHAR(100), high_rssi VARCHAR(100), mlat VARCHAR(100), PRIMARY KEY (id))



// function to compute distance between receiver and aircraft
function func_haversine($lat_from, $lon_from, $lat_to, $lon_to, $earth_radius) {
	$delta_lat = deg2rad($lat_to - $lat_from);
	$delta_lon = deg2rad($lon_to - $lon_from);
	$a = sin($delta_lat / 2) * sin($delta_lat / 2) + cos(deg2rad($lat_from)) * cos(deg2rad($lat_to)) * sin($delta_lon / 2) * sin($delta_lon / 2);
	$c = 2 * atan2(sqrt($a), sqrt(1-$a));
	return $earth_radius * $c;
}

$i = 0;
$sent_messages = 0;
$hex_array = array();
$start_time = time();
date_default_timezone_set('UTC');
$seconds_of_day = time() - strtotime('today');
$user_set_array['metric'] ? $earth_radius = 6371 : $earth_radius = 3440;

// fetch receiver.json and read receiver latitude and longitude
$json_receiver_location = json_decode(file_get_contents($user_set_array['url_json'] . 'receiver.json'), true);
isset($json_receiver_location['lat']) ? $rec_lat = $json_receiver_location['lat'] : $rec_lat = 0;
isset($json_receiver_location['lon']) ? $rec_lon = $json_receiver_location['lon'] : $rec_lon = 0;

// set csv-file column header names
$csv_header = '"Transponder"' . "\t" . '"Messages"' . "\t" . '"Flight"' . "\t" . '"Category"' . "\t" . '"Squawk"' . "\t" . '"First Seen"' . "\t" . '"First Latitude"' . "\t" . '"First Longitude"' . "\t" . '"First Altitude"' . "\t" . '"Last Seen"' . "\t" . '"Last Latitude"' . "\t" . '"Last Longitude"' . "\t" . '"Last Altitude"' . "\t" . '"Low Dist"' . "\t" . '"High Dist"' . "\t" . '"Low Rssi"' . "\t" . '"High Rssi"' . "\t" . '"Mlat"' . PHP_EOL . PHP_EOL;

while (true) {

	$db_insert = '';
	$start_loop_microtime = microtime(true);

	// at midnight generate csv-file and submit email and/or write log-file
	if ($seconds_of_day > time() - strtotime('today') || ($user_set_array['test_mode'] && ($i == 60 || $i == 120 || $i == 180))) {
		$csv = '';
		$csv .= $csv_header;
		foreach ($csv_array as $key => $value) {
			$csv .= "\"\t\0" . implode("\"\t\"", str_replace('.', ',', $value)) . "\"" . PHP_EOL;
		}
		if ($user_set_array['email'] == true && $sent_messages < $user_set_array['mailer_limit']) {
			$boundary = str_replace(' ', '.', microtime());
			$header = 'From: ' . $user_set_array['email_address'] . PHP_EOL;
			$header .= 'Reply-To: ' . $user_set_array['email_address'] . PHP_EOL;
			$header .= 'MIME-Version: 1.0' . PHP_EOL;
			$header .= 'Content-Type: multipart/mixed; boundary="' . $boundary . '"' . PHP_EOL . PHP_EOL;
			$body = '--' . $boundary . PHP_EOL;
			$body .= 'Content-type:text/plain; charset=iso-8859-1' . PHP_EOL;
			$body .= 'Content-Transfer-Encoding: 7bit' . PHP_EOL . PHP_EOL;
			$body .= number_format(count($csv_array), 0, ',', '.') . ' Aircrafts @ ' . number_format(array_sum(array_column($csv_array, 'msg')), 0, ',', '.') . ' Messages - Yesterday UTC' . PHP_EOL . PHP_EOL;
			$body .= '--' . $boundary . PHP_EOL;
			$body .= 'Content-Type: application/octet-stream; name="aircrafts.xls"' . PHP_EOL;
			$body .= 'Content-Transfer-Encoding: base64' . PHP_EOL;
			$body .= 'Content-Disposition: attachment; filename="aircrafts.xls"' . PHP_EOL . PHP_EOL;
			$body .= chunk_split(base64_encode($csv)) . PHP_EOL . PHP_EOL;
			$body .= '--' . $boundary . '--';
			mail($user_set_array['email_address'], 'Daily Aircraft Stats', $body, $header);
		}
		if ($user_set_array['log'] == true) {
			$file_to_write = gzencode($csv);
			$file_name_to_write = $user_set_array['log_directory'] . '/' . 'ac_' . date('Y_m_d_i', time() - 86400) . '.xls.zip';
			if (!file_exists($user_set_array['log_directory'])) mkdir($user_set_array['log_directory'], 0755, true);
			file_put_contents($file_name_to_write, $file_to_write, LOCK_EX);
		}
		if ($user_set_array['database'] == true) {
			$sql = '';
			foreach ($csv_array as $key => $value) {
				$sql .= "INSERT INTO daily_report VALUES (NULL, '" . date('Ymd', time() - 86400) . "', '" . implode("', '", $value) . "');" . PHP_EOL;
			}
			try {
				$db = new PDO('mysql:host=' . $user_set_array['db_host'] . ';dbname=' . $user_set_array['db_name'] . '', $user_set_array['db_user'], $user_set_array['db_pass']); $db_insert = '';
				$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
				if ($sql) { $db->exec($sql); $db_insert = 'inserted'; }
				$db = null;
			} catch (PDOException $db_error) {
				$db_insert = PHP_EOL . $db_error->getMessage();
			}
		}
		if (!$user_set_array['test_mode']) $csv_array = array();
		$sent_messages++;
	}

	// fetch aircraft.json and read timestamp
	$json_data_array = json_decode(file_get_contents($user_set_array['url_json'] . 'aircraft.json'),true);
	isset($json_data_array['now']) ? $ac_now = date("Y-m-d G:i:s l", $json_data_array['now']) : $ac_now = '';

	// loop through aircraft section of aircraft.json file and generate csv_array that holds the data of whole day
	foreach ($json_data_array['aircraft'] as $row) {
		isset($row['hex']) ? $ac_hex = $row['hex'] : $ac_hex = '';
		isset($row['flight']) ? $ac_flight = trim($row['flight']) : $ac_flight = '';
		isset($row['category']) ? $ac_category = $row['category'] : $ac_category = '';
		isset($row['squawk']) ? $ac_squawk = $row['squawk'] : $ac_squawk = '';
		isset($row['altitude']) ? $ac_altitude = $row['altitude'] : $ac_altitude = '';
		isset($row['lat']) ? $ac_lat = $row['lat'] : $ac_lat = '';
		isset($row['lon']) ? $ac_lon = $row['lon'] : $ac_lon = '';
		isset($row['seen']) ? $ac_seen = $row['seen'] : $ac_seen = '';
		isset($row['rssi']) ? $ac_rssi = $row['rssi'] : $ac_rssi = '';
		isset($row['mlat']) ? $ac_mlat = implode(' ', $row['mlat']) : $ac_mlat = '';
		if ($ac_hex != '' && $ac_hex != '000000' && ($ac_seen != '' && $ac_seen < 1.2)) {
			$csv_array[$ac_hex]['hex'] = $ac_hex;
			isset($csv_array[$ac_hex]['msg']) ? $csv_array[$ac_hex]['msg']++ : $csv_array[$ac_hex]['msg'] = 1;
			if (!isset($csv_array[$ac_hex]['flight']) && $ac_flight == '') { $csv_array[$ac_hex]['flight'] = ''; }
			else if ($ac_flight != '') { $csv_array[$ac_hex]['flight'] = $ac_flight; }
			if (!isset($csv_array[$ac_hex]['category']) && $ac_category == '') { $csv_array[$ac_hex]['category'] = ''; }
			else if ($ac_category != '') { $csv_array[$ac_hex]['category'] = $ac_category; }
			if (!isset($csv_array[$ac_hex]['squawk']) && $ac_squawk == '') { $csv_array[$ac_hex]['squawk'] = ''; }
			else if ($ac_squawk != '') { $csv_array[$ac_hex]['squawk'] = $ac_squawk; }
			if (!isset($csv_array[$ac_hex]['f_see']) || $csv_array[$ac_hex]['f_see'] == '') $csv_array[$ac_hex]['f_see'] = $ac_now;
			if (!isset($csv_array[$ac_hex]['f_lat']) || $csv_array[$ac_hex]['f_lat'] == '') $csv_array[$ac_hex]['f_lat'] = $ac_lat;
			if (!isset($csv_array[$ac_hex]['f_lon']) || $csv_array[$ac_hex]['f_lon'] == '') $csv_array[$ac_hex]['f_lon'] = $ac_lon;
			if (!isset($csv_array[$ac_hex]['f_alt']) || $csv_array[$ac_hex]['f_alt'] == '') $csv_array[$ac_hex]['f_alt'] = $ac_altitude;
			if (!isset($csv_array[$ac_hex]['l_see']) && $ac_now == '') { $csv_array[$ac_hex]['l_see'] = ''; }
			else if ($ac_now != '') { $csv_array[$ac_hex]['l_see'] = $ac_now; }
			if (!isset($csv_array[$ac_hex]['l_lat']) && $ac_lat == '') { $csv_array[$ac_hex]['l_lat'] = ''; }
			else if ($ac_lat != '') { $csv_array[$ac_hex]['l_lat'] = $ac_lat; }
			if (!isset($csv_array[$ac_hex]['l_lon']) && $ac_lon == '') { $csv_array[$ac_hex]['l_lon'] = ''; }
			else if ($ac_lon != '') { $csv_array[$ac_hex]['l_lon'] = $ac_lon; }
			if (!isset($csv_array[$ac_hex]['l_alt']) && $ac_altitude == '') { $csv_array[$ac_hex]['l_alt'] = ''; }
			else if ($ac_altitude != '') { $csv_array[$ac_hex]['l_alt'] = $ac_altitude; }
			$ac_lat && $ac_lon ? $ac_dist = round(func_haversine($rec_lat, $rec_lon, $ac_lat, $ac_lon, $earth_radius), 1) : $ac_dist = '';
			if (!isset($csv_array[$ac_hex]['l_dist']) || $csv_array[$ac_hex]['l_dist'] == '') { $csv_array[$ac_hex]['l_dist'] = $ac_dist; }
			else if ($ac_dist != '' && $csv_array[$ac_hex]['l_dist'] > $ac_dist) { $csv_array[$ac_hex]['l_dist'] = $ac_dist; }
			if (!isset($csv_array[$ac_hex]['h_dist']) || $csv_array[$ac_hex]['h_dist'] == '') { $csv_array[$ac_hex]['h_dist'] = $ac_dist; }
			else if ($ac_dist != '' && $csv_array[$ac_hex]['h_dist'] < $ac_dist) { $csv_array[$ac_hex]['h_dist'] = $ac_dist; }
			if (!isset($csv_array[$ac_hex]['l_rssi'])) { $csv_array[$ac_hex]['l_rssi'] = $ac_rssi; }
			else if ($ac_rssi != '' && $csv_array[$ac_hex]['l_rssi'] > $ac_rssi) { $csv_array[$ac_hex]['l_rssi'] = $ac_rssi; }
			if (!isset($csv_array[$ac_hex]['h_rssi'])) { $csv_array[$ac_hex]['h_rssi'] = $ac_rssi; }
			else if ($ac_rssi != '' && $csv_array[$ac_hex]['h_rssi'] < $ac_rssi) { $csv_array[$ac_hex]['h_rssi'] = $ac_rssi; }
			if (!isset($csv_array[$ac_hex]['mlat']) && $ac_mlat == '') { $csv_array[$ac_hex]['mlat'] = ''; }
			else if ($ac_mlat != '') { $csv_array[$ac_hex]['mlat'] = 'mlat'; }
			$last_run = time() - strtotime('today');
		}
	}
	$seconds_of_day = time() - strtotime('today');
	#var_dump($csv_array);

// generate terminal output and set sleep timer to get minimum a full second until next aircraft.json is ready to get fetched
$runtime = (time() - $start_time);
$runtime_formatted = sprintf('%d days %02d:%02d:%02d', $runtime/60/60/24,($runtime/60/60)%24,($runtime/60)%60,$runtime%60);
($runtime > 0) ? $loop_clock = number_format(round(($i / $runtime),6),6) : $loop_clock = number_format(1, 6);
$process_microtime = (round(1000000 * (microtime(true) - $start_loop_microtime)));
print('upt(us): ' . sprintf('%07d', $process_microtime) . ' - ' . $loop_clock . ' loops/s avg - since ' . $runtime_formatted . ' - run ' . $i . ' => ' . sprintf('%04d', count($csv_array)) . ' aircraft(s) @ ' . array_sum(array_column($csv_array, 'msg')) . ' msg today ' . $db_insert . PHP_EOL);
sleep(1);
$i++;

}

?>
