<?php

declare(strict_types=1);

namespace App\Infrastructure;

use PDO;

// Singleton PDO wrapper.
// Opens exactly one MySQL connection for the lifetime of the process.
// Every repository and service gets the connection via Database::connection().
class Database {
    private static ?PDO $instance = null;

    public static function connection(array $config): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $config['host'],
                $config['port'],
                $config['name'],
                $config['charset']
            );
                
            self::$instance = new PDO($dsn, $config['user'], $config['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }

        return self::$instance;
    }

    // allows tests to inject a SQLite in-memory PDO instead of hitting real MySQL
    public static function setInstance(PDO $pdo): void
    {
        self::$instance = $pdo;
    }
}
