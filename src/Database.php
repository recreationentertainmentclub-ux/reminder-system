<?php

declare(strict_types=1);

namespace ReminderSystem;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Thin wrapper around PDO that provides a singleton connection
 * configured from the application config file.
 */
class Database
{
    private static ?PDO $instance = null;

    /** Prevent direct instantiation. */
    private function __construct() {}

    /**
     * Returns the shared PDO instance, creating it on first call.
     *
     * @param array<string,mixed>|null $config Optional config override (useful in tests).
     */
    public static function getInstance(?array $config = null): PDO
    {
        if (self::$instance === null) {
            $config = $config ?? require __DIR__ . '/../config/config.php';
            self::$instance = self::createConnection($config['db']);
        }

        return self::$instance;
    }

    /**
     * Create a new PDO connection from the given db config array.
     *
     * @param array<string,mixed> $db
     */
    public static function createConnection(array $db): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $db['host'],
            $db['port'],
            $db['name'],
            $db['charset'] ?? 'utf8mb4'
        );

        try {
            $pdo = new PDO($dsn, $db['user'], $db['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException(
                'Database connection failed: ' . $e->getMessage(),
                (int)$e->getCode(),
                $e
            );
        }

        return $pdo;
    }

    /** Reset the singleton (useful in tests). */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
