<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/thingsboard-client.php';
require_once __DIR__ . '/rate-limit.php';
require_once __DIR__ . '/email-settings.php';
require_once __DIR__ . '/../posts/service.php';

function managementRespond(array $payload, int $status = 200): void
{
	http_response_code($status);
	header('Content-Type: application/json; charset=utf-8');
	header('Cache-Control: no-store');
	echo json_encode($payload, JSON_UNESCAPED_UNICODE);
	exit;
}

function managementReadJsonBody(): ?array
{
	$body = file_get_contents('php://input');
	if ($body === false || strlen($body) > 16384) {
		return null;
	}
	$value = json_decode($body, true);
	return is_array($value) ? $value : null;
}

function managementPermissions(string $authority): array
{
	$isAdmin = $authority === 'TENANT_ADMIN' || $authority === 'SYS_ADMIN';
	return [
		'editOwnLocations' => false,
		'manageLocations' => $isAdmin,
		'manageUsers' => $isAdmin,
	];
}

function managementRequireIdentity(): array
{
	$identity = getManagementIdentity();
	if ($identity === null) {
		managementRespond(['error' => 'Bitte zuerst anmelden.'], 401);
	}
	return $identity;
}

function managementRequireCsrfToken(): void
{
	if (!hasValidManagementCsrfToken()) {
		managementRespond(['error' => 'Die Sitzung muss erneut geladen werden.'], 403);
	}
}

function managementRequireAdmin(): array
{
	$identity = managementRequireIdentity();
	$authority = $identity['thingsboardAuthority'] ?? '';
	if ($authority !== 'TENANT_ADMIN' && $authority !== 'SYS_ADMIN') {
		managementRespond(['error' => 'Diese Funktion erfordert einen Administrator-Account.'], 403);
	}
	return $identity;
}

function managementServiceToken(): string
{
	$authorization = getThingsBoardAuthorization();
	if (!is_string($authorization) || $authorization === '') {
		managementRespond(['error' => 'ThingsBoard ist momentan nicht erreichbar.'], 502);
	}
	return $authorization;
}

function managementCustomerId(): string
{
	$customerId = defined('WASSERKARTE_CUSTOMER_ID') ? trim((string) WASSERKARTE_CUSTOMER_ID) : '';
	if (!preg_match('/^[a-f0-9-]{36}$/i', $customerId)) {
		managementRespond(['error' => 'WASSERKARTE_CUSTOMER_ID ist nicht konfiguriert.'], 500);
	}
	return $customerId;
}

function managementRequireCustomerUser(string $userId, string $token): array
{
	if (!preg_match('/^[a-f0-9-]{36}$/i', $userId)) {
		managementRespond(['error' => 'Ungültige Account-ID.'], 400);
	}

	$response = thingsBoardManagementRequest('GET', '/user/' . rawurlencode($userId), null, $token);
	$user = $response['body'];
	if (
		$response['status'] !== 200
		|| !is_array($user)
		|| ($user['authority'] ?? '') !== 'CUSTOMER_USER'
		|| ($user['customerId']['id'] ?? '') !== managementCustomerId()
	) {
		managementRespond(['error' => 'Dieser Account gehört nicht zur Wasserkarte.'], 404);
	}

	return $user;
}

function managementLocationIdsFromAttribute($value): array
{
	if (is_string($value)) {
		$decoded = json_decode($value, true);
		$value = is_array($decoded) ? $decoded : [];
	}
	if (!is_array($value)) {
		return [];
	}

	return array_values(array_filter($value, static fn ($id) => is_string($id) && preg_match('/^[a-f0-9-]{36}$/i', $id)));
}

function managementUserRoleFromValue($value): string
{
	return in_array($value, ['wassermeister', 'super_wassermeister'], true) ? $value : 'none';
}

