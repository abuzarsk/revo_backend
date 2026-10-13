<?php

namespace App\Models;

use App\Core\Model;
use PDO;
use PDOException;

class RevoUser extends Model
{
    private string $table = 'revo_users';

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
                    revo_user_id,
                    revo_user_name,
                    revo_user_email,
                    revo_user_phone,
                    revo_user_default_role,
                    revo_user_created_source,
                    updated_by,
                    updated_on,
                    created_by,
                    created_on
                FROM {$this->table}
                ORDER BY revo_user_id ASC
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
                    revo_user_id,
                    revo_user_name,
                    revo_user_email,
                    revo_user_phone,
                    revo_user_default_role,
                    revo_user_created_source,
                    updated_by,
                    updated_on,
                    created_by,
                    created_on
                FROM {$this->table}
                WHERE revo_user_id = :id
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
            'revo_user_name',
            'revo_user_email',
            'revo_user_phone',
            'revo_user_password',
            'revo_user_default_role',
            'revo_user_created_source',
            'created_by',
        ];

        //print_r($data);
        if(isset($data['revo_user_created_source']) && $data['revo_user_created_source']=='google'){
            foreach ($required as $field) {
                if ($field !== 'revo_user_phone' && empty($data[$field])) {
                    return [
                        'status' => false,
                        'error' => 400,
                        'message' => "Field {$field} is required",
                    ];
                }
            }
        }else{
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return [
                        'status' => false,
                        'error' => 400,
                        'message' => "Field {$field} is required",
                    ];
                }
            }
        }


        

        

        $now = date('Y-m-d H:i:s');
        $updatedBy = $data['updated_by'] ?? $data['created_by'];


        $hash = password_hash((string)$data['revo_user_password'], PASSWORD_DEFAULT);
        

        $sql = "INSERT INTO {$this->table} (
                    revo_user_name,
                    revo_user_email,
                    revo_user_phone,
                    revo_user_password,
                    revo_user_default_role,
                    revo_user_created_source,
                    updated_by,
                    updated_on,
                    created_by,
                    created_on
                ) VALUES (
                    :revo_user_name,
                    :revo_user_email,
                    :revo_user_phone,
                    :revo_user_password,
                    :revo_user_default_role,
                    :revo_user_created_source,
                    :updated_by,
                    :updated_on,
                    :created_by,
                    :created_on
                )";

        try {
            $stmt = $this->getDb()->prepare($sql);
            $stmt->bindValue(':revo_user_name', $data['revo_user_name'], PDO::PARAM_STR);
            $stmt->bindValue(':revo_user_email', $data['revo_user_email'], PDO::PARAM_STR);
            $stmt->bindValue(':revo_user_phone', $data['revo_user_phone'], PDO::PARAM_STR);
            $stmt->bindValue(':revo_user_password', $hash, PDO::PARAM_STR);
            $stmt->bindValue(':revo_user_default_role', (int)$data['revo_user_default_role'], PDO::PARAM_INT);
            $stmt->bindValue(':revo_user_created_source', $data['revo_user_created_source'], PDO::PARAM_STR);
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
            // Handle duplicate email or other DB errors
            $code = $e->getCode() === '23000' ? 409 : 500;

            return [
                'status' => false,
                'error' => $code,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function checkifUserExists(string $email, string $phone): bool
    {
        $sql = "SELECT * FROM {$this->table} WHERE revo_user_email = :email OR revo_user_phone = :phone LIMIT 1";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->bindValue(':phone', $phone, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }

    public function getByEmailOrPhone(string $email, string $phone): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE revo_user_email = :email OR revo_user_phone = :phone LIMIT 1";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->bindValue(':phone', $phone, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function getByEmail(string $email): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE revo_user_email = :email LIMIT 1";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function getByPhone(string $phone): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE revo_user_phone = :phone LIMIT 1";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':phone', $phone, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }  
    
    public function verifyCredentials(string $password,string $emailormobile,string $val)
    {
        //$adminModel = $this->model('Admin');
        if($emailormobile=='EMAIL'){
            $admin = $this->getByEmail($val);
        }else{
            $admin = $this->getByPhone($val);
        }
        if (!$admin || empty($admin['revo_user_password'])) {
            return null;
        }

        if (!password_verify($password, $admin['revo_user_password'])) {
            return null;
        }
        return $admin;
    }
    
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [':id' => $id];

        $updatable = [
            'revo_user_name',
            'revo_user_email',
            'revo_user_phone',
            'revo_user_default_role',
            'revo_user_created_source',
            'updated_by',
        ];

        foreach ($updatable as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }

        if (array_key_exists('revo_user_password', $data)) {
            $fields[] = "revo_user_password = :revo_user_password";
            $params[':revo_user_password'] = password_hash((string)$data['revo_user_password'], PASSWORD_DEFAULT);
        }

        if (empty($fields)) {
            return false;
        }

        $fields[] = "updated_on = :updated_on";
        $params[':updated_on'] = date('Y-m-d H:i:s');

        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE revo_user_id = :id";

        $stmt = $this->getDb()->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE revo_user_id = :id";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }
}

