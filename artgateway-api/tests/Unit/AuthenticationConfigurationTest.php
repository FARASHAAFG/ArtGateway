<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AuthenticationConfigurationTest extends TestCase
{
    public function testDockerConfigurationRequiresAndLoadsJwtSecret(): void
    {
        $environment = [
            'DB_HOST' => 'db',
            'DB_USERNAME' => 'artgateway_user',
            'DB_PASSWORD' => 'test-db-password',
            'JWT_SECRET' => str_repeat('docker-test-secret-', 2),
        ];
        $this->withEnvironment($environment, function (): void {
            $configure = require APP_BASE_PATH . '/config/env.docker.php';
            $settings = $configure(['db' => [], 'jwt' => []]);

            self::assertSame(str_repeat('docker-test-secret-', 2), $settings['jwt']['secret']);
            self::assertSame('artgateway_db', $settings['db']['database']);
        });
    }

    public function testDockerConfigurationRejectsMissingJwtSecret(): void
    {
        $environment = [
            'DB_HOST' => 'db',
            'DB_USERNAME' => 'artgateway_user',
            'DB_PASSWORD' => 'test-db-password',
            'JWT_SECRET' => null,
        ];
        $this->withEnvironment($environment, function (): void {
            $configure = require APP_BASE_PATH . '/config/env.docker.php';

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('JWT_SECRET');
            $configure(['db' => [], 'jwt' => []]);
        });
    }

    public function testLocalExampleConfigurationReadsJwtSecretFromEnvironment(): void
    {
        $secret = str_repeat('local-test-secret-', 2);
        $this->withEnvironment(['JWT_SECRET' => $secret], function () use ($secret): void {
            $configure = require APP_BASE_PATH . '/config/env.example.php';
            $settings = $configure(['db' => [], 'jwt' => []]);

            self::assertSame($secret, $settings['jwt']['secret']);
        });
    }

    private function withEnvironment(array $values, callable $operation): void
    {
        $original = [];
        foreach ($values as $name => $value) {
            $original[$name] = getenv($name);
            if ($value === null) {
                putenv($name);
                unset($_ENV[$name], $_SERVER[$name]);
            } else {
                putenv($name . '=' . $value);
                $_ENV[$name] = $value;
            }
        }

        try {
            $operation();
        } finally {
            foreach ($original as $name => $value) {
                if ($value === false) {
                    putenv($name);
                    unset($_ENV[$name], $_SERVER[$name]);
                } else {
                    putenv($name . '=' . $value);
                    $_ENV[$name] = $value;
                }
            }
        }
    }
}