function managementUserPermissions(string $userId, string $token): array
{
	$response = thingsBoardManagementRequest(
		'GET',
		'/plugins/telemetry/USER/' . rawurlencode($userId) . '/values/attributes/SERVER_SCOPE?keys=wasserkarte_role,wasserkarte_locations',
		null,
		$token
	);
	if ($response['status'] !== 200 || !is_array($response['body'])) {
		return ['role' => 'none', 'locations' => []];
	}

	$role = 'none';
	$locations = [];
	foreach ($response['body'] as $attribute) {
		if (is_array($attribute) && ($attribute['key'] ?? '') === 'wasserkarte_role') {
			$role = managementUserRoleFromValue($attribute['value'] ?? null);
		}
		if (is_array($attribute) && ($attribute['key'] ?? '') === 'wasserkarte_locations') {
			$locations = managementLocationIdsFromAttribute($attribute['value'] ?? null);
		}
	}

	return ['role' => $role, 'locations' => $locations];
}

function managementUserRole(string $userId, string $token): string
{
	return managementUserPermissions($userId, $token)['role'];
}

function managementUserSettingsFromValue($value): array
{
	if (is_string($value)) {
		$value = json_decode($value, true);
	}
	if (!is_array($value)) {
		return [];
	}

	$settings = [];
	foreach ([
		'focusMode',
		'showDataGaps',
		'showErrors',
		'debugAttributes',
		'showInfoOnStart',
		'newLocationProperties',
		'tableview_col_bookmarks',
		'tableview_col_attributes',
		'tableview_col_nfkavg',
		'tableview_col_von',
		'tableview_compact',
		'tableview_bookmarksontop',
		'tableview_showdepths',
	] as $key) {
		if (array_key_exists($key, $value) && is_bool($value[$key])) {
			$settings[$key] = $value[$key];
		}
	}
	if (isset($value['colorScheme']) && in_array($value['colorScheme'], ['normal', 'dwd', 'blau'], true)) {
		$settings['colorScheme'] = $value['colorScheme'];
	}
	if (isset($value['tableview_timelinerange']) && in_array($value['tableview_timelinerange'], ['all', '365d', '180d', '90d'], true)) {
		$settings['tableview_timelinerange'] = $value['tableview_timelinerange'];
	}
	if (isset($value['tableview_timelinestyle']) && $value['tableview_timelinestyle'] === 'nfk_avg') {
		$settings['tableview_timelinestyle'] = $value['tableview_timelinestyle'];
	}
	if (array_key_exists('bookmarks', $value) && is_array($value['bookmarks'])) {
		$settings['bookmarks'] = array_values(array_unique(array_filter(
			$value['bookmarks'],
			static fn ($id) => is_string($id) && preg_match('/^[a-f0-9-]{36}$/i', $id)
		)));
		if (count($settings['bookmarks']) > 500) {
			$settings['bookmarks'] = array_slice($settings['bookmarks'], 0, 500);
		}
	}

	return $settings;
}

function managementProfileAttributes(string $userId, string $token): array
{
	$response = thingsBoardManagementRequest('GET', '/plugins/telemetry/USER/' . rawurlencode($userId)
		. '/values/attributes/SERVER_SCOPE?keys=wasserkarte_settings,wasserkarte_role,wasserkarte_locations,wasserkarte_email_settings', null, $token);
	if ($response['status'] !== 200 || !is_array($response['body'])) return [];
	$attributes = array_column($response['body'], 'value', 'key');
	$locations = managementLocationIdsFromAttribute($attributes['wasserkarte_locations'] ?? []);
	return [
		'wasserkarteSettings' => managementUserSettingsFromValue($attributes['wasserkarte_settings'] ?? []),
		'wasserkarteRole' => managementUserRoleFromValue($attributes['wasserkarte_role'] ?? null),
		'wasserkarteLocations' => $locations,
		'wasserkarteEmailSettings' => array_key_exists('wasserkarte_email_settings', $attributes)
			? managementEmailSettingsFromValue($attributes['wasserkarte_email_settings'])
			: ['enabled' => false, 'threshold' => 50, 'style' => 'detailed', 'locations' => array_values(array_unique($locations))],
	];
}

function managementMailEnabled(): bool
{
	return defined('MAIL_ENABLED') && MAIL_ENABLED === true;
}

function managementLoginLimit(string $name, int $default): int
{
	return defined($name) ? max(1, (int) constant($name)) : $default;
}

