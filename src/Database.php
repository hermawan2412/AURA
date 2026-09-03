<?php

namespace Aurat;

use PDO;
use PDOException;

class Database
{
    /** @var PDO|null */
    private static $instance = null;

    public static function pdo()
    {
        if (self::$instance === null) {
            $config = require __DIR__ . '/../config/config.php';
            $db = $config['db'];

            $dsn = 'mysql:host=' . $db['host'] . ';dbname=' . $db['dbname'] . ';charset=' . $db['charset'];

            try {
                self::$instance = new PDO($dsn, $db['user'], $db['pass'], array(
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ));
            } catch (PDOException $e) {
                error_log('[AURA] Koneksi database gagal: ' . $e->getMessage());
                http_response_code(500);
                die('Tidak dapat terhubung ke database. Hubungi administrator sistem.');
            }
        }

        return self::$instance;
    }
}
