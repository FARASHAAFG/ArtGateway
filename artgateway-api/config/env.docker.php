<?php

declare(strict_types=1);

/**
 * Docker environment configuration.
 *
 * This file is used when running the application in Docker containers.
 * It is automatically mounted as env.php by docker-compose.yml.
 *
 * Database credentials match those defined in docker-compose.yml.
 */

return function (array $settings): array {
    $requiredEnvironmentValue = static function (string $name): string {
        $value = getenv($name);
        if ($value === false || trim($value) === '') {
            throw new RuntimeException("Required environment variable {$name} is not set.");
        }

        return $value;
    };

    $settings['db']['host'] = $requiredEnvironmentValue('DB_HOST');
    $settings['db']['username'] = $requiredEnvironmentValue('DB_USERNAME');
    $settings['db']['password'] = $requiredEnvironmentValue('DB_PASSWORD');
    $settings['db']['database'] = 'artgateway_db';
    $settings['db']['port'] = '3306';

    $jwtSecret = $requiredEnvironmentValue('JWT_SECRET');
    if (strlen($jwtSecret) < 32) {
        throw new RuntimeException('JWT_SECRET must contain at least 32 characters.');
    }
    $settings['jwt']['secret'] = $jwtSecret;

    return $settings;
};
