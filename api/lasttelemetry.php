<?php

// Updates last telemetry values for devices.json (all devices data, attributes and last telemetry).
// Should update through a cronjob several times a day. Add to crontab -e:
// 0 */2 * * * /usr/bin/php /var/home/badbelzig/www/wasserkarte.badbelzig-klimadaten.de/api/lasttelemetry.php >> $HOME/wasserkarte.log 2>&1


require_once 'config.php';

// only run if cache file doesn't exist or was changed more than 1 hours ago
if (file_exists(CACHE_FILE_DEVICES)) {
	$mtime = filemtime(CACHE_FILE_DEVICES);
	if ($mtime !== false) {
		$age = time() - $mtime;
		if ($age < 1 * 3600) {
			echo date('Y-m-d H:i:s', (int) (microtime(true))) . " – Skipped: cache younger than 1 hour\n";
			exit;
		}
	}
}
//

require_once 'telemetry.php';
require_once 'devices.php';
require_once 'datamodel.php';
require_once 'cache.php';
require_once 'auth.php';

if (version_compare(phpversion(), '7.1', '>=')) {
	ini_set('precision', 17);
	ini_set('serialize_precision', -1);
}

function updateLastTelemetry() {
	$startTime = microtime(true);

	$cache = getDevicesCache();
	$token = getAuthToken();
	$data = fetchLastTelemetryForCachedDevices( $token, $cache);
	if ($data) {
		saveCache($data);
	}

	$queryTime = microtime(true) - $startTime;
	echo date('Y-m-d H:i:s', (int) (microtime(true))) . " – Last telemetry updated (" . number_format($queryTime,2) . "s)\n";
}


function getDevicesCache()
{
	if (file_exists(CACHE_FILE_DEVICES)) {
		$fileData = file_get_contents(CACHE_FILE_DEVICES);
		$cache = json_decode($fileData, true);	
		return $cache;
	}
	return null;
}


updateLastTelemetry();