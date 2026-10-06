<?php

// HTTP-/ThingsBoard-Verknüpfung mit zwei lokalen PHP-Testservern.
// php tests/media-http-test.php
$root = sys_get_temp_dir() . '/wasserkarte-media-http-' . bin2hex(random_bytes(6));
mkdir($root, 0700);
mkdir($root . '/mock', 0700);
$source = realpath(__DIR__ . '/../api');
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS)) as $entry) {
	$relative = substr($entry->getPathname(), strlen($source) + 1);
	if (!$entry->isFile() || $entry->getExtension() !== 'php' || $relative === 'config.php' || str_starts_with($relative, 'cache/') || str_starts_with($relative, 'storage/')) continue;
	$target = $root . '/api/' . $relative;
	if (!is_dir(dirname($target))) mkdir(dirname($target), 0700, true);
	copy($entry->getPathname(), $target);
}
copy(__DIR__ . '/thingsboard-fixture.php', $root . '/mock/router.php');
mkdir($root . '/api/cache/sessions', 0700, true);
$device = '66666666-6666-4666-8666-666666666666';
$user = '55555555-5555-4555-8555-555555555555';
$postId = '44444444-4444-4444-8444-444444444444';
$session = 'mediatest' . bin2hex(random_bytes(8));
$csrf = bin2hex(random_bytes(32));
session_save_path($root . '/api/cache/sessions');
session_id($session);
session_start();
$_SESSION = ['management_identity' => ['id' => $user, 'firstName' => 'Test', 'lastName' => 'User', 'email' => 'test@example.invalid', 'thingsboardAuthority' => 'TENANT_ADMIN'], 'management_csrf_token' => $csrf];
session_write_close();
$testSessions = ['admin' => [$session, $csrf]];
foreach (['owner' => '12121212-1212-4212-8212-121212121212', 'other' => '13131313-1313-4313-8313-131313131313'] as $name => $identityId) {
	$fixtureSession = 'mediatest' . bin2hex(random_bytes(8));
	$fixtureCsrf = bin2hex(random_bytes(32));
	session_id($fixtureSession);
	session_start();
	$_SESSION = ['management_identity' => ['id' => $identityId, 'firstName' => $name, 'lastName' => 'User', 'thingsboardAuthority' => 'CUSTOMER_USER', 'wasserkarteRole' => 'super_wassermeister'], 'management_csrf_token' => $fixtureCsrf];
	session_write_close();
	$testSessions[$name] = [$fixtureSession, $fixtureCsrf];
}
$fixtureSession = 'mediatest' . bin2hex(random_bytes(8));
$fixtureCsrf = bin2hex(random_bytes(32));
session_id($fixtureSession);
session_start();
$_SESSION = ['management_identity' => ['id' => '14141414-1414-4414-8414-141414141414', 'firstName' => 'regular', 'lastName' => 'User', 'thingsboardAuthority' => 'CUSTOMER_USER', 'wasserkarteRole' => 'wassermeister'], 'management_csrf_token' => $fixtureCsrf];
session_write_close();
$testSessions['regular'] = [$fixtureSession, $fixtureCsrf];

function asUser(string $name): void
{
	global $session, $csrf, $testSessions;
	[$session, $csrf] = $testSessions[$name];
}
file_put_contents($root . '/api/cache/devices.json', json_encode(['devices' => [['id' => $device, 'name' => 'Teststandort']]]));

function freePort(): int
{
	$socket = stream_socket_server('tcp://127.0.0.1:0');
	$address = stream_socket_get_name($socket, false);
	fclose($socket);
	return (int) substr(strrchr($address, ':'), 1);
}

$mockPort = freePort();
$apiPort = freePort();
function config(bool $local): void
{
	global $root, $mockPort;
	file_put_contents($root . '/api/config.php', '<?php ' . "define('CACHE_DIR', __DIR__ . '/cache'); define('CACHE_FILE_DEVICES', CACHE_DIR . '/devices.json'); define('POSTS_LOCAL_MODE', " . ($local ? 'true' : 'false') . "); define('API_KEY', 'test'); define('THINGSBOARD_URL', 'http://127.0.0.1:$mockPort'); define('SESSION_COOKIE_SECURE', false); define('MANAGEMENT_SESSION_DIR', CACHE_DIR . '/sessions');");
}
config(true);
file_put_contents($root . '/api/cache/posts.json', json_encode(['version' => 2, 'localMode' => true, 'posts' => []]));
$descriptors = [0 => ['pipe', 'r'], 1 => ['file', $root . '/server.log', 'a'], 2 => ['file', $root . '/server.log', 'a']];
$servers = [];

