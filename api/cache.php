<?php


// GET CACHE

function getCache()
{
	if (file_exists(CACHE_FILE_DEVICES)) {
		$fileData = file_get_contents(CACHE_FILE_DEVICES);
		$cache = json_decode($fileData, true);

		if (isset($cache['deviceCacheTimestamp']) && time() - $cache['deviceCacheTimestamp'] < CACHE_DEVICES_DURATION) {
			return $cache;
		}
	}
	return null;
}

function saveCache($data)
{
	file_put_contents(CACHE_FILE_DEVICES, json_encode($data));
}



// GET TELEMETRY CACHE

function getTelemetryCache($deviceId, $timerange, $aggregation)
{
	$cacheFile = "cache/telemetry_{$deviceId}_{$timerange}_{$aggregation}.json";
	if (file_exists($cacheFile)) {
		$fileData = file_get_contents($cacheFile);
		$cache = json_decode($fileData, true);

		if (isset($cache['timestamp'])) {
			$cacheAge = time() - $cache['timestamp'];
			if ($cacheAge < CACHE_TELEMETRY_SINGLE_DURATION) {
				return $cache;
			}
		}
	}
	return null;
}

function getTelemetryCacheToExpand($deviceId, $timerange, $aggregation)
{
	$cacheFile = "cache/telemetry_{$deviceId}_{$timerange}_{$aggregation}.json";
	if (file_exists($cacheFile)) {
		$fileData = file_get_contents($cacheFile);
		$cache = json_decode($fileData, true);
		return $cache;
	}
	return null;
}

// SAVE TELEMETRY CACHE

function saveTelemetryCache($deviceId, $timerange, $aggregation, $jsonData)
{
	$cacheFile = "cache/telemetry_{$deviceId}_{$timerange}_{$aggregation}.json";
	file_put_contents($cacheFile, $jsonData);
}

// GET CACHED DEVICE DATA

function getCachedDeviceInfo($deviceId)
{

	$cacheData = getCache();
	if ($cacheData && isset($cacheData['devices'])) {
		foreach ($cacheData['devices'] as $device) {
			if ($device['id'] === $deviceId) {
				return $device;
			}
		}
	}
	return null;
}
