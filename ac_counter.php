<?php

#phpinfo();
#var_dump(ini_get_all());
#ini_set('error_reporting', E_ALL);

// set path to aircraft.json file
$user_set_array['url_json'] = 'http://127.0.0.1/dump1090/data/aircraft.json';

// set email and/or logfile option to true or false
$user_set_array['email'] = 'true';    $user_set_array['log'] = 'true';

// set path to directory where log files to store to
$user_set_array['log_directory'] = '/home/pi/ac_counter_log';

// set your google email-address daily reports to send to
$user_set_array['email_address'] = 'YOUR_EMAIL@gmail.com';

// set the absolute limit of alert-messages default is 500
$user_set_array['mailer_limit'] = 500;

$i = 0;
$sent_messages = 0;
$hex_array = array();
$start_time = time();
date_default_timezone_set('UTC');
$seconds_of_day = time() - strtotime('today');
$csv_header = '"Transponder"' . "\t" . '"Messages"' . "\t" . '"Flight"' . "\t" . '"Category"' . "\t" . '"Squawk"' . "\t" . '"Set First"' . "\t" . '"First Seen"' . "\t" . '"First Latitude"' . "\t" . '"First Longitude"' . "\t" . '"First Altitude"' . "\t" . '"Set Last"'. "\t" . '"Last Seen"' . "\t" . '"Last Latitude"' . "\t" . '"Last Longitude"' . "\t" . '"Last Altitude"' . "\t" . '"Mlat"' . PHP_EOL . PHP_EOL;

while (true) {

	$start_loop_microtime = microtime(true);

	// at midnight generate csv-file and submit email and/or write log-file
	if ($seconds_of_day > time() - strtotime('today')) {
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
			$file_name_to_write = $user_set_array['log_directory'] . '/' . 'ac_' . date('Y_m_d_i') . '.xls.zip';
			if (!file_exists($user_set_array['log_directory'])) mkdir($user_set_array['log_directory'], 0755, true);
			file_put_contents($file_name_to_write, $file_to_write, LOCK_EX);
		}
		$csv_array = array();
		$sent_messages++;
	}

	// fetch aircraft.json and read timestamp
	$json_data_array = json_decode(file_get_contents($user_set_array['url_json']),true);
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
			if ((!isset($csv_array[$ac_hex]['f_see']) && !isset($csv_array[$ac_hex]['f_lat']) && !isset($csv_array[$ac_hex]['f_lon']) && !isset($csv_array[$ac_hex]['f_alt'])) && ($ac_now != '' && $ac_lat != '' && $ac_lon != '' && $ac_altitude != '')) { $csv_array[$ac_hex]['f_set'] = 'set'; }
			else if (!isset($csv_array[$ac_hex]['f_set'])) { $csv_array[$ac_hex]['f_set'] = ''; }
			if (!isset($csv_array[$ac_hex]['f_see']) || $csv_array[$ac_hex]['f_see'] == '') $csv_array[$ac_hex]['f_see'] = $ac_now;
			if (!isset($csv_array[$ac_hex]['f_lat']) || $csv_array[$ac_hex]['f_lat'] == '') $csv_array[$ac_hex]['f_lat'] = $ac_lat;
			if (!isset($csv_array[$ac_hex]['f_lon']) || $csv_array[$ac_hex]['f_lon'] == '') $csv_array[$ac_hex]['f_lon'] = $ac_lon;
			if (!isset($csv_array[$ac_hex]['f_alt']) || $csv_array[$ac_hex]['f_alt'] == '') $csv_array[$ac_hex]['f_alt'] = $ac_altitude;
			if ($ac_now != '' && $ac_lat != '' && $ac_lon != '' && $ac_altitude != '') { $csv_array[$ac_hex]['l_set'] = 'set'; }
			else { $csv_array[$ac_hex]['l_set'] = ''; }
			if (!isset($csv_array[$ac_hex]['l_see']) && $ac_now == '') { $csv_array[$ac_hex]['l_see'] = ''; }
			else if ($ac_now != '') { $csv_array[$ac_hex]['l_see'] = $ac_now; }
			if (!isset($csv_array[$ac_hex]['l_lat']) && $ac_lat == '') { $csv_array[$ac_hex]['l_lat'] = ''; }
			else if ($ac_lat != '') { $csv_array[$ac_hex]['l_lat'] = $ac_lat; }
			if (!isset($csv_array[$ac_hex]['l_lon']) && $ac_lon == '') { $csv_array[$ac_hex]['l_lon'] = ''; }
			else if ($ac_lon != '') { $csv_array[$ac_hex]['l_lon'] = $ac_lon; }
			if (!isset($csv_array[$ac_hex]['l_alt']) && $ac_altitude == '') { $csv_array[$ac_hex]['l_alt'] = ''; }
			else if ($ac_altitude != '') { $csv_array[$ac_hex]['l_alt'] = $ac_altitude; }
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
print('upt(us): ' . sprintf('%07d', $process_microtime) . ' - ' . $loop_clock . ' loops/s avg - since ' . $runtime_formatted . ' - run ' . $i . ' => ' . sprintf('%04d', count($csv_array)) . ' aircraft(s) @ ' . array_sum(array_column($csv_array, 'msg')) . ' msg today' . PHP_EOL);
sleep(1);
$i++;

}

?>
