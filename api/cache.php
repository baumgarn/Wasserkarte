<?php

// GET CACHE

function getCache()
{
	if (file_exists(CACHE_FILE_DEVICES)) {
		$fileData = file_get_contents(CACHE_FILE_DEVICES);
		$cache = json_decode($fileData, true);
		return $cache;
	}
	return null;
}

// ATOMIC WRITE

function atomicWrite(string $finalPath, string $data): void
{
    $dir = dirname($finalPath);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException("Failed to create directory: $dir");
        }
    }

    // Create temp file in same directory to keep rename() atomic
    $tmp = tempnam($dir, 'tmp_');
    if ($tmp === false) {
        throw new RuntimeException("Failed to create temp file in $dir");
    }

    $fp = fopen($tmp, 'wb');
    if ($fp === false) {
        @unlink($tmp);
        throw new RuntimeException("Failed to open temp file $tmp");
    }

    $len     = strlen($data);
    $written = fwrite($fp, $data);
    if ($written === false || $written !== $len) {
        fclose($fp);
        @unlink($tmp);
        throw new RuntimeException("Failed to write all bytes to $tmp");
    }

    fflush($fp);
    if (function_exists('fsync')) { @fsync($fp); }
    fclose($fp);

    // Set permissions so file is always user-writable and world-readable
    @chmod($tmp, 0644);

    // Atomic swap
    if (!@rename($tmp, $finalPath)) {
        @unlink($tmp);
        throw new RuntimeException("Failed to rename $tmp to $finalPath");
    }
}

// SAVE CACHE

function saveCache(array $data): void
{
	$json = json_encode($data, JSON_PRETTY_PRINT);
    atomicWrite(CACHE_FILE_DEVICES, $json);
	$gzData = gzencode($json, 9);
    $gzPath = CACHE_FILE_DEVICES . '.gz';
    atomicWrite($gzPath, $gzData);
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

function saveTelemetryCache(string $deviceId, string $timerange, string $aggregation, string $jsonData): void
{
    $cacheFile = __DIR__ . "/cache/telemetry_{$deviceId}_{$timerange}_{$aggregation}.json";
    atomicWrite($cacheFile, $jsonData);
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
