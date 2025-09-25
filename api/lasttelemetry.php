<?php

// Endpoint to update last telemetry values for devices.json (all devices data, attributes and last telemetry).
// Should update through a cronjob several times a day. Add to crontab -e:
// 0 */2 * * * /usr/bin/php /var/www/wasserkarte.badbelzig-klimadaten.de/api/lasttelemetry.php > /dev/null 2>&1


require_once 'config.php';

// only run if cache file doesn't exist or was changed more than 1 hours ago
if (file_exists(CACHE_FILE_DEVICES)) {
	$mtime = filemtime(CACHE_FILE_DEVICES);
	if ($mtime !== false) {
		$age = time() - $mtime;
		if ($age < 1 * 3600) {
			http_response_code(200);
			echo "Skipped: cache younger than 1h";
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

	$cache = getCache();
	$token = getAuthToken();
	$data = fetchLastTelemetryForCachedDevices( $token, $cache);
	if ($data) {
		saveCache($data);
	}
}

updateLastTelemetry();