function managementRequireLoginNotRateLimited(string $email): void
{
	$source = managementRequestSource();
	$window = managementLoginLimit('MANAGEMENT_LOGIN_WINDOW_SECONDS', 900);
	$retryAfter = max(
		managementRateLimitRetryAfter('login-ip', $source, $window),
		managementRateLimitRetryAfter('login-email-ip', managementLoginSubject($email), $window)
	);
	if ($retryAfter > 0) {
		header('Retry-After: ' . $retryAfter);
		managementRespond(['error' => 'Zu viele Anmeldeversuche. Bitte in ' . (int) ceil($retryAfter / 60) . ' Min. erneut versuchen.'], 429);
	}
}

function managementRegisterFailedLogin(string $email): void
{
	$source = managementRequestSource();
	$window = managementLoginLimit('MANAGEMENT_LOGIN_WINDOW_SECONDS', 900);
	$block = managementLoginLimit('MANAGEMENT_LOGIN_BLOCK_SECONDS', 900);
	managementRegisterRateLimitFailure('login-ip', $source, managementLoginLimit('MANAGEMENT_LOGIN_IP_MAX_ATTEMPTS', 20), $window, $block);
	managementRegisterRateLimitFailure('login-email-ip', managementLoginSubject($email), managementLoginLimit('MANAGEMENT_LOGIN_EMAIL_MAX_ATTEMPTS', 5), $window, $block);
}

function managementClearFailedLogin(string $email): void
{
	managementClearRateLimit('login-email-ip', managementLoginSubject($email), managementLoginLimit('MANAGEMENT_LOGIN_WINDOW_SECONDS', 900));
}

function managementActivationUrl(string $thingsBoardLink): ?string
{
	$query = parse_url($thingsBoardLink, PHP_URL_QUERY);
	parse_str(is_string($query) ? $query : '', $parameters);
	$activateToken = $parameters['activateToken'] ?? '';
	$baseUrl = defined('WASSERKARTE_URL') ? rtrim((string) WASSERKARTE_URL, '/') : '';
	if (!is_string($activateToken) || $activateToken === '' || $baseUrl === '') {
		return null;
	}

	return $baseUrl . '/aktivieren?activateToken=' . rawurlencode($activateToken);
}

function managementSendActivationMail(string $email, string $firstName, string $activationLink): bool
{
	$from = defined('MAIL_FROM') ? trim((string) MAIL_FROM) : '';
	if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
		return false;
	}
	$name = $firstName !== '' ? $firstName : 'Hallo';
	$subject = 'Aktiviere deinen Wasserkarte-Account';
	$message = "{$name},\n\nbitte aktiviere deinen Wasserkarte-Account und setze ein Passwort:\n\n{$activationLink}\n\nDieser Link ist zeitlich begrenzt gültig.\n";
	$headers = [
		'From: Wasserkarte <' . $from . '>',
		'Content-Type: text/plain; charset=UTF-8',
	];

	return mail($email, $subject, $message, implode("\r\n", $headers));
}

function managementSessionPayload(bool $refresh = false): array
{
	$identity = getManagementIdentity();
	if ($identity === null) {
		return ['authenticated' => false];
	}
	if ($refresh || !array_key_exists('wasserkarteSettings', $identity) || managementEmailSettingsFromValue($identity['wasserkarteEmailSettings'] ?? null) === null
		|| !array_key_exists('wasserkarteLocations', $identity)) {
		$identity = array_merge($identity, managementProfileAttributes((string) $identity['id'], managementServiceToken()));
		updateManagementIdentity($identity);
	}

	return [
		'authenticated' => true,
		'user' => [
			'id' => $identity['id'],
			'email' => $identity['email'],
			'firstName' => $identity['firstName'],
			'lastName' => $identity['lastName'],
			'thingsboardAuthority' => $identity['thingsboardAuthority'],
			'wasserkarteRole' => $identity['wasserkarteRole'] ?? 'none',
			'wasserkarteLocations' => $identity['wasserkarteLocations'] ?? [],
			'settings' => $identity['wasserkarteSettings'] ?? [],
			'emailSettings' => $identity['wasserkarteEmailSettings'] ?? null,
		],
		'permissions' => managementPermissions($identity['thingsboardAuthority']),
		'csrfToken' => getManagementCsrfToken(),
	];
}

