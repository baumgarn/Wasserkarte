<?php

require_once __DIR__ . '/service.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') {
	$cache = postsReadCache();
	if ($cache === null) {
		try {
			$cache = postsRebuildCache(postsServiceToken());
		} catch (Throwable $error) {
			postsRespond(['error' => 'Posts konnten nicht geladen werden.'], 502);
		}
	}
	postsRespond($cache);
}

if (!in_array($method, ['POST', 'PATCH', 'DELETE'], true)) {
	postsRespond(['error' => 'Methode nicht unterstützt.'], 405, true);
}

$identity = postsRequireIdentity();
postsRequireCsrf();
$input = postsReadJsonBody();
if ($input === null) {
	postsRespond(['error' => 'Ungültige Anfrage.'], 400, true);
}
$token = postsServiceToken();
session_write_close();

try {
	postsWithLock(static function () use ($method, $identity, $input, $token): void {

		if ($method === 'POST') {
			$deviceId = $input['deviceId'] ?? null;
			$content = postsContent($input['content'] ?? null);
			$mediaIds = mediaIds($input['mediaIds'] ?? []);
			$timestamp = postsTimestamp($input['timestamp'] ?? (int) floor(microtime(true) * 1000));
			postsRequireLocation(is_string($deviceId) ? $deviceId : '');
			if ($content === null || $timestamp === null || $mediaIds === null || ($content === '' && $mediaIds === [])) {
				postsRespond(['error' => 'Bitte Text oder Medien sowie einen gültigen Zeitpunkt angeben (bis zu 10 Fotos oder ein Video).'], 400, true);
			}
			if (!postsCanWriteDevice($identity, $deviceId, $token)) {
				postsRespond(['error' => 'Du darfst für diesen Standort keine Einträge erstellen.'], 403, true);
			}
			$postId = $input['id'] ?? postsUuid();
			if (!mediaValidId($postId)) postsRespond(['error' => 'Ungültige Post-ID.'], 400, true);
			$cache = postsReadCache() ?? postsRebuildCache($token);
			$remote = isset($input['id']) ? postsFindOnDevice($postId, $deviceId, $token) : null;
			foreach ($remote !== null ? [$remote, ...$cache['posts']] : $cache['posts'] as $existing) {
				if ($existing['id'] !== $postId) continue;
				// Gleicher Entwurf nach verlorener HTTP-Antwort: bereits gespeicherten Post zurückgeben.
				if (postsWritableInEnvironment($existing) && $existing['authorUserId'] === $identity['id']
					&& $existing['deviceId'] === $deviceId && $existing['content'] === $content
					&& (int) $existing['timestamp'] === $timestamp && ($existing['mediaIds'] ?? []) === $mediaIds) {
					postsUpsertCache($existing, $token);
					postsRespond(['post' => $existing], 200, true);
				}
				postsRespond(['error' => 'Dieser Post wurde bereits gespeichert. Bitte die Einträge neu laden.'], 409, true);
			}
			$now = postsNextCreatedAt($deviceId);
			$post = [
				'id' => $postId,
				'deviceId' => $deviceId,
				'timestamp' => $timestamp,
				'createdAt' => $now,
				'updatedAt' => $now,
				'authorUserId' => $identity['id'],
				'authorName' => trim(($identity['firstName'] ?? '') . ' ' . ($identity['lastName'] ?? '')) ?: ($identity['email'] ?? ''),
				'content' => $content,
				'mediaIds' => $mediaIds,
			];
			if (postsLocalMode()) {
				$post['environment'] = 'local';
			}
			postsRespond(postsSaveWithMedia($post, $identity, $token), 201, true);
		}

		$postId = is_string($input['id'] ?? null) ? $input['id'] : '';
		if (!preg_match('/^[a-f0-9-]{36}$/i', $postId)) {
			postsRespond(['error' => 'Ungültige Post-ID.'], 400, true);
		}
		try {
			$post = postsFind($postId, $token);
		} catch (Throwable $error) {
			postsRespond(['error' => 'Post konnte nicht geladen werden.'], 502, true);
		}
		if ($post === null) {
			postsRespond(['error' => 'Post wurde nicht gefunden.'], 404, true);
		}
		if (!postsCanEdit($identity, $post, $token)) {
			postsRespond(['error' => 'Du darfst diesen Eintrag nicht bearbeiten.'], 403, true);
		}

		if ($method === 'PATCH') {
			$content = postsContent($input['content'] ?? null);
			$mediaIds = mediaIds($input['mediaIds'] ?? ($post['mediaIds'] ?? []));
			$timestamp = postsTimestamp($input['timestamp'] ?? null);
			if ($content === null || $timestamp === null || $mediaIds === null || ($content === '' && $mediaIds === [])) {
				postsRespond(['error' => 'Bitte Text oder Medien sowie einen gültigen Zeitpunkt angeben (bis zu 10 Fotos oder ein Video).'], 400, true);
			}
			$previous = $post;
			$post['content'] = $content;
			$post['mediaIds'] = $mediaIds;
			$post['timestamp'] = $timestamp;
			$post['updatedAt'] = (int) floor(microtime(true) * 1000);
			postsRespond(postsSaveWithMedia($post, $identity, $token, $previous), 200, true);
		}

		if ($method === 'DELETE') {
			if (!postsDeleteTelemetry($post, $token)) {
				postsRespond(['error' => 'Eintrag konnte nicht gelöscht werden.'], 502, true);
			}
			try {
				postsRemoveFromCache($postId, $token);
			} catch (Throwable $error) {
				error_log('Deleted post; cache update failed: ' . $error->getMessage());
				@unlink(postsCacheFile());
			}
			try {
				mediaWithLock(postsLocalMode(), static function () use ($postId): void {
					$index = mediaReadIndex(postsLocalMode());
					$ids = array_column(array_filter($index['media'], static fn ($item) => ($item['postId'] ?? '') === $postId), 'id');
					mediaRemoveEntries(postsLocalMode(), $index, $ids);
				});
			} catch (Throwable $error) {
				error_log('Deleted post media will be retried by daily cleanup: ' . $error->getMessage());
			}
			postsRespond(['success' => true], 200, true);
		}
	});
} catch (InvalidArgumentException $error) {
	postsRespond(['error' => $error->getMessage()], 400, true);
} catch (Throwable $error) {
	error_log('Posts API: ' . $error->getMessage());
	postsRespond(['error' => 'Eintrag konnte nicht gespeichert werden. Bitte erneut versuchen.'], 502, true);
}
