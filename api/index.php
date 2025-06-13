<?php

include 'config.php';

// ENTRY POINT

function entry() {
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

	header('Content-Type: application/json');
	echo json_encode(getThingsBoardDevices(), JSON_PRETTY_PRINT);

}

// ENTRY CHECK AND SERVE CACHE OR FETCH DATA

function getThingsBoardDevices() {
    $cache = getCache();
	if ($cache && !isset($_GET['refresh'])) {
		$deviceCacheAge = time() - $cache['deviceCacheTimestamp'];
		$telemetryCacheAge = time() - $cache['telemetryCacheTimestamp'];
		if ($telemetryCacheAge > CACHE_TELEMETRY_ALL_DURATION && $deviceCacheAge < CACHE_DEVICES_DURATION) {
			$token = getAuthToken();
			$data = fetchTelemetryForCachedDevices( $token, $cache);
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

// GET CACHE

function getCache() {
    if (file_exists(CACHE_FILE_DEVICES)) {
        $fileData = file_get_contents(CACHE_FILE_DEVICES);
        $cache = json_decode($fileData, true);

        if (isset($cache['deviceCacheTimestamp']) && time() - $cache['deviceCacheTimestamp'] < CACHE_DEVICES_DURATION) {
            return $cache;
        }
    }
    return null;
}

function saveCache($data) {
    file_put_contents(CACHE_FILE_DEVICES, json_encode($data));
}

// GET LATEST TELEMETRY FOR CACHED DEVICES

function fetchTelemetryForCachedDevices($token, $cache) {
    if (!$token) {
        echo "Fehler: Kein gültiger Token verfügbar.";
        return null;
    }

	$cachedDevices = $cache['devices']; 
    $startTime = microtime(true);

	$deviceIds = array_map(function($device) {
		return $device['id']['id'];
	}, $cachedDevices);

	$telemetry = getBatchTelemetry($token, $deviceIds);

	foreach ($cachedDevices as $device) {
		$deviceId = $device['id']['id'];
		$device['telemetry'] = $telemetry[$deviceId] ?? [];
	}


    $endTime = microtime(true);
    $queryTime = $endTime - $startTime;

	$cache['queryTime'] = $queryTime;
	$t = time();
	$cache['telemetryCacheTimestamp'] = $t;
	$cache['telemetryCacheTime'] = date('Y-m-d H:i:s', $t);
	$cache['telemetryCacheUpdate'] = date('Y-m-d H:i:s', $t + CACHE_TELEMETRY_ALL_DURATION);

	return $cache;

}

// GET ALL DEVICES ATTRIBUTES TELEMETRY

function fetchDevicesFromThingsBoard($token) {
    if (!$token) {
        echo "Fehler: Kein gültiger Token verfügbar.";
        return null;
    }

    $allDevices = [];
    $startTime = microtime(true);

	$page = 0;
	$pageSize = 1000;

	while (true) {
		$url = THINGSBOARD_URL . "/tenant/devices?&page=$page&pageSize=$pageSize";
		
		$options = [
			"http" => [
				"header" => "X-Authorization: Bearer " . $token . "\r\n" .
							"Content-Type: application/json\r\n",
				"method" => "GET"
			]
		];
		$context = stream_context_create($options);
		$result = @file_get_contents($url, false, $context);

		if ($result === false) {
			$error = error_get_last();
			echo "Fehler beim Abrufen der Geräte: " . $error['message'];
			if (isset($http_response_header)) {
				echo "\nHTTP Antwort Header: ";
				print_r($http_response_header);
			}
			break;
		}

		$response = json_decode($result, true);

		if (!isset($response['data'])) {
			echo "Fehler: Keine Geräte in der Antwort gefunden.";
			break;
		}

		$filteredDevices = $response['data'];
		$deviceIds = array_map(function($device) {
			return $device['id']['id'];
		}, $filteredDevices);

		list($attributes, $telemetry) = getBatchAttributesAndTelemetry($token, $deviceIds);

		foreach ($filteredDevices as $device) {
			$deviceId = $device['id']['id'];
			$device['attributes'] = $attributes[$deviceId] ?? [];
			$device['telemetry'] = $telemetry[$deviceId] ?? [];

			foreach ($device['attributes'] as $attribute) {
				if ($attribute['key'] == 'map' && ($attribute['value'] == 'true' || $attribute['value'] == true)) {
					$allDevices[] = $device;
				} 
			}
		}

		if ($page >= $response['totalPages'] - 1) {
			break;
		}

		$page++;
	}

    $endTime = microtime(true);
    $queryTime = $endTime - $startTime;
	$t = time();

    return [
        'queryTime' => $queryTime,
        'deviceCacheTimestamp' => $t,
        'deviceCacheTime' => date('Y-m-d H:i:s', $t),
        'deviceCacheUpdate' => date('Y-m-d H:i:s', $t + CACHE_DEVICES_DURATION),
        'telemetryCacheTimestamp' => $t,
        'telemetryCacheTime' => date('Y-m-d H:i:s', $t),
        'telemetryCacheUpdate' => date('Y-m-d H:i:s', $t + CACHE_TELEMETRY_ALL_DURATION),
        'deviceCount' => count($allDevices),
        'devices' => $allDevices,
    ];
}

// GET BATCH ATTRIBUTES AND TELEMETRY

function getBatchAttributesAndTelemetry($token, $deviceIds) {
    $allAttributes = [];
    $allTelemetry = [];
    $multiHandle = curl_multi_init();
    $curlHandles = [];

    foreach ($deviceIds as $deviceId) {

		$attrUrl = THINGSBOARD_URL . "/plugins/telemetry/DEVICE/$deviceId/values/attributes";
        $attrCh = curl_init($attrUrl);
        curl_setopt($attrCh, CURLOPT_HTTPHEADER, [
            "X-Authorization: Bearer " . $token,
            "Content-Type: application/json"
        ]);
        curl_setopt($attrCh, CURLOPT_RETURNTRANSFER, true);
        $curlHandles["attr_$deviceId"] = $attrCh;
        curl_multi_add_handle($multiHandle, $attrCh);

        $teleUrl = THINGSBOARD_URL . "/plugins/telemetry/DEVICE/$deviceId/values/timeseries";
        $teleCh = curl_init($teleUrl);
        curl_setopt($teleCh, CURLOPT_HTTPHEADER, [
            "X-Authorization: Bearer " . $token,
            "Content-Type: application/json"
        ]);
        curl_setopt($teleCh, CURLOPT_RETURNTRANSFER, true);
        $curlHandles["tele_$deviceId"] = $teleCh;
        curl_multi_add_handle($multiHandle, $teleCh);
    }

    $running = null;
    do {
        curl_multi_exec($multiHandle, $running);
        curl_multi_select($multiHandle);
    } while ($running > 0);

    foreach ($curlHandles as $key => $ch) {
        $result = curl_multi_getcontent($ch);
        if ($result !== false) {
            $response = json_decode($result, true);
            if (!empty($response)) {
                if (strpos($key, 'attr_') === 0) {
                    $deviceId = str_replace('attr_', '', $key);
                    $allAttributes[$deviceId] = $response;
                } elseif (strpos($key, 'tele_') === 0) {
                    $deviceId = str_replace('tele_', '', $key);
                    $allTelemetry[$deviceId] = $response;
                }
            }
        }
        curl_multi_remove_handle($multiHandle, $ch);
        curl_close($ch);
    }

    curl_multi_close($multiHandle);
    return [$allAttributes, $allTelemetry];
}


// GET BATCH TELEMETRY

function getBatchTelemetry($token, $deviceIds) {
    $allAttributes = [];
    $allTelemetry = [];
    $multiHandle = curl_multi_init();
    $curlHandles = [];

    foreach ($deviceIds as $deviceId) {

        $teleUrl = THINGSBOARD_URL . "/plugins/telemetry/DEVICE/$deviceId/values/timeseries";
        $teleCh = curl_init($teleUrl);
        curl_setopt($teleCh, CURLOPT_HTTPHEADER, [
            "X-Authorization: Bearer " . $token,
            "Content-Type: application/json"
        ]);
        curl_setopt($teleCh, CURLOPT_RETURNTRANSFER, true);
        $curlHandles["tele_$deviceId"] = $teleCh;
        curl_multi_add_handle($multiHandle, $teleCh);
    }

    $running = null;
    do {
        curl_multi_exec($multiHandle, $running);
        curl_multi_select($multiHandle);
    } while ($running > 0);

    foreach ($curlHandles as $key => $ch) {
        $result = curl_multi_getcontent($ch);
        if ($result !== false) {
            $response = json_decode($result, true);
            if (!empty($response)) {
             	if (strpos($key, 'tele_') === 0) {
                    $deviceId = str_replace('tele_', '', $key);
                    $allTelemetry[$deviceId] = $response;
                }
            }
        }
        curl_multi_remove_handle($multiHandle, $ch);
        curl_close($ch);
    }

    curl_multi_close($multiHandle);
    return $allTelemetry;
}

// GET EARLIEST TIMESTAMP

function getEarliestTimestamp($token, $deviceId, $sensorKeys) {
    $params = [
        'keys' => $sensorKeys,
        'startTs' => 0,
        'endTs' => round(microtime(true) * 1000),
        'limit' => 1,
        'orderBy' => 'ASC',
        'agg' => 'NONE'
    ];

    $url = THINGSBOARD_URL . "/plugins/telemetry/DEVICE/$deviceId/values/timeseries?" . http_build_query($params);

    $options = [
        "http" => [
            "header" => "X-Authorization: Bearer $token\r\n" .
                        "Content-Type: application/json\r\n",
            "method" => "GET"
        ]
    ];

    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);

    if ($result === false) {
        echo "Fehler beim Abrufen der frühesten Telemetriedaten: " . error_get_last()['message'];
        return null;
    }

    $data = json_decode($result, true);

    if (empty($data)) {
        echo "Keine Telemetriedaten gefunden.\n";
        return null;
    }

    $earliestTs = null;
    foreach ($data as $key => $entries) {
        if (!empty($entries)) {
            $ts = $entries[0]['ts'];
            if ($earliestTs === null || $ts < $earliestTs) {
                $earliestTs = $ts;
            }
        }
    }

    return $earliestTs;
}
function fetchTelemetryChunk($token, $deviceId, $sensorKeys, $startTs, $endTs, $aggregation) {
    $params = [
        'keys' => $sensorKeys,
        'startTs' => $startTs,
        'endTs' => $endTs,
        'orderBy' => 'ASC'
    ];

    if ($aggregation == '1d') {
        $params['agg'] = 'AVG';
        $params['intervalType'] = 'MILLISECONDS';
        $params['interval'] = 1000 * 60 * 60 * 24;
    } else {
        $params['agg'] = 'NONE';
        $params['limit'] = 1000000000;
    }

    $url = THINGSBOARD_URL . "/plugins/telemetry/DEVICE/$deviceId/values/timeseries?" . http_build_query($params);

    $headers = [
        "X-Authorization: Bearer $token",
        "Content-Type: application/json"
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_FAILONERROR => false,
    ]);

    $result = curl_exec($ch);

    if ($result === false) {
        curl_close($ch);
        throw new Exception("Fetch error: " . curl_error($ch));
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 400) {
        throw new Exception("HTTP Error $httpCode\nResponse body:\n$result");
    }

    return json_decode($result, true);
}


function mergeTelemetryData($baseData, $newData) {
    foreach ($newData as $key => $values) {
        if (!isset($baseData[$key])) {
            $baseData[$key] = [];
        }
        $baseData[$key] = array_merge($baseData[$key], $values);
    }
    return $baseData;
}


function getTimeseriesForDevice($token, $deviceId, $timerange = 'all', $aggregation = '1d') {
    $startTime = microtime(true);
    $now = time() * 1000;

    $deviceInfo = getCachedDeviceInfo($deviceId);
    if (!$deviceInfo) {
        echo json_encode(['error' => 'Device info missing']);
        return;
    }

    $sensorKeys = implode(',', extractSensorKeys($deviceInfo));

    $expandTelemetryData = null;
    if ($timerange === 'all' && !isset($_GET['refresh'])) {
        $expandTelemetryData = getTelemetryCacheToExpand($deviceId, $timerange, $aggregation);
    }

    if ($expandTelemetryData) {
        $startTs = $expandTelemetryData['latestTimestamp'] + 1;
        $cachedData = $expandTelemetryData['data'];
    } else if ($timerange === 'all') {
        $startTs = getEarliestTimestamp($token, $deviceId, $sensorKeys);
        $cachedData = [];
    } else {
        $timerangeMapping = [
            'day' => strtotime('-1 day', $now / 1000) * 1000,
            'week' => strtotime('-1 week', $now / 1000) * 1000,
            'month' => strtotime('-1 month', $now / 1000) * 1000,
            'year' => strtotime('-1 year', $now / 1000) * 1000
        ];
        $startTs = $timerangeMapping[$timerange] ?? $now;
        $cachedData = [];
    }

    $telemetryData = $cachedData;
    $chunkSize = 1000 * 60 * 60 * 24 * 365; // 1 year in ms
    $totalNewDataPoints = 0;

    if ($aggregation && $timerange === 'all') {
        for ($chunkStart = $startTs; $chunkStart < $now; $chunkStart += $chunkSize) {
            $chunkEnd = min($chunkStart + $chunkSize - 1, $now);
            try {
                $chunkData = fetchTelemetryChunk($token, $deviceId, $sensorKeys, $chunkStart, $chunkEnd, $aggregation);
                foreach ($chunkData as $k => $arr) {
                    $totalNewDataPoints += count($arr);
                }
                $telemetryData = mergeTelemetryData($telemetryData, $chunkData);
            } catch (Exception $e) {
                echo $e->getMessage();
                return;
            }
        }
    } else {
        // Non-aggregated, no paging
        try {
            $chunkData = fetchTelemetryChunk($token, $deviceId, $sensorKeys, $startTs, $now, $aggregation);
            foreach ($chunkData as $k => $arr) {
                $totalNewDataPoints += count($arr);
            }
            $telemetryData = mergeTelemetryData($telemetryData, $chunkData);
        } catch (Exception $e) {
            echo $e->getMessage();
            return;
        }
    }

    // Find earliest/latest timestamps
    $earliest = PHP_INT_MAX;
    $latest = 0;
    foreach ($telemetryData as $arr) {
        if (!$arr) continue;
        $first = $arr[0]['ts'];
        $last = $arr[count($arr) - 1]['ts'];
        if ($first < $earliest) $earliest = $first;
        if ($last > $latest) $latest = $last;
    }

    $endTime = microtime(true);
    $queryTime = $endTime - $startTime;

    $data = [
        'timestamp' => time(),
        'newDataPoints' => $totalNewDataPoints,
        'totalDataPoints' => array_sum(array_map('count', $telemetryData)),
        'earliestTimestamp' => $earliest,
        'latestTimestamp' => $latest,
		'earliestDate' => date('Y-m-d H:i:s', (int) ($earliest / 1000)),
		'latestDate' => date('Y-m-d H:i:s', (int) ($latest / 1000)),        'queryTime' => $queryTime,
        'data' => $telemetryData
    ];

    saveTelemetryCache($deviceId, $timerange, $aggregation, json_encode($data, JSON_PRETTY_PRINT));

    header('Content-Type: application/json');
    echo json_encode($data, JSON_PRETTY_PRINT);
}
// GET TELEMETRY CACHE

function getTelemetryCache($deviceId, $timerange, $aggregation) {
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

function getTelemetryCacheToExpand($deviceId, $timerange, $aggregation) {
    $cacheFile = "cache/telemetry_{$deviceId}_{$timerange}_{$aggregation}.json";
    if (file_exists($cacheFile)) {
        $fileData = file_get_contents($cacheFile);
        $cache = json_decode($fileData, true);
        return $cache;
    }
    return null;
}

// SAVE TELEMETRY CACHE

function saveTelemetryCache($deviceId, $timerange, $aggregation, $jsonData) {
    $cacheFile = "cache/telemetry_{$deviceId}_{$timerange}_{$aggregation}.json";
    file_put_contents($cacheFile, $jsonData);
}

// GET CACHED DEVICE DATA

function getCachedDeviceInfo($deviceId) {

    $cacheData = getCache();
    if ($cacheData && isset($cacheData['devices'])) {
        foreach ($cacheData['devices'] as $device) {
            if ($device['id']['id'] === $deviceId) {
                return $device;
            }
        }
    }
    return null;
}

// GET SENSOR KEYS FROM DEVICE DATA

function extractSensorKeys($deviceInfo) {

	$sensorKeys = [];
    if (isset($deviceInfo['telemetry'])) {
        foreach ($deviceInfo['telemetry'] as $key => $value) {
            if (in_array($key, ALLOWED_SENSOR_KEYS)) {
                $sensorKeys[] = $key;
            }
        }
    }
    return $sensorKeys;
}

// AUTHENTICATION //

function getAuthToken() {
    $tokenData = getTokenCache();
    if ($tokenData && !isTokenExpired($tokenData)) {
        return $tokenData['token'];
    }
    
    $newToken = loginToThingsBoard();
    if ($newToken) {
        saveTokenCache($newToken);
        return $newToken;
    }
    
    return null;
}

function loginToThingsBoard() {
    $url = THINGSBOARD_URL . "/auth/login";
    $data = json_encode([
    	"username" => USERNAME,
        "password" => PASSWORD
    ]);
    
    $options = [
        "http" => [
            "header" => "Content-Type: application/json\r\n",
            "method" => "POST",
            "content" => $data
        ]
    ];
      $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);

    if ($result === false) {
        echo "Fehler beim Login: " . error_get_last()['message'];
        return null;
    }

    $response = json_decode($result, true);
    if (!isset($response["token"])) {
        echo "Fehler: Kein Token in der Antwort vorhanden. Antwort: " . print_r($response, true);
        return null;
    }

    return $response["token"];
}

function getTokenCache() {
    if (file_exists(TOKEN_FILE)) {
        $fileData = file_get_contents(TOKEN_FILE);
        return json_decode($fileData, true);
    }
    return null;
}

function isTokenExpired($tokenData) {
    if (!is_array($tokenData) || !isset($tokenData['timestamp'])) {
        return true;
    }
    $tokenAge = time() - $tokenData['timestamp'];
    return $tokenAge >= 900; // 15 Minutes
}

function saveTokenCache($token) {
    $cache = [
        'timestamp' => time(),
        'token' => $token
    ];
    file_put_contents(TOKEN_FILE, json_encode($cache));
	chmod(TOKEN_FILE, 0600);
}


/////

entry();

/////