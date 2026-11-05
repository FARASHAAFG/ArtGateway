<?php

declare(strict_types=1);

//TODO: set the app environment based on the domaine name.
// The APP_ENV value must be set to either prod or dev
//$_ENV['APP_ENV'] = 'prod';
$_ENV['APP_ENV'] ??= $_SERVER['APP_ENV'] ?? 'dev';

// Load default settings
$settings = require __DIR__ . '/defaults.php';

// Overwrite default settings with environment specific local settings
$configFiles = [
    __DIR__ . sprintf('/settings.%s.php', $_ENV['APP_ENV']),
    __DIR__ . '/env.php',
    __DIR__ . '/../env.php',
];

if (!realpath(__DIR__ . '/env.php')) {

    trigger_error('env.php file not found. Please create it in the config folder by copying env.example.php and renaming it to env.php. For more details about configuring this application, refer to: <br><a href="https://github.com/frostybee/slim-template?tab=readme-ov-file#how-do-i-usedeploy-this-template">README.md</a>');
    exit;
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
