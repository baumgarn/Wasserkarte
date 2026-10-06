<?php

// Eigenständiger Testlauf ohne echte Konfiguration oder ThingsBoard-Zugang.
// php tests/media-test.php
$testRoot = sys_get_temp_dir() . '/wasserkarte-media-test-' . bin2hex(random_bytes(6));
mkdir($testRoot, 0700);
define('CACHE_DIR', $testRoot . '/cache');
define('MEDIA_STORAGE_DIR', $testRoot . '/storage');
define('POSTS_LOCAL_MODE', true);
require_once __DIR__ . '/../api/telemetry/cache.php';
require_once __DIR__ . '/../api/posts/cache.php';
require_once __DIR__ . '/../api/media/images.php';

function check(bool $condition, string $message): void
{
	if (!$condition) throw new RuntimeException($message);
	echo 'OK: ' . $message . PHP_EOL;
}

function removeTestDirectory(string $directory): void
{
	foreach (new FilesystemIterator($directory) as $entry) {
		if ($entry->isDir()) removeTestDirectory($entry->getPathname());
		else unlink($entry->getPathname());
	}
	rmdir($directory);
}

try {
	mkdir(CACHE_DIR, 0700);
	mediaPrepareDirectory(false);
	mediaPrepareDirectory(true);
	check(mediaDirectory() === $testRoot . '/storage' && mediaDirectory(true) === $testRoot . '/storage/local', 'Medien liegen unabhängig vom Cache direkt in storage');
	check(is_file(mediaDirectory() . '/.htaccess'), 'HTTP-Sperre wird angelegt');
	$id = '11111111-1111-4111-8111-111111111111';
	$source = $testRoot . '/source.png';
	$image = imagecreatetruecolor(2400, 1200);
	imagepng($image, $source);
	imagedestroy($image);
	$variants = mediaProcessImage($source, $id, true);
	check($variants['display']['width'] === 2000 && $variants['display']['height'] === 1000, 'Anzeigebild: 2000 × 1000');
	check($variants['thumbnail']['width'] === 300 && $variants['thumbnail']['height'] === 150, 'Thumbnail: 300 × 150');
	check(getimagesize(mediaFilePath(['id' => $id], 'display', true))['mime'] === 'image/webp', 'Varianten sind echte WebP-Dateien');
	$image = imagecreatetruecolor(80, 120);
	imagepng($image, $source);
	imagedestroy($image);
	$smallId = '22222222-2222-4222-8222-222222222222';
	$small = mediaProcessImage($source, $smallId, false);
	check($small['display']['width'] === 80 && $small['thumbnail']['height'] === 120, 'Kleine Bilder werden nicht vergrößert');

	// JPEG mit EXIF-Orientierung 6: 80 × 120 muss zu 120 × 80 werden.
	$jpeg = $testRoot . '/rotated.jpg';
	$image = imagecreatetruecolor(80, 120);
	imagejpeg($image, $jpeg);
	imagedestroy($image);
	$tiff = 'II' . pack('vV', 42, 8) . pack('v', 1) . pack('vvVv', 0x0112, 3, 1, 6) . "\0\0" . pack('V', 0);
	$exif = "Exif\0\0" . $tiff;
	$bytes = file_get_contents($jpeg);
	file_put_contents($jpeg, substr($bytes, 0, 2) . "\xFF\xE1" . pack('n', strlen($exif) + 2) . $exif . substr($bytes, 2));
	$rotated = mediaProcessImage($jpeg, '33333333-3333-4333-8333-333333333333', true);
	check($rotated['display']['width'] === 120 && $rotated['display']['height'] === 80, 'JPEG-EXIF-Ausrichtung wird korrigiert');
	file_put_contents($testRoot . '/fake.jpg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
	try {
		mediaProcessImage($testRoot . '/fake.jpg', $id, true);
		throw new RuntimeException('SVG wurde angenommen');
	} catch (InvalidArgumentException $expected) { check(true, 'Gefälschte Dateiendung/SVG wird abgelehnt'); }

	$postId = '44444444-4444-4444-8444-444444444444';
	$userId = '55555555-5555-4555-8555-555555555555';
	$deviceId = '66666666-6666-4666-8666-666666666666';
	$item = ['id' => $id, 'postId' => $postId, 'deviceId' => $deviceId, 'environment' => 'local', 'authorUserId' => $userId, 'variants' => $variants];
	$post = ['id' => $postId, 'deviceId' => $deviceId, 'environment' => 'local', 'mediaIds' => [$id]];
	$index = ['version' => 1, 'media' => [$item]];
	mediaValidateAttachments($post, ['id' => $userId], $index, null);
	check(true, 'Eigener Upload wird dem richtigen Post zugeordnet');
	foreach ([['id' => '77777777-7777-4777-8777-777777777777'], ['id' => $userId, 'wrongEnvironment' => true]] as $identity) {
		$input = $post;
		if (isset($identity['wrongEnvironment'])) unset($input['environment']);
		try {
			mediaValidateAttachments($input, $identity, $index, null);
			throw new RuntimeException('Fremde Zuordnung wurde angenommen');
		} catch (InvalidArgumentException $expected) { check(true, 'Fremder Nutzer/andere Umgebung wird abgelehnt'); }
	}
	check(!mediaReferenced($item, []), 'Entwurfsupload ist nicht veröffentlicht');
	check(mediaReferenced($item, [$post]), 'Angehängtes Bild ist veröffentlicht');
	$context = mediaDeletionContext($item, $post);
	check($context['attached'] && $context['remainingImages'] === 0 && !$context['hasText'], 'Reiner Einzelbild-Post hat keinen weiteren Inhalt');
	$withText = $post + ['content' => 'Text bleibt'];
	$withText['mediaIds'] = [$id, $smallId, '88888888-8888-4888-8888-888888888888'];
	$context = mediaDeletionContext($item, $withText);
	check($context['remainingImages'] === 2 && $context['hasText'], 'Löschvorschau zählt weitere Bilder und erkennt Text');
	check($context['postRevision'] !== mediaDeletionContext($item, $post)['postRevision'], 'Geänderter Post erhält anderen Lösch-Fingerabdruck');
	$draftContext = mediaDeletionContext($item, null);
	check(!$draftContext['attached'] && $draftContext['remainingImages'] === 0 && !$draftContext['hasText'], 'Unveröffentlichter Upload hat keinen Post-Hinweis');
	check(!mediaInEnvironment($item, false), 'Lokales Medium ist in Produktion unsichtbar');
	check(!postsWritableInEnvironment(['id' => $postId]), 'Produktionsposts sind lokal nur lesbar');
	check(postsWritableInEnvironment($post), 'Lokale Posts sind lokal bearbeitbar');
	$public = mediaPublicItem($item);
	check(!isset($public['authorUserId']) && !isset($public['authorName']) && !isset($public['variants']['display']['path']), 'Öffentlicher Index enthält keine Nutzer-ID, Namen oder Speicherpfade');
	check(str_starts_with($public['variants']['display']['url'], '/api/media/'), 'URLs sind relativ zum jeweiligen Server');
	check(mediaIds(array_fill(0, 9, $id)) === null && mediaIds([$id, $id]) === null, 'Limit und doppelte Medien-IDs werden geprüft');
	check(mediaIds(['../file']) === null, 'Pfadmanipulation wird abgelehnt');
	mediaWithLock(true, static function () use ($index) { mediaWriteIndex(true, $index); });
	check(mediaReadIndex(true)['media'][0]['id'] === $id && mediaReadIndex(false)['media'] === [], 'Lokaler und produktiver Index sind getrennt');
	mediaWithLock(true, static function () use ($id) { mediaRemoveEntries(true, mediaReadIndex(true), [$id]); });
	check(mediaReadIndex(true)['media'] === [] && !is_file(mediaFilePath($item, 'display', true)), 'Entfernen löscht Indexeintrag und beide Varianten');
	removeTestDirectory(CACHE_DIR);
	check(is_file(mediaFilePath(['id' => $smallId], 'display', false)) && is_file(mediaDirectory(true) . '/media.json'), 'Leeren des Caches erhält Medien und ihren Index');
	file_put_contents(mediaDirectory(true) . '/media.json', '{broken');
	try { mediaReadIndex(true); throw new RuntimeException('Defekter Index akzeptiert'); }
	catch (RuntimeException $expected) { check($expected->getMessage() === 'Medienindex ist beschädigt.', 'Defekter Index wird nicht überschrieben'); }
} finally {
	removeTestDirectory($testRoot);
}
