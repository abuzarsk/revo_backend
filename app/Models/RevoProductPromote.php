<?php

namespace App\Models;

use App\Core\Model;
use PDO;
use PDOException;

class RevoProductPromote extends Model
{
    private string $table = 'revo_products_promote';

    public function getAllByMediator(int $mediatorId): array
    {
        $sql = "SELECT
                    id,
                    product_id,
                    common_id,
                    status,
                    updated_on,
                    updated_by,
                    created_on,
                    created_by
                FROM {$this->table}
                WHERE common_id = :common_id
                ORDER BY id DESC";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':common_id', $mediatorId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getById(int $id): ?array
    {
        $sql = "SELECT
                    id,
                    product_id,
                    common_id,
                    status,
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

    public function create(array $data): array
    {
        $required = ['product_id', 'common_id', 'status', 'created_by'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                return [
                    'status' => false,
                    'error' => 400,
                    'message' => "Field {$field} is required",
                ];
            }
        }

        //check if product_id and common_id already exists

        $sql = "SELECT * FROM {$this->table} WHERE product_id = :product_id AND common_id = :common_id LIMIT 1";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':product_id', (int)$data['product_id'], PDO::PARAM_INT);
        $stmt->bindValue(':common_id', (int)$data['common_id'], PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            
            //update the status
            $sql = "UPDATE {$this->table} SET status = :status WHERE product_id = :product_id AND common_id = :common_id";
            $stmt = $this->getDb()->prepare($sql);
            $stmt->bindValue(':status', (int)$data['status'], PDO::PARAM_INT);
            $stmt->bindValue(':product_id', (int)$data['product_id'], PDO::PARAM_INT);
            $stmt->bindValue(':common_id', (int)$data['common_id'], PDO::PARAM_INT);
            $stmt->execute();
            return [
                'status' => true,
                'error' => 200,
                'message' => "Product and common id already exists",
            ];

        }else{

        $now = date('Y-m-d H:i:s');
        $updatedBy = $data['updated_by'] ?? $data['created_by'];

        $sql = "INSERT INTO {$this->table} (
                    product_id,
                    common_id,
                    status,
                    updated_on,
                    updated_by,
                    created_on,
                    created_by
                ) VALUES (
                    :product_id,
                    :common_id,
                    :status,
                    :updated_on,
                    :updated_by,
                    :created_on,
                    :created_by
                )";

        try {
            $stmt = $this->getDb()->prepare($sql);
            $stmt->bindValue(':product_id', (int)$data['product_id'], PDO::PARAM_INT);
            $stmt->bindValue(':common_id', (int)$data['common_id'], PDO::PARAM_INT);
            $stmt->bindValue(':status', (int)$data['status'], PDO::PARAM_INT);
            $stmt->bindValue(':updated_on', $now, PDO::PARAM_STR);
            $stmt->bindValue(':updated_by', (int)$updatedBy, PDO::PARAM_INT);
            $stmt->bindValue(':created_on', $now, PDO::PARAM_STR);
            $stmt->bindValue(':created_by', (int)$data['created_by'], PDO::PARAM_INT);
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
    }

    public function getByProductIdByMediator(int $productId, int $commonId): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE product_id = :product_id AND common_id = :common_id LIMIT 1";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $stmt->bindValue(':common_id', $commonId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [':id' => $id];
        $updatable = ['product_id', 'common_id', 'status', 'updated_by'];

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

    public function getPromotedProducts(int $mediatorId): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE common_id = :common_id";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':common_id', $mediatorId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
