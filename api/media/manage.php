<?php

require_once __DIR__ . '/../posts/service.php';

function mediaAuthorName(string $userId, string $token): string
{
	static $names = [];
	if (array_key_exists($userId, $names)) return $names[$userId];
	if (!mediaValidId($userId)) return $names[$userId] = '';

	$response = thingsBoardManagementRequest('GET', '/user/' . rawurlencode($userId), null, $token);
	$user = $response['body'];
	if ($response['status'] !== 200 || !is_array($user)) return $names[$userId] = '';

	$name = trim(implode(' ', array_filter([
		is_string($user['firstName'] ?? null) ? $user['firstName'] : '',
		is_string($user['lastName'] ?? null) ? $user['lastName'] : '',
	])));
	return $names[$userId] = $name !== '' ? $name : (is_string($user['email'] ?? null) ? $user['email'] : '');
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'DELETE'], true)) {
	postsRespond(['error' => 'Methode nicht unterstützt.'], 405, true);
}
$identity = postsRequireIdentity();
if ($method === 'DELETE') postsRequireCsrf();
session_write_close();

try {
	if ($method === 'GET') {
		if (isset($_GET['id'])) {
			$id = $_GET['id'];
			if (!mediaValidId($id)) postsRespond(['error' => 'Ungültige Medien-ID.'], 400, true);
			foreach (postsLocalMode() ? [false, true] : [false] as $local) {
				foreach (mediaReadIndex($local)['media'] as $item) {
					if (($item['id'] ?? null) !== $id || !mediaInEnvironment($item, $local)) continue;
					if (!mediaCanManage($identity, $item)) postsRespond(['error' => 'Du darfst dieses Medium nicht verwalten.'], 403, true);
					$post = postsFindOnDevice($item['postId'], $item['deviceId'], postsServiceToken());
					postsRespond(mediaDeletionContext($item, $post), 200, true);
				}
			}
			postsRespond(['error' => 'Medium wurde nicht gefunden.'], 404, true);
		}
		// Die Übersicht darf nicht auf einen vollständigen ThingsBoard-Abruf warten.
		// Ein leerer oder abgelaufener Post-Cache wird von der Posts-Ansicht aufgebaut;
		// für die optionale Kennzeichnung genügt hier ein bereits vorhandener Cache.
		$cache = postsReadCache();
		$token = postsServiceToken();
		$items = [];
		foreach (postsLocalMode() ? [false, true] : [false] as $local) {
			foreach (mediaReadIndex($local)['media'] as $item) {
				if (!is_array($item) || !mediaValidId($item['id'] ?? null)
					|| !mediaInEnvironment($item, $local)
					|| (!mediaIsAdmin($identity) && ($item['authorUserId'] ?? null) !== $identity['id'])) continue;
				$authorUserId = is_string($item['authorUserId'] ?? null) ? $item['authorUserId'] : '';
				$publicItem = mediaPublicItem($item);
				$publicItem['authorUserId'] = $authorUserId;
				$publicItem['authorName'] = mediaAuthorName($authorUserId, $token);
				$publicItem['canDelete'] = $local === postsLocalMode() && mediaCanManage($identity, $item);
				$publicItem['attached'] = $cache !== null && mediaReferenced($item, $cache['posts']);
				$items[] = $publicItem;
			}
		}
		usort($items, static fn ($a, $b) => ($b['uploadedAt'] ?? 0) <=> ($a['uploadedAt'] ?? 0));
		postsRespond(['media' => $items, 'localMode' => postsLocalMode(), 'allUsers' => mediaIsAdmin($identity)], 200, true);
	}

	$input = postsReadJsonBody();
	$draftId = $input['draftId'] ?? null;
	if ($draftId !== null) {
		$deviceId = $input['deviceId'] ?? null;
		if (!mediaValidId($draftId) || !mediaValidId($deviceId)) {
			postsRespond(['error' => 'Ungültiger Entwurf oder Standort.'], 400, true);
		}
		postsRequireLocation($deviceId);
		$token = postsServiceToken();
		if (postsFindOnDevice($draftId, $deviceId, $token) !== null) {
			postsRespond(['error' => 'Veröffentlichte Posts können nicht als Entwurf verworfen werden.'], 409, true);
		}
		$result = mediaWithLock(postsLocalMode(), static function () use ($draftId, $deviceId, $identity): array {
			$index = mediaReadIndex(postsLocalMode());
			mediaDiscardDraft($index, $draftId, $deviceId, $identity['id']);
			$ids = array_column(array_filter($index['media'], static fn ($item) => ($item['postId'] ?? null) === $draftId
				&& ($item['deviceId'] ?? null) === $deviceId && ($item['authorUserId'] ?? null) === $identity['id']), 'id');
			if ($ids === []) mediaWriteIndex(postsLocalMode(), $index);
			else mediaRemoveEntries(postsLocalMode(), $index, $ids);
			return ['success' => true, 'ids' => $ids];
		});
		postsRespond($result, 200, true);
	}
	$id = $input['id'] ?? null;
	if (!mediaValidId($id)) postsRespond(['error' => 'Ungültige Medien-ID.'], 400, true);
	$revision = $input['postRevision'] ?? null;
	if ($revision !== null && (!is_string($revision) || !preg_match('/^[a-f0-9]{64}$/D', $revision))) {
		postsRespond(['error' => 'Ungültiger Post-Stand.'], 400, true);
	}
	$result = postsWithLock(static function () use ($id, $identity, $revision): array {
		return mediaWithLock(postsLocalMode(), static function () use ($id, $identity, $revision): array {
			$index = mediaReadIndex(postsLocalMode());
			$item = null;
			foreach ($index['media'] as $entry) if (($entry['id'] ?? null) === $id) $item = $entry;
			if ($item === null) {
				// Lokal sind heruntergeladene Produktionsmedien nur lesbar, auch für Admins.
				if (postsLocalMode()) {
					foreach (mediaReadIndex(false)['media'] as $entry) {
						if (($entry['id'] ?? null) === $id) {
							postsRespond(['error' => 'Produktionsbilder können nur in Produktion gelöscht werden.'], 403, true);
						}
					}
				}
				// Wiederholung nach verlorener HTTP-Antwort bleibt erfolgreich.
				return ['success' => true, 'id' => $id, 'posts' => []];
			}
			if (!mediaInEnvironment($item, postsLocalMode()) || !mediaCanManage($identity, $item)) {
				postsRespond(['error' => 'Du darfst dieses Medium nicht löschen.'], 403, true);
			}

			// Die Telemetrie ist maßgeblich: Beim Entfernen nicht versehentlich einen
			// veralteten Post aus dem Cache zurück nach ThingsBoard schreiben.
			$token = postsServiceToken();
			$post = postsFindOnDevice($item['postId'], $item['deviceId'], $token);
			if ($revision !== null && !hash_equals(mediaDeletionContext($item, $post)['postRevision'], $revision)) {
				postsRespond(['error' => 'Der Post wurde zwischenzeitlich geändert. Bitte die Löschung erneut bestätigen.'], 409, true);
			}
			$updated = [];
			$removedPostIds = [];
			if ($post !== null && in_array($id, $post['mediaIds'] ?? [], true)) {
				if (!postsWritableInEnvironment($post)) {
					postsRespond(['error' => 'Der zugehörige Post gehört zu einer anderen Umgebung.'], 403, true);
				}
				$post['mediaIds'] = array_values(array_filter($post['mediaIds'], static fn ($mediaId) => $mediaId !== $id));
				$post['updatedAt'] = (int) floor(microtime(true) * 1000);
				if (trim($post['content'] ?? '') === '' && $post['mediaIds'] === []) {
					// Nur das tatsächlich angehängte letzte Bild kann einen Post leeren.
					// Unveröffentlichte Uploads dürfen keinen unabhängigen Post löschen.
					if (!postsDeleteTelemetry($post, $token)) {
						throw new RuntimeException('Leerer Bildpost konnte nicht aus ThingsBoard gelöscht werden.');
					}
					$removedPostIds[] = $post['id'];
				} else {
					if (!postsSaveTelemetry($post, $token)) {
						// Bei einem Fehler bleiben Index und Dateien für einen erneuten Versuch erhalten.
						throw new RuntimeException('Bild-Verknüpfung konnte nicht aus ThingsBoard entfernt werden.');
					}
					$updated[] = $post;
				}
			} elseif ($post !== null) {
				$updated[] = $post;
			} else {
				$removedPostIds[] = $item['postId'];
			}
			try {
				foreach ($updated as $post) postsUpsertCache($post, $token);
				foreach ($removedPostIds as $postId) postsRemoveFromCache($postId, $token);
			} catch (Throwable $error) {
				error_log('Media removed from telemetry; posts cache update failed: ' . $error->getMessage());
				@unlink(postsCacheFile());
			}
			mediaRemoveEntries(postsLocalMode(), $index, [$id]);
			return ['success' => true, 'id' => $id, 'posts' => $updated, 'removedPostIds' => $removedPostIds];
		});
	});
	postsRespond($result, 200, true);
} catch (Throwable $error) {
	error_log('Media management: ' . $error->getMessage());
	postsRespond(['error' => 'Das Medium konnte nicht gelöscht oder die Übersicht nicht geladen werden. Bitte erneut versuchen.'], 502, true);
}
