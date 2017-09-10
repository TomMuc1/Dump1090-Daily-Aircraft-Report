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

while (true) {

	$start_loop_microtime = microtime(true);
	if ($seconds_of_day > time() - strtotime('today')) {
		if ($sent_messages < $user_set_array['mailer_limit']) {
			$email = $user_set_array['email_address'];
			$header  = 'MIME-Version: 1.0' . PHP_EOL;
			$header .= 'Content-type: text/plain; charset=iso-8859-1' . PHP_EOL;
			$header .= 'From: ' . $user_set_array['email_address'] . PHP_EOL;
			$header .= 'Reply-To: ' . $user_set_array['email_address'] . PHP_EOL;
			$header .= 'X-Mailer: PHP ' . phpversion();
			$body = '*** ' . count($hex_array) . ' Aircrafts Yesterday UTC ***' . PHP_EOL . PHP_EOL;
			foreach ($hex_array as $key => $value) {
				$body .= $key .' = ' . $value . ', ';
			}
			mail($user_set_array['email_address'], 'Your Daily Aircraft Stats', $body, $header);
		}
		$sent_messages++;
		$hex_array = array();
	}

	$x = 0;
	$json_data_array = json_decode(file_get_contents($user_set_array['url_json']),true);

	// loop through aircraft.json file
	foreach ($json_data_array['aircraft'] as $row) {
		isset($row['hex']) ? $ac_hex = $row['hex'] : $ac_hex = '';
		if ($ac_hex != '' && strpos($ac_hex, '~') === false && $ac_hex != '000000') {
			@$hex_array[$ac_hex] ++;
			$last_run = time() - strtotime('today');
		}
	}
	$seconds_of_day = time() - strtotime('today');

// generate terminal output and set sleep timer to get minimum a full second until next aircraft.json is ready to get fetched
$runtime = (time() - $start_time);
$runtime_formatted = sprintf('%d days %02d:%02d:%02d', $runtime/60/60/24,($runtime/60/60)%24,($runtime/60)%60,$runtime%60);
($runtime > 0) ? $loop_clock = number_format(round(($i / $runtime),12),12) : $loop_clock = number_format(1, 12);
$process_microtime = (round(1000000 * (microtime(true) - $start_loop_microtime)));
print('upt(us): ' . sprintf('%07d', $process_microtime) . ' - ' . $loop_clock . ' loops/s avg - since ' . $runtime_formatted . ' - run(s) ' . $i . ' => ' . sprintf('%04d', count($hex_array)) . ' aircraft(s) today (UTC)' . PHP_EOL);
sleep(1);
$i++;

}

?>
