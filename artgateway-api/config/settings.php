<?php

declare(strict_types=1);

//TODO: set the app environment based on the domaine name.
// The APP_ENV value must be set to either prod or dev
//$_ENV['APP_ENV'] = 'prod';
$_ENV['APP_ENV'] ??= $_SERVER['APP_ENV'] ?? 'dev';

// Load default settings
$settings = require __DIR__ . '/defaults.php';

// Tests use an isolated configuration and never load local or production secrets.
$isTestEnvironment = $_ENV['APP_ENV'] === 'test';
if ($isTestEnvironment) {
    $configFiles = [__DIR__ . '/settings.test.php'];
} else {
    $configFiles = [
        __DIR__ . sprintf('/settings.%s.php', $_ENV['APP_ENV']),
        __DIR__ . '/env.php',
        __DIR__ . '/../env.php',
    ];

    if (!realpath(__DIR__ . '/env.php')) {
        trigger_error('env.php file not found. Please create it in the config folder by copying env.example.php and renaming it to env.php. For more details about configuring this application, refer to: <br><a href="https://github.com/frostybee/slim-template?tab=readme-ov-file#how-do-i-usedeploy-this-template">README.md</a>');
        exit;
    }
}

foreach ($configFiles as $configFile) {
    if (!file_exists($configFile)) {
        continue;
    }

    $local = require $configFile;
    if (is_callable($local)) {
        $settings = $local($settings);
    }
}

if (!$isTestEnvironment) {
    // Environment variables override local files so Docker and Wampoon use the same keys.
    $environmentVariables = [
        'host' => 'DB_HOST',
        'port' => 'DB_PORT',
        'database' => 'DB_DATABASE',
        'username' => 'DB_USERNAME',
        'password' => 'DB_PASSWORD',
    ];
    foreach ($environmentVariables as $setting => $variable) {
        $value = getenv($variable);
        if ($value !== false) {
            $settings['db'][$setting] = $value;
        }
    }

    $jwtSecret = getenv('JWT_SECRET');
    if ($jwtSecret !== false) {
        $settings['jwt']['secret'] = $jwtSecret;
    }
}

// External service: Art Institute of Chicago (free, no API key, 60 requests/minute per IP)
$settings['external']['artic'] = [
    'base_uri' => 'https://api.artic.edu/api/v1/',
    // Total time (seconds) we are willing to wait for the whole request.
    'timeout' => 3.0,
    // Time (seconds) we are willing to wait just to connect.
    'connect_timeout' => 2.0,
    // The museum asks API users to identify themselves (project name + contact).
    'user_agent' => 'ArtGateway student project (contact: jiayu.joyho@gmail.com)',
];

return $settings;