function request(string $route, string $method = 'GET', $body = null, bool $authenticated = true, bool $validCsrf = true, array $extraHeaders = []): array
{
	global $apiPort, $session, $csrf;
	$curl = curl_init('http://127.0.0.1:' . $apiPort . '/api/' . $route);
	$headers = $extraHeaders;
	$responseHeaders = [];
	if ($validCsrf) $headers[] = 'X-CSRF-Token: ' . $csrf;
	if ($authenticated) $headers[] = 'Cookie: wasserkarte_session=' . $session;
	if ($body !== null && !is_array($body)) $headers[] = 'Content-Type: application/json';
	curl_setopt_array($curl, [CURLOPT_HEADERFUNCTION => static function ($curl, $line) use (&$responseHeaders) { $responseHeaders[] = trim($line); return strlen($line); }, CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 10]);
	if ($method === 'HEAD') curl_setopt($curl, CURLOPT_NOBODY, true);
	if ($body !== null) curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
	$raw = curl_exec($curl);
	$status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
	if ($raw === false) throw new RuntimeException(curl_error($curl));
	curl_close($curl);
	return [$status, json_decode($raw, true) ?? $raw, $responseHeaders];
}

function expect(bool $condition, string $message): void
{
	if (!$condition) throw new RuntimeException($message);
	echo 'OK: ' . $message . PHP_EOL;
}

function removeFixture(string $directory): void
{
	foreach (new FilesystemIterator($directory) as $entry) {
		if ($entry->isDir()) removeFixture($entry->getPathname()); else unlink($entry->getPathname());
	}
	rmdir($directory);
}

