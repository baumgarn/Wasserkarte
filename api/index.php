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

requireRefreshSecretIfNeeded();

// ENTRY POINT

function respondJson(array $payload, int $statusCode = 200): void {
	http_response_code($statusCode);
	header('Content-Type: application/json');
	echo json_encode($payload, JSON_PRETTY_PRINT);
}

function isValidTelemetryRequest(string $deviceId, string $timerange, string $aggregation): bool {
	$allowedTimeranges = ['all', 'day', 'week', 'month', 'year'];
	$allowedAggregations = ['1d', 'raw'];

	if (!preg_match('/^[a-zA-Z0-9-]+$/', $deviceId)) {
		return false;
	}

	if (!in_array($timerange, $allowedTimeranges, true)) {
		return false;
	}

	if (!in_array($aggregation, $allowedAggregations, true)) {
		return false;
	}

	return true;
}

function entry() {
	// Telemetry
	if (isset($_GET['deviceId'])) {
		$deviceId = $_GET['deviceId'];
		$timerange = $_GET['time'] ?? 'day';
		$aggregation = $_GET['agg'] ?? '1d';

		if (!isValidTelemetryRequest($deviceId, $timerange, $aggregation)) {
			respondJson(['error' => 'Invalid telemetry request parameters'], 400);
			return;
		}

		$token = getAuthToken();
		if ($token) {
			$data = getTimeseriesForDevice($token, $deviceId, null, $timerange, $aggregation);
			if (!is_array($data) || !isset($data['telemetry']) || !is_array($data['telemetry'])) {
				respondJson(['error' => 'Invalid telemetry response'], 502);
				return;
			}

			$json = json_encode($data, JSON_PRETTY_PRINT);
			if ($json === false) {
				respondJson(['error' => 'Failed to encode telemetry response'], 500);
				return;
			}

			saveTelemetryCache($deviceId, $timerange, $aggregation, $json);
			respondJson($data);
		} else {
			respondJson(['error' => 'Unable to authenticate'], 502);
		}
		return;
	}

	// Devices
	respondJson(getThingsBoardDevices());

}

/////

entry();

/////
