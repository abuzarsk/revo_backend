<?php

namespace App\Models;

use App\Core\Model;
use PDO;
use PDOException;

class RevoUserBank extends Model
{
    private string $table = 'revo_user_bank';

    private function getBaseSelectColumns(string $alias = ''): string
    {
        $p = $alias !== '' ? "{$alias}." : '';

        return "{$p}id,
                    {$p}user_id,
                    {$p}revo_user_upi,
                    {$p}revo_upi_display_name,
                    {$p}revo_user_bank_name,
                    {$p}revo_bank_holder_name,
                    {$p}revo_back_account_no,
                    {$p}revo_bank_ifsc,
                    {$p}updated_on,
                    {$p}updated_by,
                    {$p}created_on,
                    {$p}created_by";
    }

    public function getById(int $id): ?array
    {
        $sql = "SELECT
                    {$this->getBaseSelectColumns()}
                FROM {$this->table}
                WHERE id = :id
                LIMIT 1";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function getByUserId(int $userId): ?array
    {
        $sql = "SELECT
                    {$this->getBaseSelectColumns()}
                FROM {$this->table}
                WHERE user_id = :user_id
                ORDER BY id DESC
                LIMIT 1";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function create(array $data): array
    {
        $required = [
            'user_id',
            'created_by',
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                return [
                    'status' => false,
                    'error' => 400,
                    'message' => "Field {$field} is required",
                ];
            }
        }

        $existing = $this->getByUserId((int)$data['user_id']);
        if ($existing) {
            return [
                'status' => false,
                'error' => 409,
                'message' => 'Bank details already exist for this user',
            ];
        }

        $now = date('Y-m-d H:i:s');
        $updatedBy = $data['updated_by'] ?? $data['created_by'];

        $sql = "INSERT INTO {$this->table} (
                    user_id,
                    revo_user_upi,
                    revo_upi_display_name,
                    revo_user_bank_name,
                    revo_bank_holder_name,
                    revo_back_account_no,
                    revo_bank_ifsc,
                    updated_on,
                    updated_by,
                    created_on,
                    created_by
                ) VALUES (
                    :user_id,
                    :revo_user_upi,
                    :revo_upi_display_name,
                    :revo_user_bank_name,
                    :revo_bank_holder_name,
                    :revo_back_account_no,
                    :revo_bank_ifsc,
                    :updated_on,
                    :updated_by,
                    :created_on,
                    :created_by
                )";

        try {
            $stmt = $this->getDb()->prepare($sql);
            $stmt->bindValue(':user_id', (int)@$data['user_id'], PDO::PARAM_INT);
            $stmt->bindValue(':revo_user_upi', (string)@$data['revo_user_upi'], PDO::PARAM_STR);
            $stmt->bindValue(':revo_upi_display_name', (string)@$data['revo_upi_display_name'], PDO::PARAM_STR);
            $stmt->bindValue(':revo_user_bank_name', (string)@$data['revo_user_bank_name'], PDO::PARAM_STR);
            $stmt->bindValue(':revo_bank_holder_name', (string)@$data['revo_bank_holder_name'], PDO::PARAM_STR);
            $stmt->bindValue(':revo_back_account_no', (string)@$data['revo_back_account_no'], PDO::PARAM_STR);
            $stmt->bindValue(':revo_bank_ifsc', (string)@$data['revo_bank_ifsc'], PDO::PARAM_STR);
            $stmt->bindValue(':updated_on', $now, PDO::PARAM_STR);
            $stmt->bindValue(':updated_by', (int)$updatedBy, PDO::PARAM_INT);
            $stmt->bindValue(':created_on', $now, PDO::PARAM_STR);
            $stmt->bindValue(':created_by', (int)@$data['created_by'], PDO::PARAM_INT);
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
            'revo_user_upi',
            'revo_user_bank_name',
            'revo_bank_holder_name',
            'revo_back_account_no',
            'revo_bank_ifsc',
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

        $fields[] = 'updated_on = :updated_on';
        $params[':updated_on'] = date('Y-m-d H:i:s');

        $sql = 'UPDATE ' . $this->table . ' SET ' . implode(', ', $fields) . ' WHERE id = :id';
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
