<?php

const MEDIA_MAX_PER_POST = 10;
const MEDIA_MAX_BYTES = 10 * 1024 * 1024;
const MEDIA_MAX_PIXELS = 24000000;

function mediaValidId($id): bool
{
	return is_string($id) && preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D', $id) === 1;
}

function mediaDirectory(bool $local = false): string
{
	$directory = defined('MEDIA_STORAGE_DIR') ? MEDIA_STORAGE_DIR : dirname(__DIR__) . '/storage';
	return $directory . ($local ? '/local' : '');
}

function mediaPrepareDirectory(bool $local): void
{
	$root = mediaDirectory();
	if (!is_dir($root) && !mkdir($root, 0750, true) && !is_dir($root)) {
		throw new RuntimeException('Medienverzeichnis konnte nicht angelegt werden.');
	}
	// Der Webserver darf Metadaten, Entwürfe und lokale Dateien nie direkt liefern.
	// nginx muss das Speicherverzeichnis in der Serverkonfiguration sperren.
	$protection = "Options -Indexes\nRequire all denied\n";
	if (!is_file($root . '/.htaccess')) {
		if (file_put_contents($root . '/.htaccess', $protection) === false) {
			throw new RuntimeException('Medienverzeichnis konnte nicht geschützt werden.');
		}
	}
	foreach (['', '/display', '/thumbnails'] as $suffix) {
		$directory = mediaDirectory($local) . $suffix;
		if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
			throw new RuntimeException('Medienverzeichnis konnte nicht angelegt werden.');
		}
	}
}

function mediaReadIndex(bool $local): array
{
	$file = mediaDirectory($local) . '/media.json';
	if (!is_file($file)) return ['version' => 1, 'media' => [], 'discardedDrafts' => []];
	$index = json_decode((string) file_get_contents($file), true);
	if (!is_array($index) || ($index['version'] ?? null) !== 1 || !is_array($index['media'] ?? null)) {
		// Nutzerdaten dürfen bei defektem JSON nicht mit einem leeren Index überschrieben werden.
		throw new RuntimeException('Medienindex ist beschädigt.');
	}
	// Alte Indizes enthalten diesen kurzlebigen Schutz gegen nachlaufende Uploads noch nicht.
	if (!is_array($index['discardedDrafts'] ?? null)) $index['discardedDrafts'] = [];
	return $index;
}

