<?php

namespace App\Models;

use App\Core\Model;
use PDO;
use PDOException;

class RevoMediator extends Model
{
    private string $table = 'revo_mediator';

    public function getAll(): array
    {
        $paginated = $this->getPaginated(1, 20);
        return $paginated['data'];
    }

    public function getPaginated(int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;

        $countSql = "SELECT COUNT(*) AS total FROM {$this->table}";
        $countStmt = $this->getDb()->query($countSql);
        $total = (int)($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $sql = "SELECT 
                    id,
                    revo_user_id,
                    mediator_name as name,
                    profile_pic,
                    alias,
                    size,
                    group_link,
                    slot_in_a_month,
                    description,
                    upated_by,
                    updated_on,
                    created_by,
                    created_on
                FROM {$this->table}
                ORDER BY id ASC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->getDb()->prepare($sql);
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

    public function getById(int $id): ?array
    {
        $sql = "SELECT 
                    id,
                    revo_user_id,
                    mediator_name as name,
                    profile_pic,
                    alias,
                    size,
                    group_link,
                    slot_in_a_month,
                    description,
                    upated_by,
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

    public function getByUserId(int $userId): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE revo_user_id = :userId LIMIT 1";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function create(array $data): array
    {
        $required = [
            'revo_user_id',
            'mediator_name',
            'profile_pic',
            'alias',
            'size',
            'group_link',
            'slot_in_a_month',
            'description',
            'created_by',
        ];

        // foreach ($required as $field) {
        //     if (empty($data[$field])) {
        //         return [
        //             'status' => false,
        //             'error' => 400,
        //             'message' => "Field {$field} is required",
        //         ];
        //     }
        // }

        $now = date('Y-m-d H:i:s');
        $upatedBy = $data['upated_by'] ?? $data['created_by'];

        $sql = "INSERT INTO {$this->table} (
                    revo_user_id,
                    mediator_name,
                    profile_pic,
                    alias,
                    size,
                    group_link,
                    slot_in_a_month,
                    description,
                    upated_by,
                    updated_on,
                    created_by,
                    created_on
                ) VALUES (
                    :revo_user_id,
                    :mediator_name,
                    :profile_pic,
                    :alias,
                    :size,
                    :group_link,
                    :slot_in_a_month,
                    :description,
                    :upated_by,
                    :updated_on,
                    :created_by,
                    :created_on
                )";

        try {
            $stmt = $this->getDb()->prepare($sql);
            $stmt->bindValue(':revo_user_id', (int)$data['revo_user_id'], PDO::PARAM_INT);
            $stmt->bindValue(':mediator_name', $data['mediator_name'], PDO::PARAM_STR);
            $stmt->bindValue(':profile_pic', $data['profile_pic'], PDO::PARAM_STR);
            $stmt->bindValue(':alias', $data['alias'], PDO::PARAM_STR);
            $stmt->bindValue(':size', (int)$data['size'], PDO::PARAM_INT);
            $stmt->bindValue(':group_link', $data['group_link'], PDO::PARAM_STR);
            $stmt->bindValue(':slot_in_a_month', (int)$data['slot_in_a_month'], PDO::PARAM_INT);
            $stmt->bindValue(':description', $data['description'], PDO::PARAM_STR);
            $stmt->bindValue(':upated_by', (int)$upatedBy, PDO::PARAM_INT);
            $stmt->bindValue(':updated_on', $now, PDO::PARAM_STR);
            $stmt->bindValue(':created_by', (int)$data['created_by'], PDO::PARAM_INT);
            $stmt->bindValue(':created_on', $now, PDO::PARAM_STR);

            $stmt->execute();

            $newMediatorId = (int) $this->getDb()->lastInsertId();
            $commonIdModel = new RevoCommonId($this->getDb());
            $updatedBy = $data['created_by'];
            $commonIdModel->create([
                'company_id' => -1,
                'mediator_id' => $newMediatorId,
                'created_by' => (int) $data['created_by'],
                'updated_by' => (int) $updatedBy,
            ]);

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
            'mediator_name',
            'profile_pic',
            'alias',
            'size',
            'group_link',
            'slot_in_a_month',
            'description',
            'upated_by',
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


    public function getMediatorFromUserId(int $userId): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE revo_user_id = :userId LIMIT 1";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}

