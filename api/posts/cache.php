<?php

function postsLocalMode(): bool
{
	return defined('POSTS_LOCAL_MODE') && POSTS_LOCAL_MODE === true;
}

function postsVisibleInEnvironment(array $post): bool
{
	return postsLocalMode() || !isset($post['environment']);
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

function postsFilterForEnvironment(array $posts): array
{
	return array_values(array_filter($posts, static fn ($post) => is_array($post) && postsVisibleInEnvironment($post)));
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
	// Auch kopierte Caches einer anderen Umgebung müssen neu aufgebaut werden.
	if (!is_array($data) || ($data['version'] ?? null) !== 2
		|| ($data['localMode'] ?? null) !== postsLocalMode()
		|| !is_array($data['posts'] ?? null)) {
		return null;
	}
	$data['posts'] = postsFilterForEnvironment($data['posts']);
	return $data;
}

function postsWriteCache(array $posts): array
{
	$posts = postsFilterForEnvironment($posts);
	usort($posts, static function (array $a, array $b): int {
		return ((int) ($b['timestamp'] ?? 0)) <=> ((int) ($a['timestamp'] ?? 0));
	});

	$cache = [
		'version' => 2,
		'localMode' => postsLocalMode(),
		'cacheTimestamp' => (int) floor(microtime(true) * 1000),
		'posts' => array_values($posts),
	];
	$json = json_encode($cache, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
	if ($json === false) {
		throw new RuntimeException('Posts cache could not be encoded.');
	}

	atomicWrite(postsCacheFile(), $json);
	return $cache;
}
