<?php

require_once __DIR__ . '/../posts/service.php';

function mediaCleanup(array $posts): int
{
	// Ausschließlich die schreibbare Umgebung bereinigen; Produktionskopien bleiben lokal erhalten.
	return mediaWithLock(postsLocalMode(), static function () use ($posts): int {
		$index = mediaReadIndex(postsLocalMode());
		$ids = [];
		$cutoff = (time() - 86400) * 1000;
		foreach ($index['media'] as $item) {
			if ((int) ($item['uploadedAt'] ?? 0) < $cutoff && !mediaReferenced($item, $posts)) {
				$ids[] = $item['id'];
			}
		}
		if ($ids !== []) mediaRemoveEntries(postsLocalMode(), $index, $ids);
		return count($ids);
	});
}
