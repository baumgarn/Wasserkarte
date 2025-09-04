<?php

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
