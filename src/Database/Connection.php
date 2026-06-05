<?php

declare(strict_types=1);

namespace Kletterdom\Database;

use PDO;

final class Connection
{
    /**
     * @param array{host:string, port:int, database:string, username:string, password:string, charset:string} $config
     */
    public static function pdo(array $config): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset'],
        );

        return new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00', NAMES utf8mb4",
        ]);
    }
}
