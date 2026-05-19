<?php
declare(strict_types=1);

abstract class AbstractRepository
{
    protected PDO $pdo;
    protected string $table;
    protected string $primaryKey;
    protected array $allowedSortColumns = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Выборка с фильтрацией, сортировкой и лимитом
     */
    public function findAll(array $where = [], array $params = [], array $orderBy = [], int $limit = 0): array
    {
        $sql = "SELECT * FROM {$this->table}";
        $conditions = [];
        $bindings = $params;

        foreach ($where as $col => $val) {
            $conditions[] = "$col = ?";
            $bindings[] = $val;
        }
        if ($conditions) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }

        // Безопасная сортировка через белый список
        if (!empty($orderBy)) {
            $validClauses = [];
            foreach ($orderBy as $col => $dir) {
                if (in_array($col, $this->allowedSortColumns, true)) {
                    $direction = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';
                    $validClauses[] = "$col $direction";
                }
            }
            if ($validClauses) {
                $sql .= " ORDER BY " . implode(", ", $validClauses);
            }
        }

        if ($limit > 0) {
            $sql .= " LIMIT " . (int)$limit;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function insert(array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO {$this->table} ($columns) VALUES ($placeholders)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($data));
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $set = [];
        foreach ($data as $col => $val) {
            $set[] = "$col = ?";
        }
        $sql = "UPDATE {$this->table} SET " . implode(', ', $set) . " WHERE {$this->primaryKey} = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([...array_values($data), $id]);
        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
}