$action = $_GET['action'] ?? '';
if ($action === 'preview-email' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	managementRequireIdentity();
	managementRequireCsrfToken();
	$input = managementReadJsonBody();
	$settings = managementEmailSettingsFromValue($input['settings'] ?? null);
	if ($settings === null) {
		managementRespond(['error' => 'Ungültige E-Mail-Einstellungen.'], 400);
	}
	require_once __DIR__ . '/../notifications/service.php';
	try {
		[$devices, $telemetry] = notificationData(false);
		$day = notificationDay();
		$rows = notificationRows($settings, $devices, $telemetry, $day);
		$message = notificationMessage($rows, $day, $settings['threshold'], $settings['style']);
		managementRespond(['html' => $message['html']]);
	} catch (Throwable $error) {
		managementRespond(['error' => $error->getMessage()], 502);
	}
}

if ($action === 'test-email' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$identity = managementRequireIdentity();
	managementRequireCsrfToken();
	$input = managementReadJsonBody();
	$settings = managementEmailSettingsFromValue($input['settings'] ?? null);
	if ($settings === null || !$settings['locations']) {
		managementRespond(['error' => 'Bitte mindestens einen Standort auswählen.'], 400);
	}
	require_once __DIR__ . '/../notifications/service.php';
	try {
		notificationTest(managementServiceToken(), $identity['id'], $settings);
		managementRespond(['success' => true]);
	} catch (Throwable $error) {
		managementRespond(['error' => $error->getMessage()], 502);
	}
}

if ($action === 'email-settings' && in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
	$identity = managementRequireIdentity();
	$isSave = $_SERVER['REQUEST_METHOD'] === 'POST';
	if ($isSave) {
		managementRequireCsrfToken();
		$input = managementReadJsonBody();
		$settings = managementEmailSettingsFromValue($input['settings'] ?? null);
		if ($settings === null) {
			managementRespond(['error' => 'Ungültige E-Mail-Einstellungen.'], 400);
		}
	}
	$token = managementServiceToken();
	$path = '/plugins/telemetry/USER/' . rawurlencode($identity['id']);
	if ($isSave) {
		$response = thingsBoardManagementRequest('POST', $path . '/attributes/SERVER_SCOPE', [
			'wasserkarte_email_settings' => $settings,
		], $token);
		if ($response['status'] !== 200) {
			managementRespond(['error' => 'E-Mail-Einstellungen konnten nicht gespeichert werden.'], 502);
		}
	} else {
		$response = thingsBoardManagementRequest('GET', $path . '/values/attributes/SERVER_SCOPE?keys=wasserkarte_email_settings,wasserkarte_locations', null, $token);
		if ($response['status'] !== 200 || !is_array($response['body'])) {
			managementRespond(['error' => 'E-Mail-Einstellungen konnten nicht geladen werden.'], 502);
		}
		$attributes = array_column($response['body'], 'value', 'key');
		if (array_key_exists('wasserkarte_email_settings', $attributes)) {
			$settings = managementEmailSettingsFromValue($attributes['wasserkarte_email_settings']);
			if ($settings === null) {
				managementRespond(['error' => 'Die gespeicherten E-Mail-Einstellungen sind ungültig.'], 502);
			}
		} else {
			$settings = ['enabled' => false, 'threshold' => 50, 'style' => 'detailed', 'locations' =>
				array_values(array_unique(managementLocationIdsFromAttribute($attributes['wasserkarte_locations'] ?? [])))
			];
		}
	}
	updateManagementIdentity(['wasserkarteEmailSettings' => $settings]);
	managementRespond(['settings' => $settings]);
}

if ($action === 'me' && $_SERVER['REQUEST_METHOD'] === 'GET') {
	managementRespond(managementSessionPayload(true));
}

