<?php

	date_default_timezone_set('Europe/Berlin');
	define('CACHE_DIR', __DIR__ . '/cache');
	define('CACHE_FILE_DEVICES', CACHE_DIR . '/devices.json');
	define('CACHE_FILE_ALLTELEMETRY', CACHE_DIR . '/alltelemetry.json');
	define('CACHE_DEVICES_DURATION', 60*60*6); // 12 Hours
	define('CACHE_TELEMETRY_ALL_DURATION', 60*60*1); // 1 Hour
	define('CACHE_TELEMETRY_SINGLE_DURATION', 60*60*1); // 1 Hour
	define('TOKEN_FILE', 'cache/token.json');
	define('THINGSBOARD_URL', 'https://...');
	define('USERNAME', '');
	define('PASSWORD', '');
	// Optional: wird gegenüber USERNAME/PASSWORD bevorzugt und als `ApiKey` verwendet.
	define('API_KEY', '');
	// In Produktion auf true setzen. Für http://localhost muss der Wert false bleiben.
	define('SESSION_COOKIE_SECURE', false);
	define('REMEMBER_ME_SESSION_LIFETIME', 60 * 60 * 24 * 30); // 30 Tage
	define('REFRESH_SECRET', 'change-this-secret');
	define('REFRESH_BLOCK_SECONDS', 300);
	// Login: fehlgeschlagene Versuche werden je IP und je Kombination aus IP/E-Mail begrenzt.
	define('MANAGEMENT_LOGIN_WINDOW_SECONDS', 60 * 15);
	define('MANAGEMENT_LOGIN_BLOCK_SECONDS', 60 * 15);
	define('MANAGEMENT_LOGIN_EMAIL_MAX_ATTEMPTS', 5);
	define('MANAGEMENT_LOGIN_IP_MAX_ATTEMPTS', 20);
	define('ALLOWED_SENSOR_KEYS', ['Bodenfeuchte_10cm', 'Bodenfeuchte_30cm', 'Bodenfeuchte_60cm', 'Bodenfeuchte_80cm']);
	define('CUTOFF_DATE', 1737072000000); // Jan 17, 2025 UTC in ms
	define('WASSERKARTE_CUSTOMER_ID', ''); // Customer muss im Thingsboard angelegt werden um User Accounts anzuheften
	define('MAIL_ENABLED', false);
	define('MAIL_FROM', 'noreply@wasserkarte.org');
	define('WASSERKARTE_URL', 'https://wasserkarte.org');
