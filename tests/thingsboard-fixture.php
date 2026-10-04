<?php

// Nur vom isolierten HTTP-Test verwendet.
header('Content-Type: application/json');
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . '/telemetry.json';
$entries = is_file($file) ? json_decode(file_get_contents($file), true) : [];
if (str_ends_with($path, '/timeseries/ANY') && $_SERVER['REQUEST_METHOD'] === 'POST') {
	if (is_file(__DIR__ . '/reject-save')) {
		http_response_code(502);
		echo '{}';
		exit;
	}
	$input = json_decode(file_get_contents('php://input'), true);
	$entries[(string) $input['ts']] = ['ts' => $input['ts'], 'value' => $input['values']['wasserkarte_post']];
	file_put_contents($file, json_encode($entries));
	if (is_file(__DIR__ . '/fail-after-save')) {
		unlink(__DIR__ . '/fail-after-save');
		http_response_code(502);
	}
	echo '{}';
} elseif (str_ends_with($path, '/timeseries/delete')) {
	if (is_file(__DIR__ . '/reject-delete')) {
		http_response_code(502);
		echo '{}';
		exit;
	}
	if (!is_file(__DIR__ . '/ignore-delete')) {
		foreach ($entries as $ts => $entry) if ($ts >= (int) $_GET['startTs'] && $ts < (int) $_GET['endTs']) unset($entries[$ts]);
	}
	file_put_contents($file, json_encode($entries));
	if (is_file(__DIR__ . '/fail-after-delete')) {
		unlink(__DIR__ . '/fail-after-delete');
		http_response_code(502);
	}
	echo '{}';
} elseif (str_ends_with($path, '/values/timeseries')) {
	$filtered = array_filter($entries, static fn ($entry) => $entry['ts'] >= (int) ($_GET['startTs'] ?? 0) && $entry['ts'] < (int) ($_GET['endTs'] ?? PHP_INT_MAX));
	echo json_encode(['wasserkarte_post' => array_values($filtered)]);
} elseif (str_contains($path, '/USER/') && str_ends_with($path, '/values/attributes/SERVER_SCOPE')) {
	echo json_encode([['key' => 'wasserkarte_role', 'value' => 'super_wassermeister']]);

} elseif (preg_match('#/user/([a-f0-9-]{36})$#i', $path, $matches)) {
	$users = [
		'55555555-5555-4555-8555-555555555555' => ['firstName' => 'Aktueller', 'lastName' => 'Admin', 'email' => 'test@example.invalid'],
		'12121212-1212-4212-8212-121212121212' => ['firstName' => 'owner', 'lastName' => 'User', 'email' => 'owner@example.invalid'],
		'13131313-1313-4313-8313-131313131313' => ['firstName' => 'other', 'lastName' => 'User', 'email' => 'other@example.invalid'],
	];
	echo json_encode($users[strtolower($matches[1])] ?? []);
} else {
	echo '[]';
}