if ($action === 'users' && $_SERVER['REQUEST_METHOD'] === 'GET') {
	managementRequireAdmin();
	$customerId = managementCustomerId();
	$token = managementServiceToken();
	$users = [];
	$page = 0;
	$postCounts = [];
	$mediaCounts = [];
	$postsCache = postsReadCache() ?? postsRebuildCache($token);
	foreach ($postsCache['posts'] as $post) {
		if (!postsLocalMode() && isset($post['environment'])) continue;
		$authorId = $post['authorUserId'] ?? '';
		$postCounts[$authorId] = ($postCounts[$authorId] ?? 0) + 1;
	}
	foreach (postsLocalMode() ? [false, true] : [false] as $local) {
		foreach (mediaReadIndex($local)['media'] as $item) {
			if (!is_array($item) || !mediaValidId($item['id'] ?? null) || !mediaInEnvironment($item, $local)) continue;
			$authorId = $item['authorUserId'] ?? '';
			$mediaCounts[$authorId] = ($mediaCounts[$authorId] ?? 0) + 1;
		}
	}

	do {
		$response = thingsBoardManagementRequest(
			'GET',
			'/customer/' . rawurlencode($customerId) . '/users?pageSize=100&page=' . $page . '&sortProperty=createdTime&sortOrder=DESC',
			null,
			$token
		);
		if ($response['status'] !== 200 || !is_array($response['body'])) {
			managementRespond(['error' => 'Nutzerliste konnte nicht geladen werden.'], 502);
		}

		foreach (($response['body']['data'] ?? []) as $user) {
			if (!is_array($user)) {
				continue;
			}
			$userId = $user['id']['id'] ?? null;
			$userPermissions = is_string($userId) ? managementUserPermissions($userId, $token) : ['role' => 'none', 'locations' => []];
			$users[] = [
				'id' => $userId,
				'email' => $user['email'] ?? '',
				'firstName' => $user['firstName'] ?? '',
				'lastName' => $user['lastName'] ?? '',
				'authority' => $user['authority'] ?? '',
				'role' => $userPermissions['role'],
				'locations' => $userPermissions['locations'],
				'locationCount' => count($userPermissions['locations']),
				'postCount' => $postCounts[$userId] ?? 0,
				'mediaCount' => $mediaCounts[$userId] ?? 0,
				'createdTime' => $user['createdTime'] ?? null,
			];
		}

		$page++;
	} while (($response['body']['hasNext'] ?? false) === true && $page < 100);

	managementRespond(['users' => $users]);
}

if ($action === 'user-permissions' && $_SERVER['REQUEST_METHOD'] === 'GET') {
	managementRequireAdmin();
	$userId = isset($_GET['id']) ? (string) $_GET['id'] : '';
	$token = managementServiceToken();
	managementRequireCustomerUser($userId, $token);
	$response = thingsBoardManagementRequest(
		'GET',
		'/plugins/telemetry/USER/' . rawurlencode($userId) . '/values/attributes/SERVER_SCOPE?keys=wasserkarte_role,wasserkarte_locations',
		null,
		$token
	);
	if ($response['status'] !== 200 || !is_array($response['body'])) {
		managementRespond(['error' => 'Berechtigungen konnten nicht geladen werden.'], 502);
	}

	$role = 'none';
	$locations = [];
	foreach ($response['body'] as $attribute) {
		if (is_array($attribute) && ($attribute['key'] ?? '') === 'wasserkarte_role') {
			$role = managementUserRoleFromValue($attribute['value'] ?? null);
		}
		if (is_array($attribute) && ($attribute['key'] ?? '') === 'wasserkarte_locations') {
			$locations = managementLocationIdsFromAttribute($attribute['value'] ?? null);
		}
	}
	managementRespond(['role' => $role, 'locations' => $locations]);
}

