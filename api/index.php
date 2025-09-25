<?php

if (version_compare(phpversion(), '7.1', '>=')) {
	ini_set('precision', 17);
	ini_set('serialize_precision', -1);
}

require_once 'config.php';
require_once 'telemetry.php';
require_once 'devices.php';
require_once 'datamodel.php';
require_once 'cache.php';
require_once 'auth.php';

// ENTRY POINT

function entry() {
	// Telemetry
	if (isset($_GET['deviceId'])) {
		$deviceId = $_GET['deviceId'];
		$token = getAuthToken();
		if ($token) {
			$timerange = $_GET['time'] ?? 'day';
			$aggregation = $_GET['agg'] ?? '1d';
			$data = getTimeseriesForDevice($token, $deviceId, null, $timerange, $aggregation);
			$json = json_encode($data, JSON_PRETTY_PRINT);
			saveTelemetryCache($deviceId, $timerange, $aggregation, $json);
			header('Content-Type: application/json');
			echo $json;
		} else {
			header('Content-Type: application/json');
			echo json_encode(['error' => 'Unable to authenticate'], JSON_PRETTY_PRINT);
		}
		return;
	}

	// Devices
	header('Content-Type: application/json');
	echo json_encode(getThingsBoardDevices(), JSON_PRETTY_PRINT);

}

/////

entry();

/////