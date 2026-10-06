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
			header('Content-Type: ' . (($item['type'] ?? 'image') === 'video' && $variant === 'display' ? 'video/mp4' : 'image/webp'));
			header('X-Content-Type-Options: nosniff');
			// Auch nach Entfernen eines Bildes soll keine langlebige öffentliche Kopie bleiben.
			header($published ? 'Cache-Control: private, no-cache' : 'Cache-Control: private, no-store');
			$etag = '"' . $id . '-' . $variant . '"';
			header('ETag: ' . $etag);
			if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
				http_response_code(304);
				exit;
			}
			$size = filesize($file);
			$start = 0;
			$end = $size - 1;
			header('Accept-Ranges: bytes');
			$range = $_SERVER['HTTP_RANGE'] ?? '';
			if ($range !== '' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && (!isset($_SERVER['HTTP_IF_RANGE']) || $_SERVER['HTTP_IF_RANGE'] === $etag)) {
				$valid = preg_match('/^bytes=(\d*)-(\d*)$/D', $range, $match) && ($match[1] !== '' || $match[2] !== '');
				if ($valid) {
					$start = $match[1] !== '' ? (int) $match[1] : max(0, $size - (int) $match[2]);
					$end = $match[1] !== '' && $match[2] !== '' ? min($end, (int) $match[2]) : $end;
					$valid = $start <= $end && $start < $size && !($match[1] === '' && (int) $match[2] === 0);
				}
				if (!$valid) {
					http_response_code(416);
					header('Content-Range: bytes */' . $size);
					header('Content-Length: 0');
					exit;
				}
				http_response_code(206);
				header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
			}
			header('Content-Length: ' . ($end - $start + 1));
			if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
				$handle = fopen($file, 'rb');
				fseek($handle, $start);
				$remaining = $end - $start + 1;
				while ($remaining > 0 && !feof($handle) && !connection_aborted()) {
					$chunk = fread($handle, min(65536, $remaining));
					if ($chunk === false || $chunk === '') break;
					echo $chunk;
					$remaining -= strlen($chunk);
				}
				fclose($handle);
			}
			exit;
		}
	}
	postsRespond(['error' => 'Medium wurde nicht gefunden.'], 404, true);
} catch (Throwable $error) {
	error_log('Media file: ' . $error->getMessage());
	postsRespond(['error' => 'Medium konnte nicht geladen werden.'], 503, true);
}
