<?php

namespace Psalm\Shepherd;

use PDO;
use PDOException;

class DatabaseProvider
{
	public static function getConnection() : PDO
	{
		$db_config = Config::getInstance()->mysql;

		try {
		    $pdo = new PDO($db_config['dsn'], $db_config['user'], $db_config['password']);
		} catch (PDOException $e) {
		    die('Connection to database failed - ' . $e->getMessage());
		}

		return $pdo;
	}
}