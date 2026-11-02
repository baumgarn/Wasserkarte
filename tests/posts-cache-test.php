<?php

// php tests/posts-cache-test.php [local]
define('POSTS_LOCAL_MODE', ($argv[1] ?? '') === 'local');
define('CACHE_DIR', sys_get_temp_dir() . '/wasserkarte-posts-test-' . bin2hex(random_bytes(6)));
require_once __DIR__ . '/../api/telemetry/cache.php';
require_once __DIR__ . '/../api/posts/cache.php';

function check(bool $condition, string $message): void
{
	if (!$condition) throw new RuntimeException($message);
	echo "OK: $message\n";
}

try {
	$posts = [['id' => 'local', 'environment' => 'local', 'timestamp' => 2], ['id' => 'production', 'timestamp' => 1]];
	$written = postsWriteCache($posts);
	check($written['posts'] === $posts, 'Writing preserves both environments');
	$disk = json_decode(file_get_contents(postsCacheFile()), true);
	check(!isset($disk['localMode']), 'Stored cache is independent of the environment');
	// A downloaded legacy v2 cache may still contain the other server’s mode.
	$disk['localMode'] = !POSTS_LOCAL_MODE;
	file_put_contents(postsCacheFile(), json_encode($disk));
	$read = postsReadCache();
	check($read !== null && $read['posts'] === $posts, 'Copied cache is reused without rebuilding');
	check($read['localMode'] === POSTS_LOCAL_MODE, 'Response reports the current server mode');
	check(postsWritableInEnvironment($posts[0]) === POSTS_LOCAL_MODE, 'Local write restriction is preserved');
	check(postsWritableInEnvironment($posts[1]) === !POSTS_LOCAL_MODE, 'Production write restriction is preserved');
} finally {
	@unlink(postsCacheFile());
	@rmdir(CACHE_DIR);
}
