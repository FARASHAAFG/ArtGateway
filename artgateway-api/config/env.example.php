<?php

declare(strict_types=1);
/**
 * Environment-specific application configuration.
 *
 * You should store all secret information (usernames, passwords, tokens,
 * private keys) here.
 *
 * TODO:
 * -----
 * 1) Within this folder, create a new PHP file called env.php.
 * 2) Copy the contents of this file into env.php.
 * 3) Make sure the env.php file is added to your .gitignore
 *    so it is not checked into version control.
 *
 * NOTE:
 * -----
 * This approach ensures that no sensitive passwords or API keys
 * will ever be included in version control history, reducing the
 * risk of a security breach. It also ensures that production values
 * never have to be shared with all project collaborators.
 */

return function (array $settings): array {
    // Wampoon defaults support a local root account with no password.
    // Set DB_* environment variables for other local or production configurations.
    $settings['db']['host'] = getenv('DB_HOST') ?: 'localhost';
    $settings['db']['port'] = getenv('DB_PORT') ?: '3306';
    $settings['db']['username'] = getenv('DB_USERNAME') ?: 'root';
    $settings['db']['database'] = getenv('DB_DATABASE') ?: 'artgateway_db';
    $settings['db']['password'] = getenv('DB_PASSWORD') ?: '';

    // Set JWT_SECRET in the server environment to a random value of at least 32 characters.
    // Example generation: bin2hex(random_bytes(32))
    $jwtSecret = getenv('JWT_SECRET');
    $settings['jwt']['secret'] = $jwtSecret !== false ? $jwtSecret : ($_ENV['JWT_SECRET'] ?? '');

    return $settings;
};
