<?php

require_once __DIR__ . '/../posts/service.php';

$id = $_GET['id'] ?? '';
$variant = $_GET['variant'] ?? 'display';
if (!mediaValidId($id) || !in_array($variant, ['display', 'thumbnail'], true)) {
	postsRespond(['error' => 'Ungültiges Medium.'], 400, true);
}
if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
	postsRespond(['error' => 'Methode nicht unterstützt.'], 405, true);
}
try {
	$cache = postsReadCache() ?? postsRebuildCache(postsServiceToken());
	foreach (postsLocalMode() ? [false, true] : [false] as $local) {
		foreach (mediaReadIndex($local)['media'] as $item) {
			if (($item['id'] ?? null) !== $id || !mediaInEnvironment($item, $local)) continue;
			$published = mediaReferenced($item, $cache['posts']);
			// In der privaten Übersicht dürfen berechtigte Nutzer:innen auch unbenutzte
			// Uploads ansehen. Anonyme Zugriffe erhalten dafür weiterhin 404.
			if (!$published && !mediaCanView(getManagementIdentity(), $item)) continue;
			if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
			$file = mediaFilePath($item, $variant, $local);
			if (!is_file($file)) break;
			header('Content-Type: image/webp');
			header('X-Content-Type-Options: nosniff');
			// Auch nach Entfernen eines Bildes soll keine langlebige öffentliche Kopie bleiben.
			header($published ? 'Cache-Control: private, no-cache' : 'Cache-Control: private, no-store');
			$etag = '"' . $id . '-' . $variant . '"';
			header('ETag: ' . $etag);
			if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
				http_response_code(304);
				exit;
			}
			header('Content-Length: ' . filesize($file));
			if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') readfile($file);
			exit;
		}
	}
	postsRespond(['error' => 'Bild wurde nicht gefunden.'], 404, true);
} catch (Throwable $error) {
	error_log('Media file: ' . $error->getMessage());
	postsRespond(['error' => 'Bild konnte nicht geladen werden.'], 503, true);
}