function mediaWriteIndex(bool $local, array $index): void
{
	$index['updatedAt'] = (int) floor(microtime(true) * 1000);
	$json = json_encode($index, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
	atomicWrite(mediaDirectory($local) . '/media.json', $json);
	@chmod(mediaDirectory($local) . '/media.json', 0640);
}

function mediaWithLock(bool $local, callable $callback)
{
	mediaPrepareDirectory($local);
	$handle = fopen(mediaDirectory($local) . '/index.lock', 'c');
	if ($handle === false) throw new RuntimeException('Medienlock konnte nicht geöffnet werden.');
	try {
		if (!flock($handle, LOCK_EX)) throw new RuntimeException('Medienlock konnte nicht gesetzt werden.');
		return $callback();
	} finally {
		flock($handle, LOCK_UN);
		fclose($handle);
	}
}

function mediaInEnvironment(array $item, bool $local): bool
{
	return ($item['environment'] ?? '') === ($local ? 'local' : 'production');
}

function mediaPruneDiscardedDrafts(array &$index): void
{
	$cutoff = (time() - 86400) * 1000;
	$index['discardedDrafts'] = array_filter($index['discardedDrafts'] ?? [], static function ($draft, $id) use ($cutoff): bool {
		return mediaValidId($id) && is_array($draft) && (int) ($draft['discardedAt'] ?? 0) >= $cutoff
			&& mediaValidId($draft['deviceId'] ?? null) && mediaValidId($draft['authorUserId'] ?? null);
	}, ARRAY_FILTER_USE_BOTH);
}

function mediaDraftWasDiscarded(array $index, string $postId, string $deviceId, string $authorUserId): bool
{
	$draft = $index['discardedDrafts'][$postId] ?? null;
	return is_array($draft) && ($draft['deviceId'] ?? null) === $deviceId && ($draft['authorUserId'] ?? null) === $authorUserId;
}

function mediaDiscardDraft(array &$index, string $postId, string $deviceId, string $authorUserId): void
{
	mediaPruneDiscardedDrafts($index);
	$index['discardedDrafts'][$postId] = [
		'deviceId' => $deviceId,
		'authorUserId' => $authorUserId,
		'discardedAt' => (int) floor(microtime(true) * 1000),
	];
}

function mediaIsAdmin(array $identity): bool
{
	return in_array($identity['thingsboardAuthority'] ?? '', ['TENANT_ADMIN', 'SYS_ADMIN'], true);
}

function mediaCanViewAll(array $identity): bool
{
	return mediaIsAdmin($identity) || ($identity['wasserkarteRole'] ?? '') === 'super_wassermeister';
}

function mediaCanView(?array $identity, array $item): bool
{
	return $identity !== null && !empty($identity['id'])
		&& (mediaCanViewAll($identity) || ($item['authorUserId'] ?? null) === $identity['id']);
}

function mediaCanManage(?array $identity, array $item): bool
{
	return $identity !== null && !empty($identity['id'])
		&& (mediaCanViewAll($identity) || ($item['authorUserId'] ?? null) === $identity['id']);
}

function mediaIds($value): ?array
{
	if (!is_array($value) || !array_is_list($value) || count($value) > MEDIA_MAX_PER_POST) return null;
	foreach ($value as $id) {
		if (!mediaValidId($id)) return null;
	}
	return count(array_unique($value)) === count($value) ? $value : null;
}

function mediaValidateAttachments(array $post, array $identity, array $index, ?array $previous): void
{
	$items = array_column($index['media'], null, 'id');
	$local = isset($post['environment']) && $post['environment'] === 'local';
	foreach ($post['mediaIds'] as $id) {
		$item = $items[$id] ?? null;
		if (!is_array($item) || !mediaInEnvironment($item, $local)
			|| ($item['postId'] ?? null) !== $post['id'] || ($item['deviceId'] ?? null) !== $post['deviceId']) {
			throw new InvalidArgumentException('Ein Medium gehört nicht zu diesem Post oder dieser Umgebung.');
		}
		if (($item['type'] ?? 'image') === 'video') {
			if (count($post['mediaIds']) !== 1) throw new InvalidArgumentException('Bitte entweder Fotos oder ein einzelnes Video auswählen.');
			if (!mediaCanViewAll($identity) && !in_array($id, $previous['mediaIds'] ?? [], true)) throw new InvalidArgumentException('Du darfst keine Videos hinzufügen.');
		}
		// Bestehende Anhänge dürfen auch berechtigte Moderator:innen behalten.
		if (($item['authorUserId'] ?? null) !== $identity['id'] && !in_array($id, $previous['mediaIds'] ?? [], true)) {
			throw new InvalidArgumentException('Ein Medium wurde nicht von dir hochgeladen.');
		}
		foreach (['display', 'thumbnail'] as $variant) {
			if (!is_file(mediaFilePath($item, $variant, $local))) {
				throw new InvalidArgumentException('Eine Bilddatei fehlt. Bitte das Medium erneut hochladen.');
			}
		}
	}
}

function mediaFilePath(array $item, string $variant, bool $local): string
{
	if (!mediaValidId($item['id'] ?? null) || !in_array($variant, ['display', 'thumbnail'], true)) {
		throw new InvalidArgumentException('Ungültiges Medium.');
	}
	return mediaDirectory($local) . ($variant === 'display' ? '/display/' : '/thumbnails/') . $item['id'] . (($item['type'] ?? 'image') === 'video' && $variant === 'display' ? '.mp4' : '.webp');
}

function mediaPublicItem(array $item): array
{
	$result = array_intersect_key($item, array_flip(['id', 'type', 'postId', 'deviceId', 'environment', 'uploadedAt', 'variants', 'duration']));
	foreach (['display', 'thumbnail'] as $variant) {
		$result['variants'][$variant]['url'] = '/api/media/file.php?id=' . rawurlencode($item['id']) . '&variant=' . $variant;
		unset($result['variants'][$variant]['path']);
	}
	return $result;
}

function mediaReferenced(array $item, array $posts): bool
{
	foreach ($posts as $post) {
		if (($post['id'] ?? null) === ($item['postId'] ?? null)
			&& ($post['deviceId'] ?? null) === ($item['deviceId'] ?? null)
			&& (($post['environment'] ?? 'production') === ($item['environment'] ?? null))
			&& in_array($item['id'], $post['mediaIds'] ?? [], true)) return true;
	}
	return false;
}

function mediaDeletionContext(array $item, ?array $post): array
{
	$attached = $post !== null && mediaReferenced($item, [$post]);
	return [
		'attached' => $attached,
		'remainingImages' => $attached ? count(array_filter($post['mediaIds'] ?? [], static fn ($id) => $id !== $item['id'])) : 0,
		'hasText' => $attached && trim($post['content'] ?? '') !== '',
		// Nur ein Fingerabdruck, keine zusätzlichen Post-Inhalte in der privaten API.
		'postRevision' => hash('sha256', json_encode($post, JSON_THROW_ON_ERROR)),
	];
}

function mediaRemoveEntries(bool $local, array $index, array $ids): void
{
	$removed = array_values(array_filter($index['media'], static fn ($item) => in_array($item['id'] ?? null, $ids, true)));
	$index['media'] = array_values(array_filter($index['media'], static fn ($item) => !in_array($item['id'] ?? null, $ids, true)));
	// Erst den Index ersetzen; ein Fehler lässt die bisherigen Bilddateien erhalten.
	mediaWriteIndex($local, $index);
	foreach ($removed as $item) {
		foreach (['display', 'thumbnail'] as $variant) {
			$file = mediaFilePath($item, $variant, $local);
			if (is_file($file) && !unlink($file)) error_log('Media file could not be removed: ' . $file);
		}
	}
}
