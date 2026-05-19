<?php
declare(strict_types=1);

class Database
{
    private static ?self $instance = null;
    private PDO $pdo;

    private function __construct(array $config)
    {
        $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        $this->pdo = new PDO($dsn, $config['user'], $config['pass'], $options);
    }

    public static function getInstance(array $config): self
    {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    public function getConnection(): PDO
    {
        return $this->pdo;
    }
}
