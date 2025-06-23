<?php

class Config {
    const DB_HOST = 'localhost';
    const DB_NAME = 'database';
    const DB_USER = 'user';
    const DB_PASS = 'pass';
    
    const STEAM_API_KEY = 'API_KEY';
    const SITE_URL = 'web_url';
    
    const DB_CHARSET = 'utf8mb4';
    const DB_OPTIONS = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ];
}

class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            $dsn = sprintf(
                "mysql:host=%s;dbname=%s;charset=%s",
                Config::DB_HOST,
                Config::DB_NAME,
                Config::DB_CHARSET
            );
            
            $this->connection = new PDO($dsn, Config::DB_USER, Config::DB_PASS, Config::DB_OPTIONS);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new RuntimeException("Database connection failed");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    private function __clone() {}
    private function __wakeup() {}
}

class SessionManager {
    public static function start() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start([
                'cookie_lifetime' => 0,
                'cookie_secure' => isset($_SERVER['HTTPS']),
                'cookie_httponly' => true,
                'cookie_samesite' => 'Strict',
                'use_strict_mode' => true
            ]);
        }
    }
    
    public static function regenerate() {
        session_regenerate_id(true);
    }
    
    public static function destroy() {
        session_destroy();
    }
}

try {
    $db = Database::getInstance()->getConnection();
    SessionManager::start();
} catch (Exception $e) {
    error_log("Configuration initialization failed: " . $e->getMessage());
    http_response_code(500);
    exit("System temporarily unavailable");
}
