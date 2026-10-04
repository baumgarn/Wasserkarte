<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../telemetry/cache.php';
require_once __DIR__ . '/../management/session.php';
require_once __DIR__ . '/../management/thingsboard-client.php';
require_once __DIR__ . '/cache.php';
require_once __DIR__ . '/../media/storage.php';

const POSTS_TELEMETRY_KEY = 'wasserkarte_post';
const POSTS_MAX_CONTENT_LENGTH = 5000;

function postsRespond(array $payload, int $status = 200, bool $private = false): void
{
	http_response_code($status);
	header('Content-Type: application/json; charset=utf-8');
	header($private ? 'Cache-Control: no-store' : 'Cache-Control: public, max-age=60');
	echo json_encode($payload, JSON_UNESCAPED_UNICODE);
	exit;
}

function postsReadJsonBody(): ?array
{
	$body = file_get_contents('php://input');
	if ($body === false || strlen($body) > 16384) {
		return null;
	}
	$value = json_decode($body, true);
	return is_array($value) ? $value : null;
}

function postsValidId($id): bool
{
	return is_string($id) && preg_match('/^[a-f0-9-]{36}$/i', $id) === 1;
}

function postsServiceToken(): string
{
	$token = getThingsBoardAuthorization();
	if (!is_string($token) || $token === '') {
		postsRespond(['error' => 'ThingsBoard ist momentan nicht erreichbar.'], 502, true);
	}
	return $token;
}

function postsRequireIdentity(): array
{
	$identity = getManagementIdentity();
	if ($identity === null) {
		postsRespond(['error' => 'Bitte zuerst anmelden.'], 401, true);
	}
	return $identity;
}

function postsRequireCsrf(): void
{
	if (!hasValidManagementCsrfToken()) {
		postsRespond(['error' => 'Die Sitzung muss erneut geladen werden.'], 403, true);
	}
}

function postsUserPermissions(string $userId, string $token): array
{
	$response = thingsBoardManagementRequest(
		'GET',
		'/plugins/telemetry/USER/' . rawurlencode($userId) . '/values/attributes/SERVER_SCOPE?keys=wasserkarte_role,wasserkarte_locations',
		null,
		$token
	);
	if ($response['status'] !== 200 || !is_array($response['body'])) {
		return ['role' => 'none', 'locations' => []];
	}

	$role = 'none';
	$locations = [];
	foreach ($response['body'] as $attribute) {
		if (!is_array($attribute)) {
			continue;
		}
		if (($attribute['key'] ?? '') === 'wasserkarte_role') {
			$value = $attribute['value'] ?? null;
			$role = in_array($value, ['wassermeister', 'super_wassermeister'], true) ? $value : 'none';
		}
		if (($attribute['key'] ?? '') === 'wasserkarte_locations') {
			$value = $attribute['value'] ?? [];
			if (is_string($value)) {
				$value = json_decode($value, true);
			}
			if (is_array($value)) {
				$locations = array_values(array_filter($value, 'postsValidId'));
			}
		}
	}

	return ['role' => $role, 'locations' => $locations];
}

function postsCanWriteDevice(array $identity, string $deviceId, string $token): bool
{
	$authority = $identity['thingsboardAuthority'] ?? '';
	if ($authority === 'TENANT_ADMIN' || $authority === 'SYS_ADMIN') {
		return true;
	}
	$permissions = postsUserPermissions((string) $identity['id'], $token);
	return $permissions['role'] === 'super_wassermeister'
		|| ($permissions['role'] === 'wassermeister' && in_array($deviceId, $permissions['locations'], true));
}

function postsCanEdit(array $identity, array $post, string $token): bool
{
	if (!postsWritableInEnvironment($post)) {
		return false;
	}
	$authority = $identity['thingsboardAuthority'] ?? '';
	if ($authority === 'TENANT_ADMIN' || $authority === 'SYS_ADMIN') {
		return true;
	}
	$permissions = postsUserPermissions((string) $identity['id'], $token);
	if ($permissions['role'] === 'super_wassermeister') {
		return true;
	}
	return ($post['authorUserId'] ?? null) === ($identity['id'] ?? null);
}

function postsDeviceIds(): array
{
	$deviceCache = getCache();
	$devices = is_array($deviceCache['devices'] ?? null) ? $deviceCache['devices'] : [];
	$ids = [];
	foreach ($devices as $device) {
		$id = is_array($device) ? ($device['id'] ?? null) : null;
		if (postsValidId($id)) {
			$ids[] = $id;
		}
	}
	return array_values(array_unique($ids));
}

function postsRequireLocation(string $deviceId): array
{
	if (!postsValidId($deviceId)) {
		postsRespond(['error' => 'Ungültiger Standort.'], 400, true);
	}
	$device = getCachedDeviceInfo($deviceId);
	if ($device === null) {
		postsRespond(['error' => 'Standort wurde nicht gefunden.'], 404, true);
	}
	return $device;
}

