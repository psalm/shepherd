<?php

namespace Psalm\Shepherd;

use PDO;
use PDOException;

class DatabaseProvider
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
		    die('Connection to database failed - ' . $e->getMessage());
		}

		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

		self::$connection = $pdo;

		return $pdo;
	}
}