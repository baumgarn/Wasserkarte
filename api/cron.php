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

// function to update last telemetry values for devices.json (all devices data, attributes and last telemetry)
// should update through a cronjob several times a day

function updateLastTelemetry() {
	$cache = getCache();
	$token = getAuthToken();
	$data = fetchLastTelemetryForCachedDevices( $token, $cache);
	if ($data) {
		saveCache($data);
	}
}

updateLastTelemetry();