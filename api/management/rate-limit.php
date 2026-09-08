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
