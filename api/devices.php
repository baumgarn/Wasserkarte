<?php

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

// GET ALL DEVICES ATTRIBUTES LAST TELEMETRY

function fetchDevicesFromThingsBoard($token)
{
	if (!$token) {
		echo "Fehler: Kein gültiger Token verfügbar.";
		return null;
	}

	$allDevices = [];
	$startTime = microtime(true);

	$page = 0;
	$pageSize = 100;

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
		$deviceIds = array_map(function ($device) {
			return $device['id']['id'];
		}, $filteredDevices);

		// get attributes and Last telemetry for devices
		list($allAttributes, $allTelemetry) = getBatchAttributesAndLastTelemetry($token, $deviceIds);

		foreach ($filteredDevices as $deviceData) {

			// device object with only necessary properties without all the thingsboard bloat
			$device = [];
			$deviceId = $deviceData['id']['id'];
			$device['name'] = $deviceData['name'];
			$device['label'] = $deviceData['label'];
			$device['id'] = $deviceId;
			$device['attributes'] = [];
			foreach ($allAttributes[$deviceId] as $attribute) {
				if (!in_array( $attribute['key'], EXCLUDED_ATTRIBUTES)) {
					$device['attributes'][$attribute['key']] = $attribute['value'];
				}		
			}
			
			if (isset($device['attributes']['map']) && ($device['attributes']['map'] == 'true' || $device['attributes']['map'] == true)) {
				
				ksort($device['attributes']);
	
				$device['telemetry'] = $allTelemetry[$deviceId]['flat'] ?? [];
	
				$device['telemetrySchema'] = expandSensorDataWithCalculations($allTelemetry[$deviceId]['schema'] ?? [], $device) ;

				$device = setAvgTWFK($device);


				$allDevices[] = $device;
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
		'cacheTime' => date('Y-m-d H:i:s', $t),
		'deviceCacheTimestamp' => $t,
		'telemetryCacheTimestamp' => $t,
		'deviceCount' => count($allDevices),
		'devices' => $allDevices,
	];
}

// GET BATCH ATTRIBUTES AND LAST TELEMETRY ONLY FOR DEVICES

function getBatchAttributesAndLastTelemetry($token, $deviceIds)
{
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

		curl_setopt($attrCh, CURLOPT_SSL_VERIFYPEER, false);

		curl_multi_add_handle($multiHandle, $attrCh);

		$teleUrl = THINGSBOARD_URL . "/plugins/telemetry/DEVICE/$deviceId/values/timeseries";
		$teleCh = curl_init($teleUrl);
		curl_setopt($teleCh, CURLOPT_HTTPHEADER, [
			"X-Authorization: Bearer " . $token,
			"Content-Type: application/json"
		]);
		curl_setopt($teleCh, CURLOPT_RETURNTRANSFER, true);
		$curlHandles["tele_$deviceId"] = $teleCh;

		curl_setopt($teleCh, CURLOPT_SSL_VERIFYPEER, false);
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
					$allTelemetry[$deviceId]['flat'] = $response;
					$allTelemetry[$deviceId]['schema'] = normalizeLatestTelemetryToSchema($response);
				}
			}
		}
		curl_multi_remove_handle($multiHandle, $ch);
		curl_close($ch);
	}

	curl_multi_close($multiHandle);
	return [$allAttributes, $allTelemetry];
}





// Format our latest telemetry to our schema format used in our timeseries telemtry for more efficient formatting 
function normalizeLatestTelemetryToSchema(array $tbResponse): array
{
	// Support both shapes
	$tele = isset($tbResponse['telemetry']) && is_array($tbResponse['telemetry'])
		? $tbResponse['telemetry']
		: $tbResponse;

	// Which allowed keys are actually present with at least one datapoint?
	$present = [];
	foreach (ALLOWED_SENSOR_KEYS as $k) {
		if (isset($tele[$k]) && is_array($tele[$k]) && !empty($tele[$k])) {
			$present[] = $k;
		}
	}

	// Build schema
	$schema = array_merge(['ts'], $present);
	$schema = expandSchema(($schema));

	// Canonical ts = max ts across present keys; fallback to received_at; else 0
	$tsCandidates = [];
	foreach ($present as $k) {
		if (isset($tele[$k][0]['ts'])) {
			$tsCandidates[] = (int)$tele[$k][0]['ts'];
		}
	}
	if (empty($tsCandidates) && isset($tele['received_at'][0]['ts'])) {
		$tsCandidates[] = (int)$tele['received_at'][0]['ts'];
	}
	$ts = !empty($tsCandidates) ? max($tsCandidates) : 0;

	// Data row (values aligned to $present order)
	$row = [$ts];
	foreach ($present as $k) {
		$row[] = isset($tele[$k][0]['value']) ? formatValue($tele[$k][0]['value']) : null;
	}
	return ['schema' => $schema, 'data' => [$row]];
}


// Update last telemetry for cached devices, this is requested in certain intervals instead of a full cache reload
function fetchLastTelemetryForCachedDevices($token, $cache)
{
	if (!$token) {
		echo "Error: No valid token.";
		return null;
	}

	$cachedDevices = $cache['devices'];
	$startTime = microtime(true);

	$deviceIds = array_map(function ($device) {
		return $device['id'];
	}, $cachedDevices);

	$allTelemetry = getBatchLastTelemetry($token, $deviceIds);

	foreach ($cachedDevices as &$device) {
		$deviceId = $device['id'];
		$device['telemetry'] = $allTelemetry[$deviceId]['flat'] ?? [];
		$device['telemetrySchema'] = $allTelemetry[$deviceId]['schema'] ?? [];
		$device['telemetrySchema'] = expandSensorDataWithCalculations($device['telemetrySchema'], $device);
	}
	unset($device);

	$endTime = microtime(true);
	$queryTime = $endTime - $startTime;

	$t = time();
	$cache['devices'] = $cachedDevices;
	$cache['cacheTime'] = date('Y-m-d H:i:s', $t + CACHE_TELEMETRY_ALL_DURATION);
	$cache['queryTime'] = $queryTime;
	$cache['telemetryCacheTimestamp'] = $t;

	return $cache;
}

// Thingsboard request for last telemetry for cached devices for above function
function getBatchLastTelemetry($token, $deviceIds)
{
	$allTelemetry = [];
	$multiHandle  = curl_multi_init();
	$curlHandles  = [];

	foreach ($deviceIds as $deviceId) {
		$teleUrl = THINGSBOARD_URL . "/plugins/telemetry/DEVICE/$deviceId/values/timeseries";
		$teleCh  = curl_init($teleUrl);
		curl_setopt_array($teleCh, [
			CURLOPT_HTTPHEADER     => ["X-Authorization: Bearer " . $token, "Content-Type: application/json"],
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYPEER => false,
		]);
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
			$response = json_decode($result, true) ?: [];
			if (strpos($key, 'tele_') === 0) {
				$deviceId = substr($key, 5);
				$allTelemetry[$deviceId]['flat'] = $response;
				$allTelemetry[$deviceId]['schema'] = normalizeLatestTelemetryToSchema($response);
			}
		}
		curl_multi_remove_handle($multiHandle, $ch);
		curl_close($ch);
	}

	curl_multi_close($multiHandle);
	return $allTelemetry;
}