if ($action === 'user-permissions' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	managementRequireAdmin();
	managementRequireCsrfToken();
	$input = managementReadJsonBody();
	$userId = is_array($input) ? (string) ($input['id'] ?? '') : '';
	$role = is_array($input) ? (string) ($input['role'] ?? '') : '';
	$locations = is_array($input) && is_array($input['locations'] ?? null) ? $input['locations'] : [];
	if (!in_array($role, ['none', 'wassermeister', 'super_wassermeister'], true)) {
		managementRespond(['error' => 'Ungültige Berechtigungsstufe.'], 400);
	}
	$locations = array_values(array_unique(array_filter($locations, static fn ($id) => is_string($id) && preg_match('/^[a-f0-9-]{36}$/i', $id))));
	if (count($locations) > 500) {
		managementRespond(['error' => 'Es können höchstens 500 Standorte zugeordnet werden.'], 400);
	}
	$updateProfile = is_array($input) && array_key_exists('firstName', $input) && array_key_exists('lastName', $input);
	if ($updateProfile) {
		$firstName = trim((string) $input['firstName']);
		$lastName = trim((string) $input['lastName']);
		if (mb_strlen($firstName) > 255 || mb_strlen($lastName) > 255) {
			managementRespond(['error' => 'Vor- und Nachname dürfen jeweils höchstens 255 Zeichen enthalten.'], 400);
		}
	}

	$token = managementServiceToken();
	$user = managementRequireCustomerUser($userId, $token);
	if ($updateProfile) {
		$user['firstName'] = $firstName;
		$user['lastName'] = $lastName;
		$updated = thingsBoardManagementRequest('POST', '/user?sendActivationMail=false', $user, $token);
		if ($updated['status'] !== 200 || !is_array($updated['body'])) {
			managementRespond(['error' => 'Profil konnte nicht gespeichert werden.'], 502);
		}
		if ((getManagementIdentity()['id'] ?? '') === $userId) {
			updateManagementIdentity(['firstName' => $firstName, 'lastName' => $lastName]);
		}
	}
	$saved = thingsBoardManagementRequest(
		'POST',
		'/plugins/telemetry/USER/' . rawurlencode($userId) . '/attributes/SERVER_SCOPE',
		['wasserkarte_role' => $role, 'wasserkarte_locations' => $locations],
		$token
	);
	if ($saved['status'] !== 200) {
		managementRespond(['error' => 'Berechtigungen konnten nicht gespeichert werden.'], 502);
	}
	managementRespond(['role' => $role, 'locations' => $locations]);
}

if ($action === 'send-account-mail' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	managementRequireAdmin();
	managementRequireCsrfToken();
	$input = managementReadJsonBody();
	$userId = is_array($input) ? (string) ($input['id'] ?? '') : '';
	$token = managementServiceToken();
	$user = managementRequireCustomerUser($userId, $token);
	$activation = thingsBoardManagementRequest('GET', '/user/' . rawurlencode($userId) . '/activationLink', null, $token);
	if ($activation['status'] === 200) {
		if (!managementMailEnabled()) {
			managementRespond(['error' => 'Der Mailversand ist derzeit deaktiviert.'], 503);
		}
		$link = managementActivationUrl(is_string($activation['rawBody']) ? trim($activation['rawBody']) : '');
		if ($link === null || !managementSendActivationMail($user['email'], $user['firstName'] ?? '', $link)) {
			managementRespond(['error' => 'Aktivierungs-Email konnte nicht gesendet werden.'], 502);
		}
	} elseif ($activation['status'] === 400 && ($activation['body']['message'] ?? '') === 'User is already activated!') {
		$enabled = thingsBoardManagementRequest('POST', '/user/' . rawurlencode($userId) . '/userCredentialsEnabled?userCredentialsEnabled=true', null, $token);
		if (!in_array($enabled['status'], [200, 204], true)) {
			managementRespond(['error' => 'Account konnte nicht entsperrt werden. Es wurde keine Passwort-Email angefordert.'], 502);
		}
		$sent = thingsBoardManagementRequest('POST', '/noauth/resetPasswordByEmail', ['email' => $user['email']]);
		if (!in_array($sent['status'], [200, 204], true)) {
			managementRespond(['error' => 'Passwort-Email konnte nicht angefordert werden.'], 502);
		}
	} else {
		managementRespond(['error' => 'Aktivierungslink konnte nicht geladen werden.'], 502);
	}
	managementResetAccountLoginLimits($user['email']);
	managementRespond(['success' => true]);
}

if ($action === 'delete-user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	managementRequireAdmin();
	managementRequireCsrfToken();
	$input = managementReadJsonBody();
	$userId = is_array($input) ? (string) ($input['id'] ?? '') : '';
	$token = managementServiceToken();
	managementRequireCustomerUser($userId, $token);
	$deleted = thingsBoardManagementRequest('DELETE', '/user/' . rawurlencode($userId), null, $token);
	if (!in_array($deleted['status'], [200, 204], true)) {
		managementRespond(['error' => 'Account konnte nicht gelöscht werden.'], 502);
	}
	managementRespond(['success' => true]);
}

