<?php

function managementRequestSource(): string
{
	// X-Forwarded-For wird bewusst nicht ausgewertet: Es ist nur hinter einem
	// ausdrücklich konfigurierten Reverse Proxy vertrauenswürdig.
	return (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

function managementRateLimitFile(): string
{
	return CACHE_DIR . '/management_rate_limits.json';
}

function managementRateLimitTransaction(callable $callback)
{
	$file = managementRateLimitFile();
	$handle = fopen($file, 'c+');
	if ($handle === false) {
		$entries = [];
		return $callback($entries);
	}

	@chmod($file, 0600);
	flock($handle, LOCK_EX);
	rewind($handle);
	$contents = stream_get_contents($handle);
	$entries = json_decode(is_string($contents) ? $contents : '', true);
	$entries = is_array($entries) ? $entries : [];
	$result = $callback($entries);
	ftruncate($handle, 0);
	rewind($handle);
	fwrite($handle, json_encode($entries, JSON_UNESCAPED_UNICODE));
	fflush($handle);
	flock($handle, LOCK_UN);
	fclose($handle);

	return $result;
}

function managementRateLimitKey(string $scope, string $subject): string
{
	// In der Datei liegen keine IP-Adressen oder E-Mail-Adressen im Klartext.
	return hash('sha256', $scope . "\0" . $subject);
}

function managementRateLimitPrune(array $entries, int $windowSeconds): array
{
	$now = time();
	$cutoff = $now - $windowSeconds;
	foreach ($entries as $key => $entry) {
		// Account-Generationen bleiben erhalten, damit alte IP-Sperren nicht wieder aufleben.
		if (isset($entry['reset_version'])) continue;
		$attempts = is_array($entry['attempts'] ?? null) ? $entry['attempts'] : [];
		$attempts = array_values(array_filter($attempts, static fn ($attempt) => is_int($attempt) && $attempt > $cutoff));
		$blockedUntil = (int) ($entry['blocked_until'] ?? 0);
		if ($blockedUntil <= $now && $attempts === []) {
			unset($entries[$key]);
			continue;
		}
		$entries[$key] = ['attempts' => $attempts, 'blocked_until' => $blockedUntil];
	}
	return $entries;
}

function managementRateLimitRetryAfter(string $scope, string $subject, int $windowSeconds): int
{
	$key = managementRateLimitKey($scope, $subject);
	return managementRateLimitTransaction(static function (array &$entries) use ($key, $windowSeconds): int {
		$entries = managementRateLimitPrune($entries, $windowSeconds);
		$blockedUntil = (int) ($entries[$key]['blocked_until'] ?? 0);
		return max(0, $blockedUntil - time());
	});
}

function managementRegisterRateLimitFailure(string $scope, string $subject, int $maxAttempts, int $windowSeconds, int $blockSeconds): void
{
	$key = managementRateLimitKey($scope, $subject);
	managementRateLimitTransaction(static function (array &$entries) use ($key, $maxAttempts, $windowSeconds, $blockSeconds): void {
		$entries = managementRateLimitPrune($entries, $windowSeconds);
		$entry = $entries[$key] ?? ['attempts' => [], 'blocked_until' => 0];
		$entry['attempts'][] = time();
		if (count($entry['attempts']) >= $maxAttempts) {
			$entry['attempts'] = [];
			$entry['blocked_until'] = time() + $blockSeconds;
		}
		$entries[$key] = $entry;
	});
}

function managementClearRateLimit(string $scope, string $subject, int $windowSeconds): void
{
	$key = managementRateLimitKey($scope, $subject);
	managementRateLimitTransaction(static function (array &$entries) use ($key, $windowSeconds): void {
		$entries = managementRateLimitPrune($entries, $windowSeconds);
		unset($entries[$key]);
	});
}

// Eine neue Generation verwirft auch alte E-Mail/IP-Schlüssel, ohne die IP
// des Nutzers kennen zu müssen. Allgemeine IP-Limits bleiben davon unberührt.
function managementLoginSubject(string $email): string
{
	$email = mb_strtolower(trim($email));
	$key = managementRateLimitKey('login-reset', $email);
	$version = managementRateLimitTransaction(static fn (array &$entries): string => $entries[$key]['reset_version'] ?? '');
	return managementRequestSource() . "\0" . $email . ($version !== '' ? "\0" . $version : '');
}

function managementResetAccountLoginLimits(string $email): void
{
	$key = managementRateLimitKey('login-reset', mb_strtolower(trim($email)));
	managementRateLimitTransaction(static function (array &$entries) use ($key): void {
		$entries[$key] = ['reset_version' => bin2hex(random_bytes(16))];
	});
}
