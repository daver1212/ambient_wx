<?php

$debug = !empty($_GET['debug']);

if ($debug) {
	ini_set('display_errors', 1);
	ini_set('display_startup_errors', 1);
	error_reporting(E_ALL);
}

include_once 'ambient.conf';
include_once 'consqlp.php';

// Allow the mac address to be specified on the url
if (!empty($_GET['mac_address'])) {
	$mac_address = $_GET['mac_address'];
}

$mac_address_array = explode(',', $mac_address);

foreach ($mac_address_array as $mac_address) {
	$mac_address = trim($mac_address);
	$cmd = "curl --location --request GET 'https://api.ambientweather.net/v1/devices/$mac_address?apiKey=$api_key&applicationKey=$application_key&limit=288'";

	$ambient_result = shell_exec($cmd);

	if (!empty($ff)) {echo $ambient_result;}

	$ambient_array = json_decode($ambient_result);
	$records_retrieved = count($ambient_array);
	if ($debug) {
		echo "<br>" . count($ambient_array) . " records retrieved.";
	}

	// Process in the retrieved data
	$seq = $new_records = $duplicate_records = $db_errors = 0;		// initialize all counters
	foreach ($ambient_array as $record_object) {
		$seq++;
		$current_datetime = date('Y-m-d H:i:s');
		$record_array = (array) $record_object;
		
		extract($record_array);
		if ($battout == 1) {
			$battery = 'ok';
		} else {
			$battery = 'low';
		}
		if (empty($lastRain)) {					// if no rain since device was activated
			$lastRain = '0000-00-00 00:00:00';
		}
		
		$sql = "
			insert into ambient.station_data 
				(mac_address, dateutc, tempinf, humidityin, baromrelin, baromabsin, tempf, battout, humidity, 
				winddir, windspeedmph, windgustmph, maxdailygust, 
				hourlyrainin, eventrainin, dailyrainin, weeklyrainin, monthlyrainin, totalrainin, 
				solarradiation, uv, feelsLike, dewPoint, feelsLikein, dewPointin, lastRain, date)
				VALUES( 
					'$mac_address', $dateutc, $tempinf, $humidityin, $baromrelin, $baromabsin, $tempf, '$battery', $humidity,
					$winddir, $windspeedmph, $windgustmph, $maxdailygust,
					$hourlyrainin, $eventrainin, $dailyrainin, $weeklyrainin, $monthlyrainin, $totalrainin,
					$solarradiation, $uv, $feelsLike, $dewPoint, $feelsLikein, $dewPointin, '$lastRain', '$date')
		";
		try {
			$insert_result = $conn -> query($sql);
			$new_records++;
			if ($debug) {
				echo "<br>" . str_pad($seq, 5) . "$current_datetime $mac_address $date stored";
			}
		}


		
		catch(PDOException $e) {
			if ($e->getCode() == 23000) {
				$duplicate_records++;
				if ($debug) {
					echo "<br>" . str_pad($seq, 5) . "$current_datetime $mac_address $date Record already stored: $mac_address $dateutc";
				}
			} else {
				$db_errors++;
				if (debug) {
					echo "<br>" . str_pad($seq, 5) . "$current_datetime $mac_address $date Error: " . $e->getMessage();
				}
			}
			$myfile = fopen("/var/log/ambient/sqlerror.log", "a");
			fwrite($myfile, date('Y-m-d H:i:s') . ' ' . $date . ": " . $e->getMessage() . "\n");
		}
		
	}

	echo "<br><br>$records_retrieved total records received for $mac_address";
	echo "<br>$new_records records added to the DB";
	echo "<br>$duplicate_records duplicate records already in the DB";
	echo "<br>$db_errors errors inserting into the DB";
}

function convert_time_zone($epoch, $to_tz='America/Los_Angeles', $from_tz= 'Zulu') {
	if (strlen((string) $epoch) > 10) {
		$epoch = substr($epoch, 0, 10);
	}
	$time_object = new DateTime("@$epoch", new DateTimeZone($from_tz));
	$time_object->setTimezone(new DateTimeZone($to_tz));
	return $time_object->format('Y-m-d H:i:s');
}


?>