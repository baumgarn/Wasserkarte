<?php

// function to get timeseries for devices
// aggregated data always expands on already cached data to reduce load on thingsboard
// data is converted to a scheme - data array format
// nfk for all sensor depths and average nfk and vol values are calculated

function getTimeseriesForDevice($token, $deviceId, $deviceInfo = null, $timerange = 'all', $aggregation = '1d') {

	$startTime = microtime(true);
	$now = time() * 1000;

	if (! isset($deviceInfo)) {
		$deviceInfo = getCachedDeviceInfo($deviceId);
		if (!$deviceInfo) {
			echo json_encode(['error' => 'Device info missing']);
			return;
		}
	}

	// original data schema from original devices request is already populated with our calculated field names nfk_10cm etc...
	if (!isset($deviceInfo['telemetrySchema']['schema'])) {
		return;
	}
	$schema = $deviceInfo['telemetrySchema']['schema'];

	$sensorKeys = implode(',', array_slice($schema, 1));
	
	// Usually we get the already cached data and only expand from that
	$expandTelemetryData = null;
	if ($timerange === 'all' && !isset($_GET['refresh'])) {
		$expandTelemetryData = getTelemetryCacheToExpand($deviceId, $timerange, $aggregation);
	}

	if ($expandTelemetryData) {
		$startTs = $expandTelemetryData['latestTimestamp'] + 1;
		$cachedData = $expandTelemetryData['telemetry'];
	} else if ($timerange === 'all') {
		$startTs = getEarliestTimestamp($token, $deviceId, $sensorKeys);
		$cachedData = null;
	} else {
		$timerangeMapping = [
			'day' => strtotime('-1 day', $now / 1000) * 1000,
			'week' => strtotime('-1 week', $now / 1000) * 1000,
			'month' => strtotime('-1 month', $now / 1000) * 1000,
			'year' => strtotime('-1 year', $now / 1000) * 1000
		];
		$startTs = $timerangeMapping[$timerange] ?? $now;
		$cachedData = null;
	}
	
	if ($aggregation !== 'raw') {
		$startTs = floorToLocalMidnightMs($startTs);
		$endTs = floorToLocalMidnightMs($now);
	}
	if ($startTs < CUTOFF_DATE) {
		$startTs = CUTOFF_DATE;
	}
	
	$telemetryData = $cachedData;

	$newDataPointsCount = 0;

	if ($aggregation != 'raw' && $timerange === 'all') { 
		// check if already a full day has passed
		$shouldFetch = true;
		if ($telemetryData && !empty($telemetryData['data'])) {
			// if (floorToLocalMidnightMs(end($telemetryData['data'])[0]) >= floorToLocalMidnightMs($now) - 86400000) {
			if (floorToLocalMidnightMs($now) <= floorToLocalMidnightMs(end($telemetryData['data'])[0])) {
				$shouldFetch = false;
			}
		}

		if ($shouldFetch) {
			$chunkSize = 1000 * 60 * 60 * 24 * 365 * 1; // 1 year in ms

			for ($chunkStart = $startTs; $chunkStart < $endTs; $chunkStart += $chunkSize) {
				$chunkEnd = min($chunkStart + $chunkSize - 1, $endTs);
				try {
					$dataChunk = fetchTelemetryChunk($token, $deviceId, $sensorKeys, $chunkStart, $chunkEnd, $aggregation);
					$dataChunk = buildSchemaData($dataChunk, $schema, $aggregation);
					$dataChunk = expandSensorDataWithCalculations($dataChunk, $deviceInfo);
					
					$newDataPointsCount += count($dataChunk['data']);
					$telemetryData = mergeTelemetryData($telemetryData, $dataChunk);
				} catch (Exception $e) {
					echo $e->getMessage();
					return;
				}
			}
		}

	} else {
		// Non-aggregated, no paging
		try {
			$data = fetchTelemetryChunk($token, $deviceId, $sensorKeys, $startTs, $now, $aggregation);
			$incoming = buildSchemaData($data, $schema, $aggregation);
			$incoming = expandSensorDataWithCalculations($incoming, $deviceInfo);
			$newDataPointsCount = count($incoming['data']);

			if ($telemetryData && isset($telemetryData['schema'])) {
				// We have cached data → merge (dedupes by timestamp and handles schema drift)
				$telemetryData = mergeTelemetryData($telemetryData, $incoming,);
			} else {
				// No cache → just use incoming
				$telemetryData = $incoming;
			}
		} catch (Exception $e) {
			echo $e->getMessage();
			return;
		}
	}

	if (
		!is_array($telemetryData) ||
		!isset($telemetryData['data']) ||
		!is_array($telemetryData['data'])
	) {
		$telemetryData = [
			'schema' => $schema ?? ['ts'],
			'data'   => []
		];
	}

	// Find earliest/latest timestamps
	$earliest = PHP_INT_MAX;
	$latest = 0;

	foreach ($telemetryData['data'] as $row) {
		$ts = $row[0];
		if ($ts < $earliest) $earliest = $ts;
		if ($ts > $latest) $latest = $ts;
	}

	$days = null;
	if ($earliest !== null && $latest !== null) {
		$days = round(($latest - $earliest) / (1000 * 60 * 60 * 24), 0) + 1;
	}

	$endTime = microtime(true);
	$queryTime = $endTime - $startTime;

	$data = [
		'name' => $deviceInfo['name'],
		'label' => $deviceInfo['label'],
		'id' => $deviceInfo['id'],
		'timestamp' => time(),
		'queryTime' => $queryTime,
		'newDataPoints' => $newDataPointsCount,
		'totalDataPoints' => count($telemetryData['data']),
		'earliestTimestamp' => $earliest,
		'latestTimestamp' => $latest,
		'earliestDate' => date('Y-m-d H:i:s', (int) ($earliest / 1000)),
		'latestDate' => date('Y-m-d H:i:s', (int) ($latest / 1000)),
		'days' => $days,
		'telemetry' => $telemetryData
	];

	return $data;

	// $json = json_encode($data, JSON_PRETTY_PRINT);
	// saveTelemetryCache($deviceId, $timerange, $aggregation, $json);
	// header('Content-Type: application/json');
	// echo $json;
}


