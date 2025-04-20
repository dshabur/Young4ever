<?php
// db_connect.php

class Database {
    private static $instance = null;
    private PDO $pdo;

    private function __construct() {
        try {
            $this->pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            logError("Ошибка подключения к базе данных: " . $e->getMessage());
            throw $e;
        }
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function beginTransaction(): bool {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool {
        return $this->pdo->commit();
    }

    public function rollBack(): bool {
        return $this->pdo->rollBack();
    }

    public function prepare(string $statement, array $options = []): PDOStatement|false {
        return $this->pdo->prepare($statement, $options);
    }

    public function query(string $query): PDOStatement|false {
        return $this->pdo->query($query);
    }

    public function lastInsertId(?string $name = null): string|false {
        return $this->pdo->lastInsertId($name);
    }

    public function getPdo(): PDO {
        return $this->pdo;
    }
}

// Пример использования:
// $database = new Database();
// $db = $database->connect();
?>