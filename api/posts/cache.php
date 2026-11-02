<?php

function postsLocalMode(): bool
{
	return defined('POSTS_LOCAL_MODE') && POSTS_LOCAL_MODE === true;
}

function postsWritableInEnvironment(array $post): bool
{
	return postsLocalMode() ? ($post['environment'] ?? '') === 'local' : !isset($post['environment']);
}

function postsWithLock(callable $callback)
{
	static $depth = 0;
	if ($depth > 0) return $callback();
	if (!is_dir(CACHE_DIR) && !mkdir(CACHE_DIR, 0755, true) && !is_dir(CACHE_DIR)) {
		throw new RuntimeException('Posts-Verzeichnis konnte nicht angelegt werden.');
	}
	$handle = fopen(CACHE_DIR . '/posts.lock', 'c');
	if ($handle === false) throw new RuntimeException('Posts-Lock konnte nicht geöffnet werden.');
	try {
		if (!flock($handle, LOCK_EX)) throw new RuntimeException('Posts-Lock konnte nicht gesetzt werden.');
		$depth++;
		return $callback();
	} finally {
		$depth = 0;
		flock($handle, LOCK_UN);
		fclose($handle);
	}
}

function postsCacheFile(): string
{
	return CACHE_DIR . '/posts.json';
}

function postsReadCache(): ?array
{
	$file = postsCacheFile();
	if (!is_file($file)) {
		return null;
	}

	$data = json_decode((string) file_get_contents($file), true);
	// Ältere Caches haben die Umgebungskennung beim Normalisieren verworfen.
	if (!is_array($data) || ($data['version'] ?? null) !== 2
		|| !is_array($data['posts'] ?? null)) {
		return null;
	}
	$data['localMode'] = postsLocalMode();
	return $data;
}

function postsWriteCache(array $posts): array
{
	usort($posts, static function (array $a, array $b): int {
		return ((int) ($b['timestamp'] ?? 0)) <=> ((int) ($a['timestamp'] ?? 0));
	});

	$cache = [
		'version' => 2,
		'cacheTimestamp' => (int) floor(microtime(true) * 1000),
		'posts' => array_values($posts),
	];
	$json = json_encode($cache, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
	if ($json === false) {
		throw new RuntimeException('Posts cache could not be encoded.');
	}

	atomicWrite(postsCacheFile(), $json);
	return $cache + ['localMode' => postsLocalMode()];
}