// Normalize our timestamps to midnight to aggregate our data starting and ending at midnight for a full day 
function floorToLocalMidnightMs(int $tsMs, string $tzId = 'Europe/Berlin'): int
{
	$tz = new DateTimeZone($tzId);
	$dt = new DateTime('@' . intdiv($tsMs, 1000)); // interpret as UTC instant
	$dt->setTimezone($tz);                          // view in local time
	$dt->setTime(0, 0, 0);                          // snap to local midnight
	return $dt->getTimestamp() * 1000;              // back to UTC ms
}
function ceilToLocalMidnightPlusOneMinuteMs(int $tsMs, string $tzId = 'Europe/Berlin'): int
{
	$tz = new DateTimeZone($tzId);
	$dt = new DateTime('@' . intdiv($tsMs, 1000)); // interpret as UTC instant
	$dt->setTimezone($tz);                          // view in local time
	$dt->setTime(24,0,0);                          // snap to local midnight
	return $dt->getTimestamp() * 1000;              // back to UTC ms
}

// We need to format the data received from Thingsboard in a more compact way to reduce bandwidth.
function buildSchemaData(array $telemetryData, array $schema, $aggregation)
{	
	// Example Result:
	//
	// 	{	'schema': ['ts', 'Bodenfeuchte_10', 'Bodenfeuchte_30', 'Bodenfeuchte_60', 'Bodenfeuchte_80'],
	// 	 	'data': [
	//			[1738795844884, 4.39, 4.38, 4.36, 4.35], 
	//			[1738882244884, 4.40, 4.39, 4.35, 4.34],
	//			[1738882244884, 4.40, 4.39, 4.35, 4.34]
	//	] }
	//
	// Basic validation
	if (empty($schema) || $schema[0] !== 'ts') {
		throw new InvalidArgumentException('Schema must start with "ts" at index 0.');
	}

	// Keep only sensor names (everything after 'ts')
	$schemaSensors = array_values(array_diff($schema, ['ts']));

	$newData = [];

	// 1) Collect all telemetry by timestamp, filling only schema-listed sensors
	foreach ($telemetryData as $sensor => $entries) {
		if ($sensor === 'ts') {
			continue; // just in case
		}

		$isInSchema = in_array($sensor, $schemaSensors, true);

		foreach ($entries as $entry) {
			if (!isset($entry['ts'])) {
				continue; // skip malformed entry
			}

			if ($aggregation == '1d') { // if daily aggregation, floor all timestamps to 00:00
				$entry['ts'] = ceilToLocalMidnightPlusOneMinuteMs($entry['ts']);
			}

			$ts = (int)$entry['ts'];


			if (!isset($newData[$ts])) {
				$newData[$ts] = ['ts' => $ts];
			}

			if ($isInSchema) {
				$value = array_key_exists('value', $entry) ? formatValue($entry['value']) : null;
				$newData[$ts][$sensor] = $value;
			}
		}
	}

	// 2) Sort by timestamp
	ksort($newData, SORT_NUMERIC);

	// 3) Build rows aligned exactly to the provided schema
	$rows = [];
	foreach ($newData as $row) {
		$aligned = [];
		foreach ($schema as $field) {
			if ($field === 'ts') {
				$aligned[] = $row['ts'];
			} else {
				$aligned[] = array_key_exists($field, $row) ? $row[$field] : null;
			}
		}
		$rows[] = $aligned;
	}

	return [
		'schema' => array_values($schema), // preserve provided order
		'data'   => $rows
	];
}

