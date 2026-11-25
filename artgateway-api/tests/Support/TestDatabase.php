<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Helpers\Core\AppSettings;
use LogicException;
use PDO;
use PDOException;

final class TestDatabase
{
    private const MARKER = 'artgateway-isolated-test-db-v1';

    private static ?PDO $connection = null;
    private static ?TestDatabaseUnavailable $unavailable = null;

    public static function connection(): PDO
    {
        if (self::$connection !== null) {
            return self::$connection;
        }
        if (self::$unavailable !== null) {
            throw self::$unavailable;
        }

        $settings = TestApplication::get()
            ->getContainer()
            ->get(AppSettings::class)
            ->get('db');

        if (
            $settings['host'] !== '127.0.0.1'
            || (string) $settings['port'] !== '3307'
            || $settings['database'] !== 'artgateway_db'
            || $settings['username'] !== 'artgateway_test'
        ) {
            throw new LogicException(
                'Refusing integration tests: the isolated test database settings were changed.'
            );
        }

        if (!in_array('mysql', PDO::getAvailableDrivers(), true)) {
            throw new TestDatabaseUnavailable(
                'The pdo_mysql PHP extension is required for MariaDB integration tests.'
            );
        }

        try {
            self::$connection = new PDO(
                sprintf(
                    'mysql:host=%s;dbname=%s;port=%s;charset=utf8mb4',
                    $settings['host'],
                    $settings['database'],
                    $settings['port']
                ),
                $settings['username'],
                $settings['password'],
                array_replace($settings['options'] ?? [], [PDO::ATTR_TIMEOUT => 2])
            );
        } catch (PDOException $exception) {
            self::$unavailable = new TestDatabaseUnavailable(
                'The isolated MariaDB test service is unavailable. Start it with docker compose -f docker-compose.test.yml up -d --wait test-db.',
                0,
                $exception
            );
            throw self::$unavailable;
        }

        $database = self::$connection->query('SELECT DATABASE()')->fetchColumn();
        if ($database !== 'artgateway_db') {
            throw new LogicException('Refusing integration tests outside the configured test database.');
        }

        $marker = self::$connection
            ->query('SELECT marker FROM _artgateway_test_environment WHERE id = 1')
            ->fetchColumn();

        if ($marker !== self::MARKER) {
            throw new LogicException(
                'Refusing integration tests: MariaDB does not contain the isolated test-database marker.'
            );
        }

        return self::$connection;
    }
}
