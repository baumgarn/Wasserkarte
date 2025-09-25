<?php

	define('CACHE_FILE_DEVICES', 'cache/devices.json');
	define('CACHE_FILE_ALLTELEMETRY', 'cache/alltelemetry.json');
	define('CACHE_DEVICES_DURATION', 60*60*6); // 12 Hours
	define('CACHE_TELEMETRY_ALL_DURATION', 60*60*1); // 1 Hour
	define('CACHE_TELEMETRY_SINGLE_DURATION', 60*60*1); // 1 Hour
	define('TOKEN_FILE', 'cache/token.json');
	define('THINGSBOARD_URL', 'https://...');
	define('USERNAME', 'username');
	define('PASSWORD', 'password');
	define('ALLOWED_SENSOR_KEYS', ['Bodenfeuchte_10cm', 'Bodenfeuchte_30cm', 'Bodenfeuchte_60cm', 'Bodenfeuchte_80cm']);