if ($action === 'create-user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	managementRequireAdmin();
	managementRequireCsrfToken();
	$input = managementReadJsonBody();
	$email = is_array($input) ? trim((string) ($input['email'] ?? '')) : '';
	$firstName = is_array($input) ? trim((string) ($input['firstName'] ?? '')) : '';
	$lastName = is_array($input) ? trim((string) ($input['lastName'] ?? '')) : '';
	if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($firstName) > 255 || mb_strlen($lastName) > 255) {
		managementRespond(['error' => 'Bitte eine gültige Email-Adresse sowie gültige Namen eingeben.'], 400);
	}

	$customerId = managementCustomerId();
	$token = managementServiceToken();
	$created = thingsBoardManagementRequest('POST', '/user?sendActivationMail=false', [
		'email' => $email,
		'firstName' => $firstName,
		'lastName' => $lastName,
		'authority' => 'CUSTOMER_USER',
		'customerId' => ['id' => $customerId, 'entityType' => 'CUSTOMER'],
	], $token);
	$userId = $created['body']['id']['id'] ?? null;
	if ($created['status'] !== 200 || !is_string($userId) || $userId === '') {
		managementRespond(['error' => 'Account konnte nicht angelegt werden.'], 502);
	}

	$activation = thingsBoardManagementRequest('GET', '/user/' . rawurlencode($userId) . '/activationLink', null, $token);
	$thingsBoardActivationLink = is_string($activation['rawBody']) ? trim($activation['rawBody']) : '';
	$activationLink = managementActivationUrl($thingsBoardActivationLink);
	if ($activation['status'] !== 200 || $activationLink === null) {
		managementRespond(['error' => 'Account wurde angelegt, aber der Aktivierungslink konnte nicht erzeugt werden.'], 502);
	}

	$mailSent = managementMailEnabled() && managementSendActivationMail($email, $firstName, $activationLink);
	managementRespond([
		'user' => ['id' => $userId, 'email' => $email, 'firstName' => $firstName, 'lastName' => $lastName],
		'activationLink' => $activationLink,
		'mailSent' => $mailSent,
	], 201);
}

if ($action === 'activate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$input = managementReadJsonBody();
	$activateToken = is_array($input) ? (string) ($input['activateToken'] ?? '') : '';
	$password = is_array($input) ? (string) ($input['password'] ?? '') : '';
	if ($activateToken === '' || $password === '' || strlen($password) > 4096) {
		managementRespond(['error' => 'Der Aktivierungslink oder das Passwort ist ungültig.'], 400);
	}

	$activation = thingsBoardManagementRequest('POST', '/noauth/activate?sendActivationMail=false', [
		'activateToken' => $activateToken,
		'password' => $password,
	]);
	if ($activation['status'] !== 200) {
		managementRespond(['error' => 'Der Aktivierungslink ist ungültig, abgelaufen oder das Passwort erfüllt die Anforderungen nicht.'], 400);
	}

	// ThingsBoard liefert ein JWT zurück. Es wird absichtlich verworfen, damit
	// keine ThingsBoard-Sitzung im Browser der Wasserkarte entsteht.
	managementRespond(['success' => true]);
}

if ($action === 'logout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	managementRequireCsrfToken();
	destroyManagementSession();
	managementRespond(['authenticated' => false]);
}

if ($action === 'settings' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$identity = managementRequireIdentity();
	managementRequireCsrfToken();
	$input = managementReadJsonBody();
	$settings = managementUserSettingsFromValue(is_array($input) ? ($input['settings'] ?? null) : null);
	$token = managementServiceToken();
	$saved = thingsBoardManagementRequest(
		'POST',
		'/plugins/telemetry/USER/' . rawurlencode($identity['id']) . '/attributes/SERVER_SCOPE',
		['wasserkarte_settings' => $settings],
		$token
	);
	if ($saved['status'] !== 200) {
		managementRespond(['error' => 'Einstellungen konnten nicht gespeichert werden.'], 502);
	}

	updateManagementIdentity(['wasserkarteSettings' => $settings]);
	managementRespond(['settings' => $settings]);
}

