<?php

function thingsBoardManagementRequest(string $method, string $path, ?array $payload = null, ?string $authorization = null): array
{
	$headers = ["Content-Type: application/json"];
	if ($authorization !== null) {
		$headers[] = "X-Authorization: {$authorization}";
	}

	$options = [
		'http' => [
			'header' => implode("\r\n", $headers) . "\r\n",
			'method' => $method,
			'ignore_errors' => true,
			'timeout' => 15,
		],
	];
	if ($payload !== null) {
		$options['http']['content'] = json_encode($payload, JSON_UNESCAPED_UNICODE);
	}

	$result = @file_get_contents(rtrim(THINGSBOARD_URL, '/') . $path, false, stream_context_create($options));
	$status = 0;
	foreach ($http_response_header ?? [] as $header) {
		if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches)) {
			$status = (int) $matches[1];
			break;
		}
	}

	return [
		'status' => $status,
		'rawBody' => $result === false ? null : $result,
		'body' => $result === false ? null : json_decode($result, true),
	];
}

function loginManagementUser(string $email, string $password, ?array &$failure = null): ?array
{
	$failure = ['kind' => 'service', 'status' => 502, 'message' => 'Der Anmeldedienst ist momentan nicht erreichbar.'];
	$login = thingsBoardManagementRequest('POST', '/auth/login', [
		'username' => $email,
		'password' => $password,
	]);
	$token = $login['body']['token'] ?? null;
	if ($login['status'] !== 200 || !is_string($token) || $token === '') {
		$message = strtolower((string) ($login['body']['message'] ?? ''));
		if ($login['status'] === 429) {
			$failure = ['kind' => 'limited', 'status' => 429, 'message' => 'Zu viele Anmeldeversuche. Bitte später erneut versuchen.'];
		} elseif (str_contains($message, 'locked') || str_contains($message, 'disabled') || str_contains($message, 'not active')) {
			$failure = ['kind' => 'locked', 'status' => 403, 'message' => 'Der Account ist gesperrt oder noch nicht aktiviert. Bitte eine neue Zugangsmail beim Admin anfordern.'];
		} elseif ($login['status'] === 401 && (str_contains($message, 'invalid username or password') || str_contains($message, 'bad credentials') || $message === 'authentication failed')) {
			$failure = ['kind' => 'credentials', 'status' => 401, 'message' => 'Email-Adresse oder Passwort ist nicht korrekt.'];
		}
		return null;
	}

	$profile = thingsBoardManagementRequest('GET', '/auth/user', null, 'Bearer ' . $token);
	if ($profile['status'] !== 200 || !is_array($profile['body'])) {
		return null;
	}

	return ['profile' => $profile['body'], 'token' => $token];
}
