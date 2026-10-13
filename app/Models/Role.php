<?php

namespace App\Models;

use App\Core\Model;
use PDO;
use PDOException;

class Role extends Model
{
    private string $table = 'revo_role_master';

    public function getAll(): array
    {
        $sql = "SELECT id, role FROM {$this->table} ORDER BY id ASC";
        $stmt = $this->getDb()->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getById(int $id): ?array
    {
        $sql = "SELECT id, role FROM {$this->table} WHERE id = :id LIMIT 1";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function create(array $data): array
    {
        if (empty($data['role'])) {
            return [
                'status' => false,
                'error' => 400,
                'message' => 'Role is required',
            ];
        }

        try {
            $sql = "INSERT INTO {$this->table} (role) VALUES (:role)";
            $stmt = $this->getDb()->prepare($sql);
            $stmt->bindValue(':role', $data['role'], PDO::PARAM_STR);
            $stmt->execute();

            return [
                'status' => true,
                'error' => 201,
                'id' => (int)$this->getDb()->lastInsertId(),
            ];
        } catch (PDOException $e) {
            $code = $e->getCode() === '23000' ? 409 : 500;

            return [
                'status' => false,
                'error' => $code,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function update(int $id, array $data): bool
    {
        if (empty($data['role'])) {
            return false;
        }

        $sql = "UPDATE {$this->table} SET role = :role WHERE id = :id";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':role', $data['role'], PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function delete(int $id): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }
}