if ($action === 'profile' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$identity = managementRequireIdentity();
	managementRequireCsrfToken();
	$input = managementReadJsonBody();
	$firstName = is_array($input) ? trim((string) ($input['firstName'] ?? '')) : '';
	$lastName = is_array($input) ? trim((string) ($input['lastName'] ?? '')) : '';
	if (mb_strlen($firstName) > 255 || mb_strlen($lastName) > 255) {
		managementRespond(['error' => 'Vor- und Nachname dürfen jeweils höchstens 255 Zeichen enthalten.'], 400);
	}

	$token = managementServiceToken();
	$current = thingsBoardManagementRequest('GET', '/user/' . rawurlencode($identity['id']), null, $token);
	if ($current['status'] !== 200 || !is_array($current['body'])) {
		managementRespond(['error' => 'Nutzerprofil konnte nicht geladen werden.'], 502);
	}

	$user = $current['body'];
	$user['firstName'] = $firstName;
	$user['lastName'] = $lastName;
	$updated = thingsBoardManagementRequest('POST', '/user?sendActivationMail=false', $user, $token);
	if ($updated['status'] !== 200 || !is_array($updated['body'])) {
		managementRespond(['error' => 'Profil konnte nicht gespeichert werden.'], 502);
	}

	updateManagementIdentity(['firstName' => $firstName, 'lastName' => $lastName]);
	managementRespond(managementSessionPayload());
}

if ($action === 'password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$identity = managementRequireIdentity();
	managementRequireCsrfToken();
	$input = managementReadJsonBody();
	$currentPassword = is_array($input) ? (string) ($input['currentPassword'] ?? '') : '';
	$newPassword = is_array($input) ? (string) ($input['newPassword'] ?? '') : '';
	if ($currentPassword === '' || $newPassword === '' || strlen($newPassword) > 4096) {
		managementRespond(['error' => 'Bitte aktuelles und neues Passwort eingeben.'], 400);
	}

	$login = loginManagementUser($identity['email'], $currentPassword);
	if ($login === null || ($login['profile']['id']['id'] ?? null) !== $identity['id']) {
		managementRespond(['error' => 'Das aktuelle Passwort ist nicht korrekt.'], 400);
	}
	$changed = thingsBoardManagementRequest('POST', '/auth/changePassword', [
		'currentPassword' => $currentPassword,
		'newPassword' => $newPassword,
	], 'Bearer ' . $login['token']);
	if ($changed['status'] !== 200) {
		managementRespond(['error' => 'Das Passwort entspricht nicht den ThingsBoard-Anforderungen.'], 400);
	}

	managementRespond(['success' => true]);
}

if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$input = managementReadJsonBody();
	$email = is_array($input) ? trim((string) ($input['email'] ?? '')) : '';
	$password = is_array($input) ? (string) ($input['password'] ?? '') : '';
	$remember = is_array($input) && ($input['remember'] ?? false) === true;
	if ($email === '' || $password === '' || strlen($email) > 254 || strlen($password) > 4096) {
		managementRespond(['error' => 'Bitte Email-Adresse und Passwort eingeben.'], 400);
	}

	managementRequireLoginNotRateLimited($email);
	$login = loginManagementUser($email, $password, $failure);
	if ($login === null) {
		if (($failure['kind'] ?? '') !== 'credentials') {
			managementRespond(['error' => $failure['message']], $failure['status']);
		}
		managementRegisterFailedLogin($email);
		managementRespond(['error' => 'Email-Adresse oder Passwort ist nicht korrekt.'], 401);
	}
	managementClearFailedLogin($email);

	$profile = $login['profile'];
	$id = $profile['id']['id'] ?? null;
	if (!is_string($id) || $id === '') {
		managementRespond(['error' => 'Das ThingsBoard-Nutzerprofil ist unvollständig.'], 502);
	}
	$authority = (string) ($profile['authority'] ?? '');

	setManagementIdentity([
		'id' => $id,
		'email' => (string) ($profile['email'] ?? $email),
		'firstName' => (string) ($profile['firstName'] ?? ''),
		'lastName' => (string) ($profile['lastName'] ?? ''),
		'thingsboardAuthority' => $authority,
	], $remember);
	managementRespond(managementSessionPayload());
}

managementRespond(['error' => 'Unbekannte Verwaltungsanfrage.'], 404);
