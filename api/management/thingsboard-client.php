<?php

function thingsBoardManagementRequest(string $method, string $path, ?array $payload = null, ?string $token = null): array
{
	$headers = ["Content-Type: application/json"];
	if ($token !== null) {
		$headers[] = "X-Authorization: Bearer {$token}";
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

function loginManagementUser(string $email, string $password): ?array
{
	$login = thingsBoardManagementRequest('POST', '/auth/login', [
		'username' => $email,
		'password' => $password,
	]);
	$token = $login['body']['token'] ?? null;
	if ($login['status'] !== 200 || !is_string($token) || $token === '') {
		return null;
	}

	$profile = thingsBoardManagementRequest('GET', '/auth/user', null, $token);
	if ($profile['status'] !== 200 || !is_array($profile['body'])) {
		return null;
	}

	return ['profile' => $profile['body'], 'token' => $token];
}
