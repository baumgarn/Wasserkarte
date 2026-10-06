<?php
require_once __DIR__ . '/../api/media/videos.php';

function videoCheck(bool $condition, string $message): void
{
	if (!$condition) throw new RuntimeException($message);
	echo 'OK: ' . $message . PHP_EOL;
}
$source = __DIR__ . '/fixtures/video.mp4';
$info = mediaVideoInfo($source);
videoCheck($info === ['width' => 160, 'height' => 90, 'duration' => 1], 'H.264/AAC-Datei wird gelesen');
$temp = tempnam(sys_get_temp_dir(), 'video-test-');
try {
	foreach ([str_replace('avc1', 'hvc1', file_get_contents($source)), substr(file_get_contents($source), 0, 100), 'not a video'] as $invalid) {
		file_put_contents($temp, $invalid);
		$rejected = false;
		try { mediaVideoInfo($temp); } catch (InvalidArgumentException $error) { $rejected = true; }
		videoCheck($rejected, 'HEVC oder beschädigtes MP4 wird abgelehnt');
	}
	videoCheck(!mediaAacDescriptor(str_repeat("\0", 30)), 'Ungültiger Audio-Descriptor wird abgelehnt');
	$video = ['id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'type' => 'video', 'postId' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'deviceId' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc', 'environment' => 'local', 'authorUserId' => 'owner'];
	$post = ['id' => $video['postId'], 'deviceId' => $video['deviceId'], 'environment' => 'local', 'mediaIds' => [$video['id'], 'dddddddd-dddd-4ddd-8ddd-dddddddddddd']];
	$rejected = false;
	try { mediaValidateAttachments($post, ['id' => 'owner', 'thingsboardAuthority' => 'TENANT_ADMIN'], ['media' => [$video]], null); } catch (InvalidArgumentException $error) { $rejected = str_contains($error->getMessage(), 'einzelnes Video'); }
	videoCheck($rejected, 'Posts mit Video und weiterem Anhang werden abgelehnt');
	$post['mediaIds'] = [$video['id']];
	$rejected = false;
	try { mediaValidateAttachments($post, ['id' => 'owner', 'wasserkarteRole' => 'wassermeister'], ['media' => [$video]], null); } catch (InvalidArgumentException $error) { $rejected = str_contains($error->getMessage(), 'keine Videos'); }
	videoCheck($rejected, 'Video hinzufügen wird beim Speichern für normale Nutzer abgelehnt');
} finally { unlink($temp); }
