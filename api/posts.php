<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/telemetry/cache.php';
require_once __DIR__ . '/management/session.php';
require_once __DIR__ . '/management/thingsboard-client.php';
require_once __DIR__ . '/posts/cache.php';

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

	return [
		'id' => $value['id'],
		'deviceId' => $deviceId,
		'timestamp' => $timestamp,
		'createdAt' => $createdAt,
		'updatedAt' => isset($value['updatedAt']) ? (int) $value['updatedAt'] : $createdAt,
		'authorUserId' => $authorUserId,
		'authorName' => is_string($value['authorName'] ?? null) ? $value['authorName'] : '',
		'content' => $value['content'],
	];
}

function postsRebuildCache(string $token): array
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

function postsUuid(): string
{
	$hex = bin2hex(random_bytes(16));
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
	return $content !== '' && mb_strlen($content) <= POSTS_MAX_CONTENT_LENGTH ? $content : null;
}

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

$identity = postsRequireIdentity();
postsRequireCsrf();
$input = postsReadJsonBody();
if ($input === null) {
	postsRespond(['error' => 'Ungültige Anfrage.'], 400, true);
}
$token = postsServiceToken();

if ($method === 'POST') {
	$deviceId = $input['deviceId'] ?? null;
	$content = postsContent($input['content'] ?? null);
	$timestamp = postsTimestamp($input['timestamp'] ?? (int) floor(microtime(true) * 1000));
	postsRequireLocation(is_string($deviceId) ? $deviceId : '');
	if ($content === null || $timestamp === null) {
		postsRespond(['error' => 'Inhalt oder Zeitpunkt sind ungültig.'], 400, true);
	}
	if (!postsCanWriteDevice($identity, $deviceId, $token)) {
		postsRespond(['error' => 'Du darfst für diesen Standort keine Einträge erstellen.'], 403, true);
	}
	$now = postsNextCreatedAt($deviceId);
	$post = [
		'id' => postsUuid(),
		'deviceId' => $deviceId,
		'timestamp' => $timestamp,
		'createdAt' => $now,
		'updatedAt' => $now,
		'authorUserId' => $identity['id'],
		'authorName' => trim(($identity['firstName'] ?? '') . ' ' . ($identity['lastName'] ?? '')) ?: ($identity['email'] ?? ''),
		'content' => $content,
	];
	if (!postsSaveTelemetry($post, $token)) {
		postsRespond(['error' => 'Eintrag konnte nicht gespeichert werden.'], 502, true);
	}
	try {
		postsUpsertCache($post, $token);
	} catch (Throwable $error) {
		postsRespond(['error' => 'Eintrag wurde gespeichert, aber der Posts-Cache konnte nicht aktualisiert werden.'], 502, true);
	}
	postsRespond(['post' => $post], 201, true);
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
	$timestamp = postsTimestamp($input['timestamp'] ?? null);
	if ($content === null || $timestamp === null) {
		postsRespond(['error' => 'Inhalt oder Zeitpunkt sind ungültig.'], 400, true);
	}
	$post['content'] = $content;
	$post['timestamp'] = $timestamp;
	$post['updatedAt'] = (int) floor(microtime(true) * 1000);
	if (!postsSaveTelemetry($post, $token)) {
		postsRespond(['error' => 'Eintrag konnte nicht aktualisiert werden.'], 502, true);
	}
	try {
		postsUpsertCache($post, $token);
	} catch (Throwable $error) {
		postsRespond(['error' => 'Eintrag wurde aktualisiert, aber der Posts-Cache konnte nicht aktualisiert werden.'], 502, true);
	}
	postsRespond(['post' => $post], 200, true);
}

if ($method === 'DELETE') {
	$response = thingsBoardManagementRequest(
		'DELETE',
		'/plugins/telemetry/DEVICE/' . rawurlencode($post['deviceId']) . '/timeseries/delete?' . http_build_query([
			'keys' => POSTS_TELEMETRY_KEY,
			'startTs' => $post['createdAt'],
			'endTs' => $post['createdAt'],
		]),
		null,
		$token
	);
	if (!in_array($response['status'], [200, 204], true)) {
		postsRespond(['error' => 'Eintrag konnte nicht gelöscht werden.'], 502, true);
	}
	try {
		postsRemoveFromCache($postId, $token);
	} catch (Throwable $error) {
		postsRespond(['error' => 'Eintrag wurde gelöscht, aber der Posts-Cache konnte nicht aktualisiert werden.'], 502, true);
	}
	postsRespond(['success' => true], 200, true);
}

postsRespond(['error' => 'Methode nicht unterstützt.'], 405, true);
