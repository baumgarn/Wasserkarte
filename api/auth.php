<?php

function getRefreshRequestSource(): ?string
{
	if (PHP_SAPI === 'cli') {
		return null;
	}

	$remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
	return $remoteAddr !== '' ? $remoteAddr : null;
}

function getRefreshBlocksFile(): string
{
	return CACHE_DIR . '/refresh_blocks.json';
}

function loadRefreshBlocks(): array
{
	$blocksFile = getRefreshBlocksFile();
	if (!is_file($blocksFile)) {
		return [];
	}

	$blockData = json_decode((string) file_get_contents($blocksFile), true);
	return is_array($blockData) ? $blockData : [];
}

function saveRefreshBlocks(array $blocks): void
{
	$blocksFile = getRefreshBlocksFile();
	file_put_contents($blocksFile, json_encode($blocks, JSON_PRETTY_PRINT));
	@chmod($blocksFile, 0600);
}

function pruneRefreshBlocks(array $blocks): array
{
	$now = time();
	$prunedBlocks = [];

	foreach ($blocks as $source => $blockData) {
		$blockedUntil = isset($blockData['blocked_until']) ? (int) $blockData['blocked_until'] : 0;
		if ($blockedUntil > $now) {
			$prunedBlocks[$source] = $blockData;
		}
	}

	return $prunedBlocks;
}

function getActiveRefreshBlock(string $source): ?array
{
	$blocks = pruneRefreshBlocks(loadRefreshBlocks());
	saveRefreshBlocks($blocks);

	return isset($blocks[$source]) && is_array($blocks[$source]) ? $blocks[$source] : null;
}

function registerRefreshBlock(string $source): void
{
	$blocks = pruneRefreshBlocks(loadRefreshBlocks());
	$blocks[$source] = [
		'source' => $source,
		'blocked_until' => time() + (defined('REFRESH_BLOCK_SECONDS') ? (int) REFRESH_BLOCK_SECONDS : 300),
	];

	saveRefreshBlocks($blocks);
}

function requireRefreshSecretIfNeeded(): void
{
	if (!isset($_GET['refresh'])) {
		return;
	}

	$source = getRefreshRequestSource();
	if ($source !== null) {
		$activeBlock = getActiveRefreshBlock($source);
		if ($activeBlock !== null) {
			http_response_code(429);
			header('Content-Type: application/json');
			header('Retry-After: ' . max(1, ((int) $activeBlock['blocked_until']) - time()));
			echo json_encode(['error' => 'Refresh requests temporarily blocked for this source'], JSON_PRETTY_PRINT);
			exit;
		}
	}

	$providedSecret = isset($_GET['secret']) ? (string) $_GET['secret'] : '';
	$configuredSecret = defined('REFRESH_SECRET') ? (string) REFRESH_SECRET : '';

	if ($configuredSecret === '' || !hash_equals($configuredSecret, $providedSecret)) {
		if ($source !== null) {
			registerRefreshBlock($source);
		}

		http_response_code(403);
		header('Content-Type: application/json');
		echo json_encode(['error' => 'Invalid refresh secret'], JSON_PRETTY_PRINT);
		exit;
	}
}

function getAuthToken() {
    $tokenData = getTokenCache();
    if ($tokenData && !isTokenExpired($tokenData)) {
        return $tokenData['token'];
    }
    
    $newToken = loginToThingsBoard();
    if ($newToken) {
        saveTokenCache($newToken);
        return $newToken;
    }
    
    return null;
}

function loginToThingsBoard() {
    $url = THINGSBOARD_URL . "/auth/login";
    $data = json_encode([
    	"username" => USERNAME,
        "password" => PASSWORD
    ]);
    
    $options = [
        "http" => [
            "header" => "Content-Type: application/json\r\n",
            "method" => "POST",
            "content" => $data
        ]
    ];
      $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);

    if ($result === false) {
        echo "Fehler beim Login: " . error_get_last()['message'];
        return null;
    }

    $response = json_decode($result, true);
    if (!isset($response["token"])) {
        echo "Fehler: Kein Token in der Antwort vorhanden. Antwort: " . print_r($response, true);
        return null;
    }

    return $response["token"];
}

function getTokenCache() {
    if (file_exists(TOKEN_FILE)) {
        $fileData = file_get_contents(TOKEN_FILE);
        return json_decode($fileData, true);
    }
    return null;
}

function isTokenExpired($tokenData) {
    if (!is_array($tokenData) || !isset($tokenData['timestamp'])) {
        return true;
    }
    $tokenAge = time() - $tokenData['timestamp'];
    return $tokenAge >= 900; // 15 Minutes
}

function saveTokenCache($token)
{
	$cache = [
		'timestamp' => time(),
		'token' => $token
	];
	file_put_contents(TOKEN_FILE, json_encode($cache));
	chmod(TOKEN_FILE, 0600);
}