function postsNormalise($value, string $deviceId, int $storageTs): ?array
{
	if (is_string($value)) {
		$value = json_decode($value, true);
	}
	if (!is_array($value) || !is_string($value['id'] ?? null) || !is_string($value['content'] ?? null)) {
		return null;
	}
	$timestamp = isset($value['timestamp']) ? (int) $value['timestamp'] : $storageTs;
	$createdAt = isset($value['createdAt']) ? (int) $value['createdAt'] : $storageTs;
	$authorUserId = $value['authorUserId'] ?? null;
	if ($timestamp <= 0 || $createdAt <= 0 || !postsValidId($authorUserId)) {
		return null;
	}

	$post = [
		'id' => $value['id'],
		'deviceId' => $deviceId,
		'timestamp' => $timestamp,
		'createdAt' => $createdAt,
		'updatedAt' => isset($value['updatedAt']) ? (int) $value['updatedAt'] : $createdAt,
		'authorUserId' => $authorUserId,
		'authorName' => is_string($value['authorName'] ?? null) ? $value['authorName'] : '',
		'content' => $value['content'],
		'mediaIds' => mediaIds($value['mediaIds'] ?? []) ?? [],
	];
	if (isset($value['environment'])) {
		$post['environment'] = $value['environment'];
	}
	return $post;
}

function postsRebuildCache(string $token): array
{
	return postsWithLock(static fn () => postsRebuildCacheUnlocked($token));
}

function postsRebuildCacheUnlocked(string $token): array
{
	$posts = [];
	$endTs = (int) floor(microtime(true) * 1000) + 1;
	foreach (postsDeviceIds() as $deviceId) {
		$response = thingsBoardManagementRequest(
			'GET',
			'/plugins/telemetry/DEVICE/' . rawurlencode($deviceId) . '/values/timeseries?' . http_build_query([
				'keys' => POSTS_TELEMETRY_KEY,
				'startTs' => 0,
				'endTs' => $endTs,
				'limit' => 100000,
				'orderBy' => 'ASC',
				'agg' => 'NONE',
			]),
			null,
			$token
		);
		if ($response['status'] !== 200 || !is_array($response['body'])) {
			throw new RuntimeException('Posts konnten nicht von ThingsBoard geladen werden.');
		}
		foreach (($response['body'][POSTS_TELEMETRY_KEY] ?? []) as $entry) {
			$post = is_array($entry) ? postsNormalise($entry['value'] ?? null, $deviceId, (int) ($entry['ts'] ?? 0)) : null;
			if ($post !== null) {
				$posts[] = $post;
			}
		}
	}
	return postsWriteCache($posts);
}

function postsFind(string $postId, string $token): ?array
{
	$cache = postsReadCache();
	foreach (($cache['posts'] ?? []) as $post) {
		if (is_array($post) && ($post['id'] ?? '') === $postId) {
			return $post;
		}
	}
	$cache = postsRebuildCache($token);
	foreach ($cache['posts'] as $post) {
		if (($post['id'] ?? '') === $postId) {
			return $post;
		}
	}
	return null;
}

function postsFindOnDevice(string $postId, string $deviceId, string $token): ?array
{
	// Vor dem Anlegen auch ThingsBoard prüfen: Die vorherige HTTP-Antwort könnte
	// verloren gegangen sein, obwohl der Telemetrieeintrag schon gespeichert wurde.
	$response = thingsBoardManagementRequest('GET',
		'/plugins/telemetry/DEVICE/' . rawurlencode($deviceId) . '/values/timeseries?' . http_build_query([
			'keys' => POSTS_TELEMETRY_KEY, 'startTs' => 0,
			'endTs' => (int) floor(microtime(true) * 1000) + 1,
			'limit' => 100000, 'orderBy' => 'ASC', 'agg' => 'NONE',
		]), null, $token);
	if ($response['status'] !== 200 || !is_array($response['body'])) {
		throw new RuntimeException('Post-ID konnte nicht in ThingsBoard geprüft werden.');
	}
	foreach ($response['body'][POSTS_TELEMETRY_KEY] ?? [] as $entry) {
		$post = postsNormalise($entry['value'] ?? null, $deviceId, (int) ($entry['ts'] ?? 0));
		if ($post !== null && $post['id'] === $postId) return $post;
	}
	return null;
}

function postsUpsertCache(array $post, string $token): void
{
	$cache = postsReadCache();
	if ($cache === null) {
		postsRebuildCache($token);
		$cache = postsReadCache();
	}
	$posts = [];
	foreach (($cache['posts'] ?? []) as $cachedPost) {
		if (is_array($cachedPost) && ($cachedPost['id'] ?? null) !== $post['id']) {
			$posts[] = $cachedPost;
		}
	}
	$posts[] = $post;
	postsWriteCache($posts);
}

function postsRemoveFromCache(string $postId, string $token): void
{
	$cache = postsReadCache();
	if ($cache === null) {
		postsRebuildCache($token);
		$cache = postsReadCache();
	}
	$posts = array_values(array_filter(
		$cache['posts'] ?? [],
		static fn ($cachedPost) => !is_array($cachedPost) || ($cachedPost['id'] ?? null) !== $postId
	));
	postsWriteCache($posts);
}

