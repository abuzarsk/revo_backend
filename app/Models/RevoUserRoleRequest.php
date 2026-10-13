<?php

namespace App\Models;

use App\Core\Model;
use PDO;
use PDOException;

class RevoUserRoleRequest extends Model
{
    private string $table = 'revo_user_role_requests';

    public function getAll(): array
    {
        $sql = "SELECT 
                    revo_role_request_id,
                    revo_user_id,
                    revo_role_id,
                    status,
                    updated_by,
                    updated_on,
                    created_by,
                    created_on
                FROM {$this->table}
                ORDER BY revo_role_request_id ASC";

        $stmt = $this->getDb()->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getById(int $id): ?array
    {
        $sql = "SELECT 
                    revo_role_request_id,
                    revo_user_id,
                    revo_role_id,
                    status,
                    updated_by,
                    updated_on,
                    created_by,
                    created_on
                FROM {$this->table}
                WHERE revo_role_request_id = :id
                LIMIT 1";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function getByUserId(int $userId): array
    {
        $sql = "SELECT 
                    revo_role_request_id,
                    revo_user_id,
                    revo_role_id,
                    status,
                    updated_by,
                    updated_on,
                    created_by,
                    created_on
                FROM {$this->table}
                WHERE revo_user_id = :user_id
                ORDER BY revo_role_request_id ASC";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get rows where revo_role_id = given role, with pagination.
     */
    public function getByRoleIdPaginated(int $revoRoleId, int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;

        // Total count for this role
        $countSql = "SELECT COUNT(*) AS total FROM {$this->table} WHERE revo_role_id = :revo_role_id";
        $countStmt = $this->getDb()->prepare($countSql);
        $countStmt->bindValue(':revo_role_id', $revoRoleId, PDO::PARAM_INT);
        $countStmt->execute();
        $total = (int)($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        // Page data
        $sql = "SELECT 
                    revo_role_request_id,
                    revo_user_id,
                    revo_role_id,
                    status,
                    updated_by,
                    updated_on,
                    created_by,
                    created_on
                FROM {$this->table}
                WHERE revo_role_id = :revo_role_id
                ORDER BY revo_role_request_id ASC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':revo_role_id', $revoRoleId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'data' => $data,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $perPage > 0 ? (int)ceil($total / $perPage) : 0,
            ],
        ];
    }

    public function create(array $data): array
    {
        $required = [
            'revo_user_id',
            'revo_role_id',
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

        $status = 0;

        $sql = "INSERT INTO {$this->table} (
                    revo_user_id,
                    revo_role_id,
                    status,
                    updated_by,
                    updated_on,
                    created_by,
                    created_on
                ) VALUES (
                    :revo_user_id,
                    :revo_role_id,
                    :status,
                    :updated_by,
                    :updated_on,
                    :created_by,
                    :created_on
                )";

        try {
            $stmt = $this->getDb()->prepare($sql);
            $stmt->bindValue(':revo_user_id', (int)$data['revo_user_id'], PDO::PARAM_INT);
            $stmt->bindValue(':revo_role_id', (int)$data['revo_role_id'], PDO::PARAM_INT);
            $stmt->bindValue(':status', $status ?? null, PDO::PARAM_STR);
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
            'revo_role_id',
            'status',
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

        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE revo_role_request_id = :id";

        $stmt = $this->getDb()->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE revo_role_request_id = :id";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function deleteByUserId(int $userId): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE revo_user_id = :user_id";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Update status for all role requests of a given user.
     */
    public function updateStatusByUserId(int $userId, int $status): bool
    {
        $sql = "UPDATE {$this->table}
                SET status = :status, updated_on = :updated_on
                WHERE revo_user_id = :user_id";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':status', $status, PDO::PARAM_INT);
        $stmt->bindValue(':updated_on', date('Y-m-d H:i:s'), PDO::PARAM_STR);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);

        return $stmt->execute();
    }
}


