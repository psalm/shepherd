<?php

namespace Psalm\Shepherd;

use PDO;
use PDOException;
use function error_log;

final class DatabaseProvider
{
    /** @var ?PDO */
    private static $connection = null;

    public static function getConnection() : PDO
    {
        if (self::$connection) {
            return self::$connection;
        }

        $db_config = Config::getInstance()->mysql;

        try {
            $pdo = new PDO($db_config['dsn'], $db_config['user'], $db_config['password']);
        } catch (PDOException $e) {
            error_log('Connection to database failed - ' . $e->getMessage());
            die('Connection to database failed');
        }

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

        self::$connection = $pdo;

        return $pdo;
    }
}