function postsSaveTelemetry(array $post, string $token): bool
{
	$response = thingsBoardManagementRequest(
		'POST',
		'/plugins/telemetry/DEVICE/' . rawurlencode($post['deviceId']) . '/timeseries/ANY',
		['ts' => $post['createdAt'], 'values' => [POSTS_TELEMETRY_KEY => json_encode($post, JSON_UNESCAPED_UNICODE)]],
		$token
	);
	return $response['status'] === 200;
}

function postsDeleteTelemetry(array $post, string $token): bool
{
	$storageTs = (int) $post['createdAt'];
	$path = '/plugins/telemetry/DEVICE/' . rawurlencode($post['deviceId']);
	$response = thingsBoardManagementRequest(
		'DELETE',
		$path . '/timeseries/delete?' . http_build_query([
			'keys' => POSTS_TELEMETRY_KEY,
			// ThingsBoard benötigt für einen einzelnen Punkt [ts, ts + 1).
			'startTs' => $storageTs,
			'endTs' => $storageTs + 1,
			'deleteAllDataForKeys' => 'false',
			'deleteLatest' => 'true',
			'rewriteLatestIfDeleted' => 'true',
		]),
		null,
		$token
	);
	if (!in_array($response['status'], [200, 204], true)) {
		return false;
	}

	// HTTP 200 allein reicht nicht: Ein leerer Löschbereich kann auch 200 liefern.
	$check = thingsBoardManagementRequest(
		'GET',
		$path . '/values/timeseries?' . http_build_query([
			'keys' => POSTS_TELEMETRY_KEY,
			'startTs' => $storageTs - 1,
			'endTs' => $storageTs + 1,
			'agg' => 'NONE',
			'limit' => 3,
		]),
		null,
		$token
	);
	if ($check['status'] !== 200 || !is_array($check['body'])) {
		return false;
	}
	$entries = $check['body'][POSTS_TELEMETRY_KEY] ?? [];
	if (!is_array($entries)) {
		return false;
	}
	foreach ($entries as $entry) {
		if (!is_array($entry) || !isset($entry['ts']) || (int) $entry['ts'] === $storageTs) {
			return false;
		}
	}
	return true;
}

function postsUuid(): string
{
	$bytes = random_bytes(16);
	$bytes[6] = chr((ord($bytes[6]) & 15) | 64);
	$bytes[8] = chr((ord($bytes[8]) & 63) | 128);
	$hex = bin2hex($bytes);
	return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
}

function postsNextCreatedAt(string $deviceId): int
{
	$createdAt = (int) floor(microtime(true) * 1000);
	foreach ((postsReadCache()['posts'] ?? []) as $post) {
		if (($post['deviceId'] ?? null) === $deviceId && isset($post['createdAt'])) {
			$createdAt = max($createdAt, (int) $post['createdAt'] + 1);
		}
	}
	return $createdAt;
}

function postsTimestamp($value): ?int
{
	if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
		return null;
	}
	$timestamp = (int) $value;
	return $timestamp >= 946684800000 && $timestamp <= ((int) floor(microtime(true) * 1000) + 31536000000) ? $timestamp : null;
}

function postsContent($value): ?string
{
	if (!is_string($value)) {
		return null;
	}
	$content = trim($value);
	return mb_strlen($content) <= POSTS_MAX_CONTENT_LENGTH ? $content : null;
}

function postsSaveWithMedia(array $post, array $identity, string $token, ?array $previous = null): array
{
	return mediaWithLock(postsLocalMode(), static function () use ($post, $identity, $token, $previous): array {
		$index = mediaReadIndex(postsLocalMode());
		mediaValidateAttachments($post, $identity, $index, $previous);
		if (!postsSaveTelemetry($post, $token)) {
			throw new RuntimeException('Eintrag konnte nicht in ThingsBoard gespeichert werden.');
		}
		// Nach erfolgreicher Telemetrie-Speicherung ist der Post angelegt. Ein Cachefehler
		// darf keinen zweiten Post beim Wiederholen erzeugen; GET kann den Cache reparieren.
		try {
			postsUpsertCache($post, $token);
		} catch (Throwable $error) {
			error_log('Saved post; cache update failed: ' . $error->getMessage());
			@unlink(postsCacheFile());
			return ['post' => $post, 'warning' => 'Post gespeichert. Die Anzeige wird beim nächsten Laden aktualisiert.'];
		}
		$removed = array_values(array_diff($previous['mediaIds'] ?? [], $post['mediaIds']));
		if ($removed !== []) {
			try {
				mediaRemoveEntries(postsLocalMode(), $index, $removed);
			} catch (Throwable $error) {
				error_log('Removed post media will be retried by daily cleanup: ' . $error->getMessage());
			}
		}
		return ['post' => $post];
	});
}
