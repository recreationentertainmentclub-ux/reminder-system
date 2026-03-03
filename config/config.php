<?php

declare(strict_types=1);

/**
 * Application configuration.
 *
 * Values are read from environment variables so that secrets are never
 * committed to source control.  When running locally you can export the
 * variables in your shell or use a .env loader of your choice.
 */
return [
    'db' => [
        'host'     => getenv('DB_HOST')     ?: 'localhost',
        'port'     => (int)(getenv('DB_PORT') ?: 3306),
        'name'     => getenv('DB_NAME')     ?: 'reminder_system',
        'user'     => getenv('DB_USER')     ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: '',
        'charset'  => 'utf8mb4',
    ],
    'smartsender' => [
        'api_url' => getenv('SMARTSENDER_API_URL') ?: 'https://app.smartsender.com/api',
        'api_key' => getenv('SMARTSENDER_API_KEY') ?: '',
    ],
    'app' => [
        'timezone' => getenv('APP_TIMEZONE') ?: 'UTC',
        'log_file' => getenv('APP_LOG_FILE') ?: __DIR__ . '/../logs/app.log',
    ],
];
