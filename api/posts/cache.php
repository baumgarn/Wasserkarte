<?php

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
	return is_array($data) && isset($data['posts']) && is_array($data['posts']) ? $data : null;
}

function postsWriteCache(array $posts): array
{
	usort($posts, static function (array $a, array $b): int {
		return ((int) ($b['timestamp'] ?? 0)) <=> ((int) ($a['timestamp'] ?? 0));
	});

	$cache = [
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
