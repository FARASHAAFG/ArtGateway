<?php

declare(strict_types=1);

namespace App\Helpers\Core;

use Exception;
use PDO;

/**
 * PDO database service wrapper.
 *
 * Provides a service layer for PDO database connections with lazy loading
 * and configuration-based connection management.
 */
class PDOService
{
    /**
     * The PDO database connection instance.
     *
     * @var PDO|null
     */
    private ?PDO $db = null;

    /**
     * Database configuration array.
     *
     * @var array
     */
    private array $config = [];

    /**
     * Constructor to initialize the PDOService with database configuration.
     *
     * @param array $config Database configuration array containing connection parameters.
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Establishes a database connection if one doesn't exist.
     *
     * Creates a MySQL PDO connection using the provided configuration.
     * Connection is established lazily on first access.
     *
     * @throws Exception If required configuration parameters are missing.
     * @throws \PDOException If the database connection fails.
     * @return void
     */
    private function connect(): void
    {
        if ($this->db !== null) {
            return;
        }

        if (!isset($this->config['database']) || !is_string($this->config['database']) || trim($this->config['database']) === '') {
            throw new Exception('Database name is required. Set DB_DATABASE or configure db.database in config/env.php.');
        }

        if (!isset($this->config['username']) || !is_string($this->config['username']) || trim($this->config['username']) === '') {
            throw new Exception('Database username is required. Set DB_USERNAME or configure db.username in config/env.php.');
        }

        $host = $this->config['host'] ?? 'localhost';
        $charset = 'utf8mb4';
        $port = $this->config['port'] ?? '3306';
        $password = $this->config['password'] ?? '';
        $database = $this->config['database'];
        $username = $this->config['username'];
        if (!is_string($host) || trim($host) === '') {
            throw new Exception('Database host is required. Set DB_HOST or configure db.host in config/env.php.');
        }
        if (filter_var($port, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]) === false) {
            throw new Exception('Database port must be an integer between 1 and 65535. Set DB_PORT or configure db.port in config/env.php.');
        }

        try {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;port=%s;charset=%s',
                $host,
                $database,
                $port,
                $charset
            );
            $this->db = new PDO($dsn, $username, $password, $this->config['options'] ?? []);
        } catch (\PDOException $e) {
            $message = sprintf('Failed to connect to the database. Error: %s. Please make sure the database name, username, and password are correct in the config/env.php file. If the config/env.php file is not found, please create it by copying the env.example.php file and renaming it to env.php.', $e->getMessage());
            throw new \PDOException($message, (int)$e->getCode());
        }
    }

    /**
     * Gets the PDO database connection instance.
     *
     * Establishes the connection if it doesn't exist and returns the PDO instance.
     *
     * @return PDO The PDO database connection instance.
     * @throws Exception If required configuration parameters are missing.
     * @throws \PDOException If the database connection fails.
     */
    public function getPDO(): PDO
    {
        $this->connect();
        return $this->db;
    }
}
