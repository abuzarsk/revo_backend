<?php

namespace App\Models;

use App\Core\Model;
use PDO;
use PDOException;

class RevoCompanyTemp extends Model
{
    private string $table = 'revo_company_temp';

    public function getAll(): array
    {
        $sql = "SELECT 
                    id,
                    revo_user_id,
                    logo,
                    alias,
                    company_size,
                    group_link,
                    slot_in_a_month,
                    description,
                    updated_by,
                    updated_on,
                    created_by,
                    created_on
                FROM {$this->table}
                ORDER BY id ASC";

        $stmt = $this->getDb()->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    

    public function getByUserId(int $userId): array
    {
        $sql = "SELECT 
                    revo_user_id,
                    logo,
                    alias,
                    company_size,
                    group_link,
                    slot_in_a_month,
                    description,
                    updated_on,
                    created_on
                FROM {$this->table}
                WHERE revo_user_id = :revo_user_id
                ORDER BY id ASC";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':revo_user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getById(int $id): ?array
    {
        $sql = "SELECT 
                    id,
                    revo_user_id,
                    logo,
                    alias,
                    company_size,
                    group_link,
                    slot_in_a_month,
                    description,
                    updated_by,
                    updated_on,
                    created_by,
                    created_on
                FROM {$this->table}
                WHERE id = :id
                LIMIT 1";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function create(array $data): array
    {
        $required = [
            'revo_user_id',
            'logo',
            'alias',
            'company_size',
            'group_link',
            'slot_in_a_month',
            'description',
            'created_by',
        ];

        foreach ($required as $field) {
            if (empty($data[$field])) {
                return [
                    'status' => false,
                    'error' => 400,
                    'message' => "Field {$field} is required",
                ];
            }
        }

        $now = date('Y-m-d H:i:s');
        $updatedBy = $data['updated_by'] ?? $data['created_by'];

        $sql = "INSERT INTO {$this->table} (
                    revo_user_id,
                    logo,
                    alias,
                    company_size,
                    group_link,
                    slot_in_a_month,
                    description,
                    updated_by,
                    updated_on,
                    created_by,
                    created_on
                ) VALUES (
                    :revo_user_id,
                    :logo,
                    :alias,
                    :company_size,
                    :group_link,
                    :slot_in_a_month,
                    :description,
                    :updated_by,
                    :updated_on,
                    :created_by,
                    :created_on
                )";

        try {
            $stmt = $this->getDb()->prepare($sql);
            $stmt->bindValue(':revo_user_id', (int)$data['revo_user_id'], PDO::PARAM_INT);
            $stmt->bindValue(':logo', $data['logo'], PDO::PARAM_STR);
            $stmt->bindValue(':alias', $data['alias'], PDO::PARAM_STR);
            $stmt->bindValue(':company_size', (int)$data['company_size'], PDO::PARAM_INT);
            $stmt->bindValue(':group_link', $data['group_link'], PDO::PARAM_STR);
            $stmt->bindValue(':slot_in_a_month', (int)$data['slot_in_a_month'], PDO::PARAM_INT);
            $stmt->bindValue(':description', $data['description'], PDO::PARAM_STR);
            $stmt->bindValue(':updated_by', (int)$updatedBy, PDO::PARAM_INT);
            $stmt->bindValue(':updated_on', $now, PDO::PARAM_STR);
            $stmt->bindValue(':created_by', (int)$data['created_by'], PDO::PARAM_INT);
            $stmt->bindValue(':created_on', $now, PDO::PARAM_STR);

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
        $fields = [];
        $params = [':id' => $id];

        $updatable = [
            'revo_user_id',
            'logo',
            'alias',
            'company_size',
            'group_link',
            'slot_in_a_month',
            'description',
            'updated_by',
        ];

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

