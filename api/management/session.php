<?php

function managementUsesSecureCookie(): bool
{
	if (defined('SESSION_COOKIE_SECURE')) {
		return (bool) SESSION_COOKIE_SECURE;
	}

	return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
		|| (($_SERVER['SERVER_PORT'] ?? null) === '443');
}

function managementRememberLifetime(): int
{
	$defaultLifetime = 60 * 60 * 24 * 30;
	return defined('REMEMBER_ME_SESSION_LIFETIME')
		? max(3600, (int) REMEMBER_ME_SESSION_LIFETIME)
		: $defaultLifetime;
}

function managementSessionDirectory(): ?string
{
	if (session_module_name() !== 'files') {
		return null;
	}

	$directory = defined('MANAGEMENT_SESSION_DIR')
		? rtrim((string) MANAGEMENT_SESSION_DIR, DIRECTORY_SEPARATOR)
		: rtrim(CACHE_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'sessions';
	if ($directory === '') {
		return null;
	}

	if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
		error_log('Wasserkarte management sessions could not create ' . $directory);
		return null;
	}

	@chmod($directory, 0700);
	return is_writable($directory) ? $directory : null;
}

function startManagementSession(?bool $remember = null): void
{
	if (session_status() === PHP_SESSION_ACTIVE) {
		return;
	}

	// Der Hoster-Standard kann Sessions anderer Sites gemeinsam nach wenigen Minuten
	// aufräumen. Ein eigenes Verzeichnis in CACHE_DIR wird beim Deploy nicht ersetzt.
	$sessionDirectory = managementSessionDirectory();
	if ($sessionDirectory !== null) {
		session_save_path($sessionDirectory);
	}

	// Persistente Sitzungen werden zusätzlich über expires_at geprüft.
	ini_set('session.gc_maxlifetime', (string) managementRememberLifetime());
	ini_set('session.use_strict_mode', '1');
	session_name('wasserkarte_session');
	session_set_cookie_params([
		'lifetime' => $remember === true ? managementRememberLifetime() : 0,
		'path' => '/',
		'domain' => '',
		'secure' => managementUsesSecureCookie(),
		'httponly' => true,
		'samesite' => 'Lax',
	]);
	session_start();
}

function setManagementIdentity(array $identity, bool $remember = false): void
{
	startManagementSession($remember);
	session_regenerate_id(true);
	$_SESSION['management_identity'] = $identity;
	$_SESSION['management_csrf_token'] = bin2hex(random_bytes(32));
	$_SESSION['management_session_expires_at'] = $remember ? time() + managementRememberLifetime() : null;
}

function getManagementIdentity(): ?array
{
	startManagementSession();
	$expiresAt = $_SESSION['management_session_expires_at'] ?? null;
	if (is_int($expiresAt) && $expiresAt <= time()) {
		destroyManagementSession();
		return null;
	}
	$identity = $_SESSION['management_identity'] ?? null;
	return is_array($identity) ? $identity : null;
}

function getManagementCsrfToken(): ?string
{
	startManagementSession();
	$token = $_SESSION['management_csrf_token'] ?? null;
	return is_string($token) ? $token : null;
}

function hasValidManagementCsrfToken(): bool
{
	$provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
	$expected = getManagementCsrfToken();
	return is_string($provided) && $expected !== null && hash_equals($expected, $provided);
}

function updateManagementIdentity(array $changes): void
{
	startManagementSession();
	$identity = getManagementIdentity();
	if ($identity === null) {
		return;
	}
	$_SESSION['management_identity'] = array_merge($identity, $changes);
}

function destroyManagementSession(): void
{
	startManagementSession();
	$_SESSION = [];

	if (ini_get('session.use_cookies')) {
		$params = session_get_cookie_params();
		setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
	}

	session_destroy();
}
