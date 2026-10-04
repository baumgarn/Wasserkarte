<?php

// Gemeinsamer Einstieg für die nächtlichen Cache-Aufgaben (nur CLI/Cron).
if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit('Daily tasks are CLI-only.');
}

require_once __DIR__ . '/config.php';

function dailyLog(string $message): void
{
	echo date('Y-m-d H:i:s') . ' – ' . $message . PHP_EOL;
}

if (!is_dir(CACHE_DIR) && !mkdir(CACHE_DIR, 0755, true) && !is_dir(CACHE_DIR)) {
	fwrite(STDERR, "Cache-Verzeichnis konnte nicht angelegt werden.\n");
	exit(1);
}

$dailyLock = fopen(CACHE_DIR . '/daily.lock', 'c');
if ($dailyLock === false) {
	fwrite(STDERR, "Daily-Lock konnte nicht geöffnet werden.\n");
	exit(1);
}
if (!flock($dailyLock, LOCK_EX | LOCK_NB)) {
	dailyLog('Skipped: tägliche Aufgaben laufen bereits.');
	fclose($dailyLock);
	exit(0);
}

$dailyExitCode = 0;
try {
	dailyLog('Tägliche Aufgaben gestartet: Tagesmittelwerte.');
	require __DIR__ . '/telemetry/dailyaverages.php';

	// Posts neu laden.
	set_time_limit(0);
	require_once __DIR__ . '/posts/service.php';
	dailyLog('Posts-Cache wird neu aufgebaut.');
	$dailyAuthorization = getThingsBoardAuthorization();
	if (!is_string($dailyAuthorization) || $dailyAuthorization === '') {
		throw new RuntimeException('ThingsBoard-Authentifizierung fehlgeschlagen.');
	}
	$dailyDevices = getCache();
	if (!is_array($dailyDevices['devices'] ?? null)) {
		throw new RuntimeException('Geräte-Cache fehlt; Posts-Cache bleibt unverändert.');
	}
	$dailyPosts = postsWithLock(static function () use ($dailyAuthorization): array {
		$posts = postsRebuildCache($dailyAuthorization);
		require_once __DIR__ . '/media/cleanup.php';
		dailyLog('Nicht verwendete Medien bereinigt (' . mediaCleanup($posts['posts']) . ').');
		return $posts;
	});
	dailyLog('Posts-Cache aktualisiert (' . count($dailyPosts['posts']) . ' Posts).');
	dailyLog('Tägliche Aufgaben abgeschlossen.');
} catch (Throwable $dailyError) {
	fwrite(STDERR, date('Y-m-d H:i:s') . ' – Daily fehlgeschlagen: ' . $dailyError->getMessage() . PHP_EOL);
	$dailyExitCode = 1;
} finally {
	flock($dailyLock, LOCK_UN);
	fclose($dailyLock);
}

exit($dailyExitCode);
