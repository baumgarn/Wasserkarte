<?php

require_once __DIR__ . '/../posts/service.php';
require_once __DIR__ . '/videos.php';

header('Cache-Control: no-store');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
try {
	if ($method === 'GET') {
		$cache = postsReadCache() ?? postsRebuildCache(postsServiceToken());
		$items = [];
		foreach (postsLocalMode() ? [false, true] : [false] as $local) {
			foreach (mediaReadIndex($local)['media'] as $item) {
				if (is_array($item) && mediaInEnvironment($item, $local) && mediaValidId($item['id'] ?? null)
					&& mediaReferenced($item, $cache['posts'])) $items[] = mediaPublicItem($item);
			}
		}
		postsRespond(['media' => $items, 'localMode' => postsLocalMode(), 'maxPerPost' => MEDIA_MAX_PER_POST, 'maxBytes' => MEDIA_MAX_BYTES], 200, true);
	}
	if ($method !== 'POST') postsRespond(['error' => 'Methode nicht unterstützt.'], 405, true);
	$identity = postsRequireIdentity();
	postsRequireCsrf();
	$deviceId = $_POST['deviceId'] ?? '';
	$postId = $_POST['postId'] ?? '';
	if (!mediaValidId($deviceId) || !mediaValidId($postId)) {
		postsRespond(['error' => 'Ungültiger Standort oder Post.'], 400, true);
	}
	postsRequireLocation($deviceId);
	$token = postsServiceToken();
	$cache = postsReadCache() ?? postsRebuildCache($token);
	$existing = null;
	foreach ($cache['posts'] as $post) {
		if ($post['id'] === $postId) $existing = $post;
	}
	if ($existing !== null) {
		if ($existing['deviceId'] !== $deviceId || !postsCanEdit($identity, $existing, $token)) {
			postsRespond(['error' => 'Du darfst diesem Post keine Medien hinzufügen.'], 403, true);
		}
	} elseif (!postsCanWriteDevice($identity, $deviceId, $token)) {
		postsRespond(['error' => 'Du darfst für diesen Standort keine Medien hochladen.'], 403, true);
	}
	$file = $_FILES['file'] ?? null;
	if (!is_array($file) || !is_int($file['error'] ?? null) || $file['error'] !== UPLOAD_ERR_OK
		|| !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) {
		postsRespond(['error' => 'Upload fehlgeschlagen. Bitte Dateigröße und Server-Uploadlimit prüfen.'], 400, true);
	}
	$isVideo = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) === 'video/mp4';
	if ($isVideo && !mediaCanViewAll($identity)) postsRespond(['error' => 'Video-Uploads sind Super-Wassermeister*innen und Admins vorbehalten.'], 403, true);
	$poster = $_FILES['poster'] ?? null;
	if ($isVideo && (!is_array($poster) || ($poster['error'] ?? -1) !== UPLOAD_ERR_OK || !is_uploaded_file($poster['tmp_name'] ?? ''))) postsRespond(['error' => 'Das Video benötigt ein Vorschaubild.'], 400, true);
	// Keine offene PHP-Session während der Bildverarbeitung halten.
	session_write_close();
	$item = mediaWithLock(postsLocalMode(), static function () use ($identity, $postId, $deviceId, $file, $isVideo, $poster): array {
		$index = mediaReadIndex(postsLocalMode());
		mediaPruneDiscardedDrafts($index);
		if (mediaDraftWasDiscarded($index, $postId, $deviceId, $identity['id'])) {
			throw new RuntimeException('Der Entwurf wurde bereits verworfen.');
		}
		$attachments = array_values(array_filter($index['media'], static fn ($item) => ($item['postId'] ?? '') === $postId && mediaInEnvironment($item, postsLocalMode())));
		if (count($attachments) >= MEDIA_MAX_PER_POST || ($attachments !== [] && ($isVideo || in_array('video', array_column($attachments, 'type'), true)))) throw new InvalidArgumentException('Bitte entweder bis zu 10 Fotos oder ein einzelnes Video auswählen.');
		$storedBytes = 0;
		$recentUploads = 0;
		foreach ($index['media'] as $item) {
			if (($item['authorUserId'] ?? '') !== $identity['id']) continue;
			if ((int) ($item['uploadedAt'] ?? 0) > (time() - 3600) * 1000) $recentUploads++;
			$storedBytes += (int) ($item['variants']['display']['bytes'] ?? 0) + (int) ($item['variants']['thumbnail']['bytes'] ?? 0);
		}
		if ($recentUploads >= 40 || $storedBytes >= 200 * 1024 * 1024 || $storedBytes + ($isVideo ? (int) filesize($file['tmp_name']) : 0) > 200 * 1024 * 1024) {
			throw new OverflowException('Uploadlimit erreicht. Bitte später erneut versuchen oder nicht benötigte Posts entfernen.');
		}
		$id = postsUuid();
		$duration = $isVideo ? mediaVideoInfo($file['tmp_name'])['duration'] : null;
		$variants = $isVideo ? mediaProcessVideo($file['tmp_name'], $poster['tmp_name'], $id, postsLocalMode()) : mediaProcessImage($file['tmp_name'], $id, postsLocalMode());
		$item = [
			'id' => $id, 'type' => $isVideo ? 'video' : 'image', 'postId' => $postId, 'deviceId' => $deviceId,
			'authorUserId' => $identity['id'],
			'environment' => postsLocalMode() ? 'local' : 'production',
			'duration' => $duration,
			'uploadedAt' => (int) floor(microtime(true) * 1000), 'variants' => $variants,
		];
		$index['media'][] = $item;
		try {
			mediaWriteIndex(postsLocalMode(), $index);
		} catch (Throwable $error) {
			foreach (['display', 'thumbnail'] as $variant) @unlink(mediaFilePath($item, $variant, postsLocalMode()));
			throw $error;
		}
		return $item;
	});
	postsRespond(['media' => mediaPublicItem($item)], 201, true);
} catch (InvalidArgumentException $error) {
	postsRespond(['error' => $error->getMessage()], 400, true);
} catch (OverflowException $error) {
	postsRespond(['error' => $error->getMessage()], 429, true);
} catch (Throwable $error) {
	error_log('Media API: ' . $error->getMessage());
	postsRespond(['error' => 'Medien konnten nicht verarbeitet werden. Bitte Serverkonfiguration und Speicher prüfen.'], 503, true);
}
