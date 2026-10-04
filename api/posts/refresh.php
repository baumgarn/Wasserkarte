<?php

require_once __DIR__ . '/service.php';

if (PHP_SAPI !== 'cli') {
	// Dieser Einstieg ist immer ein Refresh, auch ohne refresh-Queryparameter.
	$_GET['refresh'] = true;
	requireRefreshSecretIfNeeded();
	header('Content-Type: application/json; charset=utf-8');
	header('Cache-Control: no-store');
}

try {
	$authorization = getThingsBoardAuthorization();
	if (!is_string($authorization) || $authorization === '') {
		throw new RuntimeException('ThingsBoard-Authentifizierung fehlgeschlagen.');
	}
	$devices = getCache();
	if (!is_array($devices['devices'] ?? null)) {
		throw new RuntimeException('Geräte-Cache fehlt; Posts-Cache bleibt unverändert.');
	}
	$cache = postsRebuildCache($authorization);
	echo json_encode([
		'success' => true,
		'postCount' => count($cache['posts']),
		'cacheTimestamp' => $cache['cacheTimestamp'],
	], JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $error) {
	if (PHP_SAPI === 'cli') {
		fwrite(STDERR, 'Posts-Refresh fehlgeschlagen: ' . $error->getMessage() . PHP_EOL);
		exit(1);
	}
	http_response_code(502);
	echo json_encode(['error' => 'Posts-Cache konnte nicht neu aufgebaut werden.'], JSON_UNESCAPED_UNICODE);
}
