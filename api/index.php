<?php

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
			getTimeseriesForDevice($token, $deviceId, $timerange, $aggregation);
		} else {
			header('Content-Type: application/json');
			echo json_encode(['error' => 'Unable to authenticate'], JSON_PRETTY_PRINT);
		}
		exit;
	}

	// Devices
	header('Content-Type: application/json');
	echo json_encode(getThingsBoardDevices(), JSON_PRETTY_PRINT);

}

// ENTRY CHECK AND SERVE CACHE OR FETCH DATA

function getThingsBoardDevices() {
    $cache = getCache();
	if (isset($cache) && !isset($_GET['refresh'])) {
		$deviceCacheAge = time() - $cache['deviceCacheTimestamp'];
		$telemetryCacheAge = time() - $cache['telemetryCacheTimestamp'];
		if ($telemetryCacheAge > CACHE_TELEMETRY_ALL_DURATION && $deviceCacheAge < CACHE_DEVICES_DURATION) {
			$token = getAuthToken();
			$data = fetchLastTelemetryForCachedDevices( $token, $cache);
			if ($data) {
				saveCache($data);
			}
			return ['query' => 'telemetry'] + $data; ;
			return $data;
		} else if ($telemetryCacheAge < CACHE_TELEMETRY_ALL_DURATION && $deviceCacheAge < CACHE_DEVICES_DURATION) {
			return ['query' => 'cached'] + $cache; ;
		}
    }
	
	$token = getAuthToken();
	$data = fetchDevicesFromThingsBoard($token);
	if ($data) {
		saveCache($data);
	}
	return ['query' => 'devices'] + $data; ;
	return $data;
}



/////

entry();

/////