// Merge existing telemetry data with new data. Case of different schemes has to be considered.
function mergeTelemetryData($existing, $incoming) {

	$existingSchema  = $existing['schema'] ?? [];
	$existingRows    = $existing['data'] ?? [];
	$incomingSchema = $incoming['schema'] ?? [];
	$incomingRows   = $incoming['data'] ?? [];

	// Case 1: schema is identical ---
	if ($existingSchema === $incomingSchema) {
		// Merge rows (keyed by timestamp to deduplicate)
		$merged = [];
		foreach ($existingRows as $row) {
			$merged[$row[0]] = $row; // [0] is always ts
		}
		foreach ($incomingRows as $row) {
			$merged[$row[0]] = $row;
		}
		ksort($merged, SORT_NUMERIC);

		return [
			'schema' => $existingSchema,
			'data'   => array_values($merged)
		];
	}

	// Case 2: schema differs → rebuild global schema ---
	$allFields = array_unique(array_merge($existingSchema, $incomingSchema));
	// Always keep ts first
	$allFields = array_diff($allFields, ['ts']);
	sort($allFields);
	$schema = array_merge(['ts'], $allFields);

	// Helper to realign any dataset
	$realign = function ($rows, $oldSchema, $schema) {
		$map = array_flip($oldSchema); // field → index
		$alignedRows = [];
		foreach ($rows as $row) {
			$aligned = [];
			foreach ($schema as $field) {
				if ($field === 'ts') {
					$aligned[] = $row[0]; // ts always first
				} elseif (isset($map[$field])) {
					$aligned[] = $row[$map[$field]] ?? null;
				} else {
					$aligned[] = null;
				}
			}
			$alignedRows[] = $aligned;
		}
		return $alignedRows;
	};

	$alignedCached  = $realign($existingRows, $existingSchema, $schema);
	$alignedIncoming = $realign($incomingRows, $incomingSchema, $schema);

	// Merge with deduplication
	$merged = [];
	foreach ($alignedCached as $row) {
		$merged[$row[0]] = $row;
	}
	foreach ($alignedIncoming as $row) {
		$merged[$row[0]] = $row;
	}
	ksort($merged, SORT_NUMERIC);

	return [
		'schema' => $schema,
		'data'   => array_values($merged)
	];
}

// Fetches the data from Thingsboard
function fetchTelemetryChunk($token, $deviceId, $sensorKeys, $startTs, $endTs, $aggregation)
{
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

// Get earliest Timestamp. We need to know from which date to start our timeseries query
function getEarliestTimestamp($token, $deviceId, $sensorKeys)
{
	$params = [
		'keys' => $sensorKeys,
		'startTs' => CUTOFF_DATE,
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


// Format numbers to two floating points only
function formatValue($value)
{
	if ($value === null || $value === '') return null;
	return round((float)$value, 2);
}
