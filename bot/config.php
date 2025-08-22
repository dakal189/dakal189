<?php

return [
	'app' => [
		'name' => getenv('APP_NAME') ?: 'ReferralBot',
		'env' => getenv('APP_ENV') ?: 'production',
		'timezone' => getenv('APP_TIMEZONE') ?: 'Asia/Tehran',
		'webhook_secret' => getenv('WEBHOOK_SECRET') ?: 'change-me-secret',
	],
	'db' => [
		'host' => getenv('DB_HOST') ?: '127.0.0.1',
		'port' => (int)(getenv('DB_PORT') ?: 3306),
		'database' => getenv('DB_DATABASE') ?: 'referral_bot',
		'username' => getenv('DB_USERNAME') ?: 'root',
		'password' => getenv('DB_PASSWORD') ?: '',
		'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
	],
	'telegram' => [
		'token' => getenv('TELEGRAM_BOT_TOKEN') ?: '',
		'admin_id' => (int)(getenv('TELEGRAM_ADMIN_ID') ?: 0),
		'admin_group_id' => getenv('TELEGRAM_ADMIN_GROUP_ID') ?: '',
		'api_base' => 'https://api.telegram.org',
	],
	'settings' => [
		'points_per_referral' => (int)(getenv('POINTS_PER_REFERRAL') ?: 10),
		'min_hours_before_qualify' => (int)(getenv('MIN_HOURS_BEFORE_QUALIFY') ?: 0),
		'days_revoke_if_left' => (int)(getenv('DAYS_REVOKE_IF_LEFT') ?: 0),
	]
];