try {
	$servers[] = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $mockPort, $root . '/mock/router.php'], $descriptors, $pipes, $root . '/mock');
	$servers[] = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $apiPort, '-t', $root], $descriptors, $pipes, $root);
	for ($attempt = 0; $attempt < 100; $attempt++) {
		$socket = @fsockopen('127.0.0.1', $apiPort);
		if ($socket) { fclose($socket); break; }
		usleep(20000);
	}
	$image = imagecreatetruecolor(600, 900);
	imagepng($image, $root . '/source.png');
	imagedestroy($image);
	$videoPost = 'abababab-abab-4bab-8bab-abababababab';
	$videoSource = __DIR__ . '/fixtures/video.mp4';
	$videoUpload = ['deviceId' => $device, 'postId' => $videoPost, 'file' => new CURLFile($videoSource, 'video/mp4', 'video.mp4'), 'poster' => new CURLFile($root . '/source.png', 'image/png', 'poster.png')];
	asUser('regular');
	expect(request('media/index.php', 'POST', $videoUpload)[0] === 403, 'Normale Wassermeister dürfen keine Videos hochladen');
	asUser('owner');
	[$status, $videoBody] = request('media/index.php', 'POST', $videoUpload);
	expect($status === 201 && $videoBody['media']['type'] === 'video', 'Super-Wassermeister können ein MP4 hochladen');
	$videoId = $videoBody['media']['id'];
	expect($videoBody['media']['duration'] === 1 && $videoBody['media']['variants']['display']['width'] === 160, 'MP4-Dauer und Abmessungen werden aus der Datei gelesen');
	expect(file_get_contents($root . '/api/storage/local/display/' . $videoId . '.mp4') === file_get_contents($videoSource), 'Video wird ohne Konvertierung gespeichert');
	expect(request('media/index.php', 'POST', $videoUpload)[0] === 400, 'Zweites Video für denselben Post wird abgelehnt');
	$photoUpload = ['deviceId' => $device, 'postId' => $videoPost, 'file' => new CURLFile($root . '/source.png', 'image/png', 'source.png')];
	expect(request('media/index.php', 'POST', $photoUpload)[0] === 400, 'Foto kann nicht zu einem Video hinzugefügt werden');
	expect(request('media/file.php?id=' . $videoId, 'GET', null, false)[0] === 404, 'Videoentwurf ist nicht öffentlich');
	[$status, $videoPostBody] = request('posts/index.php', 'POST', json_encode(['id' => $videoPost, 'deviceId' => $device, 'content' => '', 'mediaIds' => [$videoId], 'timestamp' => (int) floor(microtime(true) * 1000)]));
	expect($status === 201, 'Einzelvideo-Post kann ohne Text veröffentlicht werden');
	[$status, $partial, $rangeHeaders] = request('media/file.php?id=' . $videoId, 'GET', null, false, true, ['Range: bytes=10-29']);
	expect($status === 206 && $partial === substr(file_get_contents($videoSource), 10, 20), 'Öffentliches Video liefert angeforderten Byte-Bereich');
	expect(in_array('Content-Type: video/mp4', $rangeHeaders, true), 'Video wird mit MP4-MIME-Typ ausgeliefert');
	[$status, $suffix] = request('media/file.php?id=' . $videoId, 'GET', null, false, true, ['Range: bytes=-20']);
	expect($status === 206 && $suffix === substr(file_get_contents($videoSource), -20), 'Suffix-Range wird unterstützt');
	expect(request('media/file.php?id=' . $videoId, 'GET', null, false, true, ['Range: bytes=999999-'])[0] === 416, 'Ungültiger Byte-Bereich liefert 416');
	expect(request('media/file.php?id=' . $videoId, 'HEAD', null, false)[0] === 200, 'HEAD für Videos wird unterstützt');
	[$status, $removedVideo] = request('media/manage.php', 'DELETE', json_encode(['id' => $videoId]));
	expect($status === 200 && in_array($videoPost, $removedVideo['removedPostIds'], true), 'Löschen des einzigen Videos entfernt den leeren Post');
	expect(!file_exists($root . '/api/storage/local/display/' . $videoId . '.mp4') && !file_exists($root . '/api/storage/local/thumbnails/' . $videoId . '.webp'), 'Video und Poster werden gelöscht');
	asUser('admin');
	$upload = ['deviceId' => $device, 'postId' => $postId, 'file' => new CURLFile($root . '/source.png', 'image/png', 'source.png')];
	expect(request('media/index.php', 'POST', $upload, false)[0] === 401, 'Upload erfordert Anmeldung');
	expect(request('media/index.php', 'POST', $upload, true, false)[0] === 403, 'Upload erfordert CSRF');
	[$status, $body] = request('media/index.php', 'POST', $upload);
	expect($status === 201, 'Multipart-Upload erzeugt Medium: ' . json_encode($body));
	$id = $body['media']['id'];
	$videoUpload['postId'] = $postId;
	expect(request('media/index.php', 'POST', $videoUpload)[0] === 400, 'Video kann nicht zu einer Fotogalerie hinzugefügt werden');
	expect(file_exists($root . '/api/storage/local/display/' . $id . '.webp') && !is_dir($root . '/api/cache/media'), 'Ohne neue Konfigurationsoption werden Uploads direkt in storage gespeichert');
	expect($body['media']['variants']['thumbnail']['height'] === 300, 'Hochformat-Thumbnail hat maximal 300 px');
	expect(request('media/index.php')[1]['media'] === [], 'Entwurf fehlt im öffentlichen Medienindex');
	expect(request('media/file.php?id=' . $id, 'GET', null, false)[0] === 404, 'Entwurfsdatei ist nicht öffentlich');
	expect(request('media/file.php?id=' . $id)[0] === 200, 'Admin kann unveröffentlichtes Bild privat ansehen');
	$discardPostId = 'dddddddd-dddd-4ddd-8ddd-dddddddddddd';
	$upload['postId'] = $discardPostId;
	[$status, $discardedImage] = request('media/index.php', 'POST', $upload);
	expect($status === 201, 'Bild für verworfenen Entwurf wird vorbereitet');
	$discardedIds = [$discardedImage['media']['id']];
	for ($imageIndex = 0; $imageIndex < 2; $imageIndex++) {
		[$status, $additionalImage] = request('media/index.php', 'POST', $upload);
		expect($status === 201, 'Weiteres Bild für verworfenen Entwurf wird hochgeladen');
		$discardedIds[] = $additionalImage['media']['id'];
	}
	[$status, $discardedDraft] = request('media/manage.php', 'DELETE', json_encode(['draftId' => $discardPostId, 'deviceId' => $device]));
	expect($status === 200 && $discardedDraft['ids'] === $discardedIds, 'Verwerfen eines Entwurfs entfernt alle bereits hochgeladenen Bilder');
	foreach ($discardedIds as $discardedId) {
		expect(!file_exists($root . '/api/storage/local/display/' . $discardedId . '.webp')
			&& !file_exists($root . '/api/storage/local/thumbnails/' . $discardedId . '.webp'), 'Beide Varianten jedes verworfenen Bildes werden sofort von der Platte entfernt');
	}
	expect(file_exists($root . '/api/storage/local/display/' . $id . '.webp'), 'Bilder eines anderen Entwurfs bleiben erhalten');
	expect(request('media/manage.php', 'DELETE', json_encode(['draftId' => $discardPostId, 'deviceId' => $device]))[1]['ids'] === [], 'Erneutes Verwerfen ist erfolgreich und liefert keine bereits entfernten IDs');
	expect(request('media/index.php', 'POST', $upload)[0] === 503, 'Nachlaufender Upload kann einen verworfenen Entwurf nicht wiederherstellen');
	$upload['postId'] = $postId;
	expect(request('media/manage.php', 'GET', null, false)[0] === 401, 'Medienverwaltung erfordert Anmeldung');
	expect(request('media/manage.php?id=' . $id, 'GET', null, false)[0] === 401, 'Private Löschvorschau erfordert Anmeldung');
	expect(request('media/manage.php?id=invalid')[0] === 400, 'Löschvorschau prüft Medien-ID');
	$draftContext = request('media/manage.php?id=' . $id)[1];
	expect(!$draftContext['attached'] && $draftContext['remainingImages'] === 0 && !$draftContext['hasText'], 'Entwurf hat keinen Hinweis auf verbleibenden Post-Inhalt');
	@unlink($root . '/api/cache/posts.json');
	$adminMedia = request('media/manage.php')[1]['media'][0];
	expect(!file_exists($root . '/api/cache/posts.json'), 'Medienübersicht baut einen kalten Post-Cache nicht synchron auf');
	expect($adminMedia['attached'] === false, 'Admin sieht auch unveröffentlichte Uploads');
	expect($adminMedia['authorName'] === 'Aktueller Admin', 'Medienübersicht löst den Namen über die Nutzer-ID auf');
	asUser('regular');
	$regularList = request('media/manage.php')[1];
	expect(!$regularList['allUsers'] && $regularList['media'] === [], 'Wassermeister sieht keine fremden Uploads und erhält keine Alle-Ansicht');
	asUser('owner');
	$superList = request('media/manage.php')[1];
	expect(!$superList['allUsers'] && $superList['media'] === [], 'Auch Super-Wassermeister erhält nur eigene Medien ohne Alle-Ansicht');
	expect(request('media/file.php?id=' . $id)[0] === 200, 'Super-Wassermeister kann fremden unveröffentlichten Upload ansehen');
	expect(request('media/manage.php?id=' . $id)[0] === 200, 'Super-Wassermeister kann fremde private Löschvorschau abrufen');
	asUser('admin');
	$post = ['id' => $postId, 'deviceId' => $device, 'content' => '', 'timestamp' => (int) floor(microtime(true) * 1000), 'mediaIds' => [$id]];
	[$status, $saved] = request('posts/index.php', 'POST', json_encode($post));
	expect($status === 201 && $saved['post']['environment'] === 'local', 'Bildpost ohne Text wird lokal markiert');
	expect(request('posts/index.php', 'POST', json_encode($post))[0] === 200, 'Wiederholtes Speichern erstellt keinen zweiten Post');
	expect(count(json_decode(file_get_contents($root . '/mock/telemetry.json'), true)) === 1, 'ThingsBoard enthält genau einen Telemetrieeintrag');
	expect(count(request('media/index.php')[1]['media']) === 1, 'Gespeicherter Anhang erscheint im Index');
	$singleContext = request('media/manage.php?id=' . $id)[1];
	expect($singleContext['attached'] && $singleContext['remainingImages'] === 0 && !$singleContext['hasText'], 'Löschvorschau erkennt reinen Einzelbild-Post');
	expect(request('media/file.php?id=' . $id)[0] === 200, 'Veröffentlichte Datei wird ausgeliefert');
	config(false);
	expect(request('posts/index.php')[1]['posts'] === [], 'Produktion filtert lokalen Post');
	expect(request('media/index.php')[1]['media'] === [], 'Produktion filtert lokales Medium');
	expect(request('media/file.php?id=' . $id)[0] === 404, 'Produktion sperrt lokale Bilddatei');
	config(true);
	// Neuer Cacheaufbau aus ThingsBoard muss mediaIds erhalten.
	$rebuilt = request('posts/index.php')[1]['posts'];
	expect($rebuilt[0]['mediaIds'] === [$id], 'mediaIds überleben ThingsBoard-Neuaufbau');
	// Eigene Bilder dürfen auch aus einem fremden, berechtigt bearbeiteten Post entfernt werden.
	asUser('owner');
	[$status, $ownerUpload] = request('media/index.php', 'POST', $upload);
	expect($status === 201, 'Berechtigter Nutzer lädt eigenes Bild an bestehenden Post hoch');
	$ownerId = $ownerUpload['media']['id'];
	$ownList = request('media/manage.php')[1];
	expect(!$ownList['allUsers'] && count($ownList['media']) === 1 && $ownList['media'][0]['id'] === $ownerId
		&& $ownList['media'][0]['canDelete'], 'Super-Wassermeister sieht ausschließlich eigene Bilder mit Löschrecht');
	expect(request('media/file.php?id=' . $ownerId)[0] === 200, 'Eigener Entwurf kann privat angesehen werden');
	$post['mediaIds'] = [$id, $ownerId];
	expect(request('posts/index.php', 'PATCH', json_encode($post))[0] === 200, 'Bestehender fremder Anhang bleibt beim berechtigten Bearbeiten erhalten');
	$oldContext = request('media/manage.php?id=' . $ownerId)[1];
	expect($oldContext['attached'] && $oldContext['remainingImages'] === 1 && !$oldContext['hasText'], 'Löschvorschau zählt nur andere Bilder des zugehörigen Posts');
	asUser('other');
	expect(request('media/manage.php')[1]['media'] === [], 'Fremde Bilder fehlen in der Medienübersicht anderer Super-Wassermeister');
	asUser('admin');
	expect(count(request('media/manage.php')[1]['media']) === 2, 'Adminübersicht enthält Bilder unterschiedlicher Nutzer');
	// ThingsBoard kann neuer als der Cache sein: aktuelle Inhalte dürfen nicht überschrieben werden.
	$telemetryFile = $root . '/mock/telemetry.json';
	$entries = json_decode(file_get_contents($telemetryFile), true);
	foreach ($entries as &$entry) {
		$value = json_decode($entry['value'], true);
		$value['content'] = 'Neuer Text direkt in ThingsBoard';
		$entry['value'] = json_encode($value);
	}
	unset($entry);
	file_put_contents($telemetryFile, json_encode($entries));
	asUser('owner');
	$freshContext = request('media/manage.php?id=' . $ownerId)[1];
	expect($freshContext['remainingImages'] === 1 && $freshContext['hasText']
		&& $freshContext['postRevision'] !== $oldContext['postRevision'] && !isset($freshContext['content']), 'Löschvorschau liest aktuellen ThingsBoard-Text statt veralteten Cache');
	expect(request('media/manage.php', 'DELETE', json_encode(['id' => $ownerId, 'postRevision' => $oldContext['postRevision']]))[0] === 409, 'Geänderter Post verlangt erneute Löschbestätigung');
	expect(file_exists($root . '/api/storage/local/display/' . $ownerId . '.webp')
		&& count(json_decode(file_get_contents($root . '/api/storage/local/media.json'), true)['media']) === 2, 'Veraltete Bestätigung verändert weder Index noch Datei');
	expect(request('media/manage.php', 'DELETE', json_encode(['id' => $ownerId, 'postRevision' => 'invalid']))[0] === 400, 'Ungültiger Lösch-Fingerabdruck wird abgelehnt');
	expect(request('media/manage.php', 'DELETE', json_encode(['id' => $ownerId]), true, false)[0] === 403, 'Bildlöschung erfordert CSRF');
	file_put_contents($root . '/mock/reject-save', 'once');
	expect(request('media/manage.php', 'DELETE', json_encode(['id' => $ownerId]))[0] === 502, 'Fehlgeschlagenes ThingsBoard-Update wird gemeldet');
	expect(file_exists($root . '/api/storage/local/display/' . $ownerId . '.webp') && count(json_decode(file_get_contents($root . '/api/storage/local/media.json'), true)['media']) === 2, 'Bei Telemetriefehler bleiben Index und Bild erhalten');
	unlink($root . '/mock/reject-save');
	[$status, $deleted] = request('media/manage.php', 'DELETE', json_encode(['id' => $ownerId, 'postRevision' => $freshContext['postRevision']]));
	expect($status === 200 && $deleted['posts'][0]['mediaIds'] === [$id], 'Nutzer entfernt nur eigenes Bild aus ThingsBoard-Post');
	expect($deleted['posts'][0]['content'] === 'Neuer Text direkt in ThingsBoard', 'Bildlöschung erhält aktuelle Telemetrie statt veralteten Cachetext');
	expect(!file_exists($root . '/api/storage/local/display/' . $ownerId . '.webp')
		&& !file_exists($root . '/api/storage/local/thumbnails/' . $ownerId . '.webp')
		&& count(json_decode(file_get_contents($root . '/api/storage/local/media.json'), true)['media']) === 1, 'Bildverwaltung entfernt beide Varianten und Indexeintrag');
	expect(request('media/manage.php', 'DELETE', json_encode(['id' => $ownerId]))[0] === 200, 'Wiederholte Bildlöschung ist idempotent');
	asUser('admin');
	$textContext = request('media/manage.php?id=' . $id)[1];
	expect($textContext['remainingImages'] === 0 && $textContext['hasText'], 'Löschvorschau erkennt verbleibenden Text ohne weitere Bilder');
	[$status, $deleted] = request('media/manage.php', 'DELETE', json_encode(['id' => $id]));
	expect($status === 200 && $deleted['posts'][0]['mediaIds'] === [] && $deleted['posts'][0]['content'] === 'Neuer Text direkt in ThingsBoard', 'Letztes Bild wird entfernt, Post mit Text bleibt erhalten');
	expect(request('posts/index.php')[1]['posts'][0]['mediaIds'] === [], 'Posts-Cache wird nach Bildlöschung aktualisiert');
	// Admins dürfen auch fremde unveröffentlichte Uploads entfernen.
	asUser('owner');
	[$status, $pendingUpload] = request('media/index.php', 'POST', $upload);
	expect($status === 201, 'Eigener weiterer Entwurf wird hochgeladen');
	asUser('other');
	expect(request('media/manage.php', 'DELETE', json_encode(['id' => $pendingUpload['media']['id']]))[0] === 200, 'Super-Wassermeister darf fremden unveröffentlichten Upload löschen');
	asUser('admin');
	$post['content'] = 'Jetzt mit Text';
	$post['mediaIds'] = [];
	expect(request('posts/index.php', 'PATCH', json_encode($post))[0] === 200, 'Anhänge werden erst beim Speichern entfernt');
	expect(request('media/file.php?id=' . $id)[0] === 404, 'Entferntes Bild ist nicht mehr abrufbar');
	expect(!file_exists($root . '/api/storage/local/display/' . $id . '.webp'), 'Entfernte Bilddatei wird gelöscht');
	[$status, $newUpload] = request('media/index.php', 'POST', $upload);
	expect($status === 201, 'Bestehender Post kann neue Bilder erhalten');
	$post['mediaIds'] = [$newUpload['media']['id']];
	expect(request('posts/index.php', 'PATCH', json_encode($post))[0] === 200, 'Neuer Anhang wird an bestehenden Post gebunden');
	expect(request('posts/index.php', 'DELETE', json_encode(['id' => $postId]))[0] === 200, 'Post wird gelöscht');
	expect(request('media/index.php')[1]['media'] === [], 'Löschen entfernt zugehörige Medien');
	expect(!file_exists($root . '/api/storage/local/display/' . $newUpload['media']['id'] . '.webp'), 'Löschen entfernt zugehörige Dateien');
	// Das letzte Bild eines reinen Bildposts entfernt auch den Telemetrieeintrag.
	asUser('owner');
	$imagePostId = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
	$upload['postId'] = $imagePostId;
	[$status, $firstImage] = request('media/index.php', 'POST', $upload);
	expect($status === 201, 'Erstes Bild für reinen Bildpost wird hochgeladen');
	[$status, $lastImage] = request('media/index.php', 'POST', $upload);
	expect($status === 201, 'Zweites Bild für reinen Bildpost wird hochgeladen');
	$imagePost = ['id' => $imagePostId, 'deviceId' => $device, 'content' => '', 'timestamp' => (int) floor(microtime(true) * 1000), 'mediaIds' => [$firstImage['media']['id'], $lastImage['media']['id']]];
	expect(request('posts/index.php', 'POST', json_encode($imagePost))[0] === 201, 'Reiner Bildpost mit zwei Bildern wird gespeichert');
	[$status, $deleted] = request('media/manage.php', 'DELETE', json_encode(['id' => $firstImage['media']['id']]));
	expect($status === 200 && $deleted['posts'][0]['mediaIds'] === [$lastImage['media']['id']] && $deleted['removedPostIds'] === [], 'Post ohne Text bleibt erhalten, solange ein weiteres Bild angehängt ist');
	[$status, $extraDraft] = request('media/index.php', 'POST', $upload);
	expect($status === 201, 'Zusätzlicher unveröffentlichter Upload wird vorbereitet');
	[$status, $deleted] = request('media/manage.php', 'DELETE', json_encode(['id' => $extraDraft['media']['id']]));
	expect($status === 200 && $deleted['posts'][0]['mediaIds'] === [$lastImage['media']['id']] && $deleted['removedPostIds'] === [], 'Entwurfsbild-Löschung löscht keinen unabhängigen veröffentlichten Post');
	file_put_contents($root . '/mock/reject-delete', 'once');
	expect(request('media/manage.php', 'DELETE', json_encode(['id' => $lastImage['media']['id']]))[0] === 502, 'Fehlgeschlagene automatische Post-Löschung wird gemeldet');
	expect(file_exists($root . '/api/storage/local/display/' . $lastImage['media']['id'] . '.webp')
		&& request('posts/index.php')[1]['posts'][0]['mediaIds'] === [$lastImage['media']['id']], 'Bei fehlgeschlagener Post-Löschung bleiben Post und Bild erhalten');
	unlink($root . '/mock/reject-delete');
	file_put_contents($root . '/mock/ignore-delete', 'once');
	expect(request('media/manage.php', 'DELETE', json_encode(['id' => $lastImage['media']['id']]))[0] === 502, 'HTTP 200 ohne gelöschte Telemetrie gilt nicht als Erfolg');
	expect(file_exists($root . '/api/storage/local/display/' . $lastImage['media']['id'] . '.webp'), 'Bild bleibt bis zur verifizierten Telemetrie-Löschung erhalten');
	unlink($root . '/mock/ignore-delete');
	// Auch in ThingsBoard vorhandener reiner Leerraum zählt nicht als Textinhalt.
	$entries = json_decode(file_get_contents($telemetryFile), true);
	foreach ($entries as &$entry) {
		$value = json_decode($entry['value'], true);
		$value['content'] = " \n\t ";
		$entry['value'] = json_encode($value);
	}
	unset($entry);
	file_put_contents($telemetryFile, json_encode($entries));
	[$status, $deleted] = request('media/manage.php', 'DELETE', json_encode(['id' => $lastImage['media']['id']]));
	expect($status === 200 && $deleted['posts'] === [] && $deleted['removedPostIds'] === [$imagePostId], 'Eigener letzter Bildanhang löscht leeren Post auch bei Leerraumtext');
	expect(request('posts/index.php')[1]['posts'] === [] && json_decode(file_get_contents($telemetryFile), true) === [], 'Automatisch gelöschter Post fehlt in ThingsBoard und Cache');
	expect(!file_exists($root . '/api/storage/local/display/' . $lastImage['media']['id'] . '.webp')
		&& !file_exists($root . '/api/storage/local/thumbnails/' . $lastImage['media']['id'] . '.webp'), 'Automatische Post-Löschung entfernt anschließend beide Bildvarianten');
	expect(request('media/manage.php?id=' . $lastImage['media']['id'])[0] === 404, 'Entferntes Bild hat keine Löschvorschau mehr');
	// Löschung in ThingsBoard erfolgt, aber die HTTP-Antwort geht verloren.
	$lostPostId = 'cccccccc-cccc-4ccc-8ccc-cccccccccccc';
	$upload['postId'] = $lostPostId;
	[$status, $lostImage] = request('media/index.php', 'POST', $upload);
	expect($status === 201, 'Bild für Löschwiederholung wird vorbereitet');
	$lostPost = ['id' => $lostPostId, 'deviceId' => $device, 'content' => '', 'timestamp' => (int) floor(microtime(true) * 1000), 'mediaIds' => [$lostImage['media']['id']]];
	expect(request('posts/index.php', 'POST', json_encode($lostPost))[0] === 201, 'Reiner Bildpost für Löschwiederholung wird gespeichert');
	file_put_contents($root . '/mock/fail-after-delete', 'once');
	expect(request('media/manage.php', 'DELETE', json_encode(['id' => $lostImage['media']['id']]))[0] === 502, 'Verlorene Antwort der automatischen Post-Löschung wird gemeldet');
	expect(file_exists($root . '/api/storage/local/display/' . $lostImage['media']['id'] . '.webp'), 'Bei verlorener Löschantwort bleibt das Bild für Wiederholung erhalten');
	[$status, $deleted] = request('media/manage.php', 'DELETE', json_encode(['id' => $lostImage['media']['id']]));
	expect($status === 200 && $deleted['removedPostIds'] === [$lostPostId]
		&& request('posts/index.php')[1]['posts'] === []
		&& !file_exists($root . '/api/storage/local/display/' . $lostImage['media']['id'] . '.webp'), 'Löschwiederholung repariert Cache und Dateien nach bereits gelöschtem Post');
	asUser('admin');
	file_put_contents($root . '/mock/fail-after-save', 'once');
	$retry = ['id' => '88888888-8888-4888-8888-888888888888', 'deviceId' => $device, 'content' => 'Verlorene Antwort', 'timestamp' => (int) floor(microtime(true) * 1000), 'mediaIds' => []];
	expect(request('posts/index.php', 'POST', json_encode($retry))[0] === 502, 'Simulierte verlorene Speicherantwort wird gemeldet');
	expect(request('posts/index.php', 'POST', json_encode($retry))[0] === 200, 'Wiederholung findet bereits gespeicherten ThingsBoard-Post');
	expect(count(json_decode(file_get_contents($root . '/mock/telemetry.json'), true)) === 1, 'Auch bei verlorener Antwort entsteht kein doppelter Telemetrieeintrag');
	config(false);
	$productionId = '99999999-9999-4999-8999-999999999999';
	$upload['postId'] = $productionId;
	[$status, $productionMedia] = request('media/index.php', 'POST', $upload);
	expect($status === 201 && $productionMedia['media']['environment'] === 'production', 'Produktionsupload nutzt regulären Medienindex');
	$productionPost = ['id' => $productionId, 'deviceId' => $device, 'content' => 'Produktionsbild', 'timestamp' => (int) floor(microtime(true) * 1000), 'mediaIds' => [$productionMedia['media']['id']]];
	expect(request('posts/index.php', 'POST', json_encode($productionPost))[0] === 201, 'Produktionspost mit Bild wird gespeichert');
	config(true);
	$localMedia = request('media/index.php')[1]['media'];
	expect(count($localMedia) === 1 && $localMedia[0]['id'] === $productionMedia['media']['id'], 'Lokal wird Produktionsmedium aus der regulären Ablage angezeigt');
	expect(request('media/file.php?id=' . $productionMedia['media']['id'])[0] === 200, 'Lokal wird Produktionsbild lokal ausgeliefert');
	expect(request('posts/index.php', 'PATCH', json_encode($productionPost))[0] === 403, 'Produktionspost ist lokal nicht bearbeitbar');
	expect(request('posts/index.php', 'DELETE', json_encode(['id' => $productionId]))[0] === 403, 'Produktionspost ist lokal nicht löschbar');
	expect(request('media/manage.php')[1]['media'][0]['canDelete'] === false, 'Produktionsmedium ist in lokaler Verwaltung nur lesbar');
	expect(request('media/manage.php', 'DELETE', json_encode(['id' => $productionMedia['media']['id']]))[0] === 403, 'Auch Admin darf Produktionsbild lokal nicht löschen');
	$upload['postId'] = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
	[$status, $orphan] = request('media/index.php', 'POST', $upload);
	expect($status === 201, 'Abgebrochener lokaler Entwurf kann vorbereitet werden');
	$indexFile = $root . '/api/storage/local/media.json';
	$index = json_decode(file_get_contents($indexFile), true);
	foreach ($index['media'] as &$entry) $entry['uploadedAt'] = (time() - 90000) * 1000;
	unset($entry);
	file_put_contents($indexFile, json_encode($index));
	$cleanupCode = 'require ' . var_export($root . '/api/posts/service.php', true) . '; require ' . var_export($root . '/api/media/cleanup.php', true) . '; postsWithLock(static fn () => mediaCleanup(postsReadCache()["posts"]));';
	$cleanup = proc_open([PHP_BINARY, '-r', $cleanupCode], $descriptors, $pipes, $root);
	expect(proc_close($cleanup) === 0, 'Bereinigung läuft mit aktuellem Posts-Cache');
	expect(!file_exists($root . '/api/storage/local/display/' . $orphan['media']['id'] . '.webp'), 'Abgebrochene Uploads werden nach 24 Stunden entfernt');
	expect(file_exists($root . '/api/storage/display/' . $productionMedia['media']['id'] . '.webp'), 'Lokale Bereinigung erhält Produktionsbilder');
	config(false);
	expect(count(request('media/manage.php')[1]['media']) === 1, 'Produktionsverwaltung enthält keine lokalen Uploads');
	expect(request('media/manage.php', 'DELETE', json_encode(['id' => $productionMedia['media']['id']]))[0] === 200, 'Produktionsbild kann in Produktion gelöscht werden');
	expect(request('media/index.php')[1]['media'] === [], 'Gelöschtes Produktionsbild verschwindet aus öffentlichem Index');
} catch (Throwable $error) {
	fwrite(STDERR, $error->getMessage() . PHP_EOL . file_get_contents($root . '/server.log'));
	$exitCode = 1;
} finally {
	foreach ($servers as $server) if (is_resource($server)) { proc_terminate($server); proc_close($server); }
	removeFixture($root);
}
exit($exitCode ?? 0);
