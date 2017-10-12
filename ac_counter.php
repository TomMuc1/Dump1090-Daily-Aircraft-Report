<?php

#phpinfo();
#var_dump(ini_get_all());
#ini_set('error_reporting', E_ALL);

// set your google email-address daily reports to send to
$user_set_array['email_address'] = 'YOUR_EMAIL@gmail.com';

// set path to aircraft.json file
$user_set_array['url_json'] = 'http://127.0.0.1/dump1090/data/aircraft.json';

// set the absolute limit of alert-messages default is 500 this script is allowed to send over its whole runtime
$user_set_array['mailer_limit'] = 500;

$i = 0;
$sent_messages = 0;
$hex_array = array();
$start_time = time();
date_default_timezone_set('UTC');
$seconds_of_day = time() - strtotime('today');
$csv_header = '"Transponder"' . "\t" . '"Messages"' . "\t" . '"Flight"' . "\t" . '"First Seen"' . "\t" . '"First Latitude"' . "\t" . '"First Longitude"' . "\t" . '"First Altitude"' . "\t" . '"Last Seen"' . "\t" . '"Last Latitude"' . "\t" . '"Last Longitude"' . "\t" . '"Last Altitude"' . PHP_EOL . PHP_EOL;

while (true) {

	$start_loop_microtime = microtime(true);

	if ($seconds_of_day > time() - strtotime('today')) {
		if ($sent_messages < $user_set_array['mailer_limit']) {
			$csv = '';
			$csv .= $csv_header;
			foreach ($csv_array as $key => $value) {
				$csv .= "\"" . implode("\"\t\"", str_replace('.', ',', $value)) . "\"" . PHP_EOL;
			}
			$boundary = str_replace(' ', '.', microtime());
			$header = 'From: ' . $user_set_array['email_address'] . PHP_EOL;
			$header .= 'Reply-To: ' . $user_set_array['email_address'] . PHP_EOL;
			$header .= 'MIME-Version: 1.0' . PHP_EOL;
			$header .= 'Content-Type: multipart/mixed; boundary="' . $boundary . '"' . PHP_EOL . PHP_EOL;
			$body = '--' . $boundary . PHP_EOL;
			$body .= 'Content-type:text/plain; charset=iso-8859-1' . PHP_EOL;
			$body .= 'Content-Transfer-Encoding: 7bit' . PHP_EOL . PHP_EOL;
			$body .= '*** ' . count($csv_array) . ' Aircrafts Yesterday UTC @ ' . array_sum(array_column($csv_array, 'msg')) . ' Messages Overall ***' . PHP_EOL . PHP_EOL;
			$body .= '--' . $boundary . PHP_EOL;
			$body .= 'Content-Type: application/octet-stream; name="aircrafts.xls"' . PHP_EOL;
			$body .= 'Content-Transfer-Encoding: base64' . PHP_EOL;
			$body .= 'Content-Disposition: attachment; filename="aircrafts.xls"' . PHP_EOL . PHP_EOL;
			$body .= chunk_split(base64_encode($csv)) . PHP_EOL . PHP_EOL;
			$body .= '--' . $boundary . '--';
			mail($user_set_array['email_address'], 'Your Daily Aircraft Stats', $body, $header);
		}
		$csv_array = array();
		$sent_messages++;
	}

	$x = 0;
	$json_data_array = json_decode(file_get_contents($user_set_array['url_json']),true);

	// loop through aircraft.json file
	foreach ($json_data_array['aircraft'] as $row) {
		isset($json_data_array['now']) ? $ac_now = date("Y-m-d G:i:s l", $json_data_array['now']) : $ac_now = '';
		isset($row['hex']) ? $ac_hex = $row['hex'] : $ac_hex = '';
		isset($row['flight']) ? $ac_flight = trim($row['flight']) : $ac_flight = '';
		isset($row['altitude']) ? $ac_altitude = $row['altitude'] : $ac_altitude = '';
		isset($row['lat']) ? $ac_lat = $row['lat'] : $ac_lat = '';
		isset($row['lon']) ? $ac_lon = $row['lon'] : $ac_lon = '';
		isset($row['seen']) ? $ac_seen = $row['seen'] : $ac_seen = '';
		if ($ac_hex != '' && $ac_hex != '000000' && ($ac_seen != '' && $ac_seen < 1.2)) {
			isset($csv_array[$ac_hex]['hex']) ? $csv_array[$ac_hex]['hex'] = $ac_hex : $csv_array[$ac_hex]['hex'] = '';
			isset($csv_array[$ac_hex]['msg']) ? $csv_array[$ac_hex]['msg']++ : $csv_array[$ac_hex]['msg'] = 0;
			$ac_flight != '' ? $csv_array[$ac_hex]['flight'] = $ac_flight : $csv_array[$ac_hex]['flight'] = '';
			if (!isset($csv_array[$ac_hex]['f_see']) || $csv_array[$ac_hex]['f_see'] == '') $csv_array[$ac_hex]['f_see'] = $ac_now;
			if (!isset($csv_array[$ac_hex]['f_lat']) || $csv_array[$ac_hex]['f_lat'] == '') $csv_array[$ac_hex]['f_lat'] = $ac_lat;
			if (!isset($csv_array[$ac_hex]['f_lon']) || $csv_array[$ac_hex]['f_lon'] == '') $csv_array[$ac_hex]['f_lon'] = $ac_lon;
			if (!isset($csv_array[$ac_hex]['f_alt']) || $csv_array[$ac_hex]['f_alt'] == '') $csv_array[$ac_hex]['f_alt'] = $ac_altitude;
			$ac_now != '' ? $csv_array[$ac_hex]['l_see'] = $ac_now : $csv_array[$ac_hex]['l_see'] = '';
			$ac_lat != '' ? $csv_array[$ac_hex]['l_lat'] = $ac_lat : $csv_array[$ac_hex]['l_lat'] = '';
			$ac_lon != '' ? $csv_array[$ac_hex]['l_lon'] = $ac_lon : $csv_array[$ac_hex]['l_lon'] = '';
			$ac_altitude != '' ? $csv_array[$ac_hex]['l_alt'] = $ac_altitude : $csv_array[$ac_hex]['l_alt'] = '';
			$last_run = time() - strtotime('today');
		}
	}
	$seconds_of_day = time() - strtotime('today');
	#var_dump($csv_array);

// generate terminal output and set sleep timer to get minimum a full second until next aircraft.json is ready to get fetched
$runtime = (time() - $start_time);
$runtime_formatted = sprintf('%d days %02d:%02d:%02d', $runtime/60/60/24,($runtime/60/60)%24,($runtime/60)%60,$runtime%60);
($runtime > 0) ? $loop_clock = number_format(round(($i / $runtime),12),12) : $loop_clock = number_format(1, 12);
$process_microtime = (round(1000000 * (microtime(true) - $start_loop_microtime)));
print('upt(us): ' . sprintf('%07d', $process_microtime) . ' - ' . $loop_clock . ' loops/s avg - since ' . $runtime_formatted . ' - run(s) ' . $i . ' => ' . sprintf('%04d', count($csv_array)) . ' aircraft(s) @ ' . array_sum(array_column($csv_array, 'msg')) . ' messages today (UTC)' . PHP_EOL);
sleep(1);
$i++;

}

?>
