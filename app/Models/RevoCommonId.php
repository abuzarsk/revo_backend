<?php

namespace App\Models;

use App\Core\Model;
use PDO;
use PDOException;

class RevoCommonId extends Model
{
    private string $table = 'revo_common_ids';

    public function getAll(): array
    {
        $sql = "SELECT
                    id,
                    unique_id,
                    company_id,
                    mediator_id,
                    updated_on,
                    updated_by,
                    created_on,
                    created_by
                FROM {$this->table}
                ORDER BY id DESC";

        $stmt = $this->getDb()->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getById(int $id): ?array
    {
        $sql = "SELECT
                    id,
                    unique_id,
                    company_id,
                    mediator_id,
                    updated_on,
                    updated_by,
                    created_on,
                    created_by
                FROM {$this->table}
                WHERE id = :id
                LIMIT 1";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function getByUniqueId(int $uniqueId): ?array
    {
        $sql = "SELECT
                    id,
                    unique_id,
                    company_id,
                    mediator_id,
                    updated_on,
                    updated_by,
                    created_on,
                    created_by
                FROM {$this->table}
                WHERE unique_id = :unique_id
                LIMIT 1";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':unique_id', $uniqueId, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function getByCompanyId(int $companyId): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE company_id = :company_id LIMIT 1";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getByMediatorId(int $mediatorId): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE mediator_id = :mediator_id LIMIT 1";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':mediator_id', $mediatorId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function create(array $data): array | bool
    {
        $required = ['company_id', 'mediator_id', 'created_by'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                return [
                    'status' => false,
                    'error' => 400,
                    'message' => "Field {$field} is required",
                ];
            }
        }

        $now = date('Y-m-d H:i:s');
        $updatedBy = $data['updated_by'] ?? $data['created_by'];

        $uniqueId = str_pad((string)floor(mt_rand() / mt_getrandmax() * 1000000), 6, '0', STR_PAD_LEFT);
   

        $sql = "INSERT INTO {$this->table} (
                    unique_id,
                    company_id,
                    mediator_id,
                    updated_on,
                    updated_by,
                    created_on,
                    created_by
                ) VALUES (
                    :unique_id,
                    :company_id,
                    :mediator_id,
                    :updated_on,
                    :updated_by,
                    :created_on,
                    :created_by
                )";

        try {
            $stmt = $this->getDb()->prepare($sql);
            $stmt->bindValue(':unique_id', (int) $uniqueId, PDO::PARAM_INT);
            $stmt->bindValue(':company_id', (int) $data['company_id'], PDO::PARAM_INT);
            $stmt->bindValue(':mediator_id', (int) $data['mediator_id'], PDO::PARAM_INT);
            $stmt->bindValue(':updated_on', $now, PDO::PARAM_STR);
            $stmt->bindValue(':updated_by', (int) $updatedBy, PDO::PARAM_INT);
            $stmt->bindValue(':created_on', $now, PDO::PARAM_STR);
            $stmt->bindValue(':created_by', (int) $data['created_by'], PDO::PARAM_INT);
            $stmt->execute();

            return [
                'status' => true,
                'error' => 201,
                'id' => (int) $this->getDb()->lastInsertId(),
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
        $fields = [];
        $params = [':id' => $id];
        $updatable = ['unique_id', 'company_id', 'mediator_id', 'updated_by'];

        foreach ($updatable as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $fields[] = "updated_on = :updated_on";
        $params[':updated_on'] = date('Y-m-d H:i:s');

        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->getDb()->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
