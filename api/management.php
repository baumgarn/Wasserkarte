<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/management/session.php';
require_once __DIR__ . '/management/thingsboard-client.php';
require_once __DIR__ . '/management/rate-limit.php';

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

function managementUserSettings(string $userId, string $token): array
{
	$response = thingsBoardManagementRequest(
		'GET',
		'/plugins/telemetry/USER/' . rawurlencode($userId) . '/values/attributes/SERVER_SCOPE?keys=wasserkarte_settings',
		null,
		$token
	);
	if ($response['status'] !== 200 || !is_array($response['body'])) {
		return [];
	}
	foreach ($response['body'] as $attribute) {
		if (is_array($attribute) && ($attribute['key'] ?? '') === 'wasserkarte_settings') {
			return managementUserSettingsFromValue($attribute['value'] ?? null);
		}
	}

	return [];
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
		managementRateLimitRetryAfter('login-email-ip', $source . "\0" . mb_strtolower($email), $window)
	);
	if ($retryAfter > 0) {
		header('Retry-After: ' . $retryAfter);
		managementRespond(['error' => 'Zu viele Anmeldeversuche. Bitte versuche es später erneut.'], 429);
	}
}

function managementRegisterFailedLogin(string $email): void
{
	$source = managementRequestSource();
	$window = managementLoginLimit('MANAGEMENT_LOGIN_WINDOW_SECONDS', 900);
	$block = managementLoginLimit('MANAGEMENT_LOGIN_BLOCK_SECONDS', 900);
	managementRegisterRateLimitFailure('login-ip', $source, managementLoginLimit('MANAGEMENT_LOGIN_IP_MAX_ATTEMPTS', 20), $window, $block);
	managementRegisterRateLimitFailure('login-email-ip', $source . "\0" . mb_strtolower($email), managementLoginLimit('MANAGEMENT_LOGIN_EMAIL_MAX_ATTEMPTS', 5), $window, $block);
}

function managementClearFailedLogin(string $email): void
{
	$source = managementRequestSource();
	managementClearRateLimit('login-email-ip', $source . "\0" . mb_strtolower($email), managementLoginLimit('MANAGEMENT_LOGIN_WINDOW_SECONDS', 900));
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

function managementSessionPayload(): array
{
	$identity = getManagementIdentity();
	if ($identity === null) {
		return ['authenticated' => false];
	}
	if (!array_key_exists('wasserkarteSettings', $identity) || (($identity['thingsboardAuthority'] ?? '') === 'CUSTOMER_USER' && !array_key_exists('wasserkarteLocations', $identity))) {
		$token = managementServiceToken();
		if (($identity['thingsboardAuthority'] ?? '') === 'CUSTOMER_USER' && !array_key_exists('wasserkarteLocations', $identity)) {
			$userPermissions = managementUserPermissions((string) $identity['id'], $token);
			$identity['wasserkarteRole'] = $userPermissions['role'];
			$identity['wasserkarteLocations'] = $userPermissions['locations'];
		}
		if (!array_key_exists('wasserkarteSettings', $identity)) {
			$identity['wasserkarteSettings'] = managementUserSettings((string) $identity['id'], $token);
		}
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
		],
		'permissions' => managementPermissions($identity['thingsboardAuthority']),
		'csrfToken' => getManagementCsrfToken(),
	];
}

$action = $_GET['action'] ?? '';
if ($action === 'me' && $_SERVER['REQUEST_METHOD'] === 'GET') {
	managementRespond(managementSessionPayload());
}

if ($action === 'users' && $_SERVER['REQUEST_METHOD'] === 'GET') {
	managementRequireAdmin();
	$customerId = managementCustomerId();
	$token = managementServiceToken();
	$users = [];
	$page = 0;

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

	$token = managementServiceToken();
	managementRequireCustomerUser($userId, $token);
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
		managementRespond(['error' => 'Bitte eine gültige E-Mail-Adresse sowie gültige Namen eingeben.'], 400);
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
		managementRespond(['error' => 'Bitte E-Mail-Adresse und Passwort eingeben.'], 400);
	}

	managementRequireLoginNotRateLimited($email);
	$login = loginManagementUser($email, $password);
	if ($login === null) {
		managementRegisterFailedLogin($email);
		managementRespond(['error' => 'E-Mail-Adresse oder Passwort ist nicht korrekt.'], 401);
	}
	managementClearFailedLogin($email);

	$profile = $login['profile'];
	$id = $profile['id']['id'] ?? null;
	if (!is_string($id) || $id === '') {
		managementRespond(['error' => 'Das ThingsBoard-Nutzerprofil ist unvollständig.'], 502);
	}
	$authority = (string) ($profile['authority'] ?? '');
	$userPermissions = $authority === 'CUSTOMER_USER'
		? managementUserPermissions($id, managementServiceToken())
		: ['role' => 'none', 'locations' => []];
	$userSettings = managementUserSettings($id, managementServiceToken());

	setManagementIdentity([
		'id' => $id,
		'email' => (string) ($profile['email'] ?? $email),
		'firstName' => (string) ($profile['firstName'] ?? ''),
		'lastName' => (string) ($profile['lastName'] ?? ''),
		'thingsboardAuthority' => $authority,
		'wasserkarteRole' => $userPermissions['role'],
		'wasserkarteLocations' => $userPermissions['locations'],
		'wasserkarteSettings' => $userSettings,
	], $remember);
	managementRespond(managementSessionPayload());
}

managementRespond(['error' => 'Unbekannte Verwaltungsanfrage.'], 404);
