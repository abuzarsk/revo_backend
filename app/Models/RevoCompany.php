<?php

namespace App\Models;

use App\Core\Model;
use PDO;
use PDOException;

class RevoCompany extends Model
{
    private string $table = 'revo_company';

    public function getAll(): array
    {
        // Backwards-compatible helper: return first 20 records from page 1
        $paginated = $this->getPaginated(1, 20);
        return $paginated['data'];
    }

    public function getPaginated(int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;

        // Total count
        $countSql = "SELECT COUNT(*) AS total FROM {$this->table}";
        $countStmt = $this->getDb()->query($countSql);
        $total = (int)($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        // Page data
        $sql = "SELECT 
                    id as company,
                    company_name as name,
                    revo_user_id as user,
                    logo,
                    alias,
                    company_size,
                    group_link,
                    slot_in_a_month,
                    description,
                    status,
                    updated_by,
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
                    company_name as name,
                    revo_user_id,
                    logo,
                    alias,
                    company_size,
                    group_link,
                    slot_in_a_month,
                    description,
                    status,
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

    public function getByUserId(int $userId): ?array
    {
        $sql = "SELECT 
                    id as company,
                    company_name as name,
                    revo_user_id,
                    logo,
                    alias,
                    company_size,
                    group_link,
                    slot_in_a_month,
                    description,
                    status,
                    updated_by,
                    updated_on,
                    created_by,
                    created_on
                FROM {$this->table}
                WHERE revo_user_id = :userId
                LIMIT 1";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function create(array $data): array
    {
        $required = [
            'revo_user_name',
            'revo_user_id',
            'logo',
            'alias',
            'company_size',
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
        $updatedBy = $data['updated_by'] ?? $data['created_by'];

        $sql = "INSERT INTO {$this->table} (
                    revo_user_id,
                    company_name,
                    logo,
                    alias,
                    company_size,
                    group_link,
                    slot_in_a_month,
                    description,
                    status,
                    updated_by,
                    updated_on,
                    created_by,
                    created_on
                ) VALUES (
                    :revo_user_id,
                    :company_name,
                    :logo,
                    :alias,
                    :company_size,
                    :group_link,
                    :slot_in_a_month,
                    :description,
                    :status,
                    :updated_by,
                    :updated_on,
                    :created_by,
                    :created_on
                )";

        try {
            $stmt = $this->getDb()->prepare($sql);
            $stmt->bindValue(':revo_user_id', (int)$data['revo_user_id'], PDO::PARAM_INT);
            $stmt->bindValue(':company_name', $data['revo_user_name'], PDO::PARAM_STR);
            $stmt->bindValue(':logo', $data['logo'], PDO::PARAM_STR);
            $stmt->bindValue(':alias', $data['alias'], PDO::PARAM_STR);
            $stmt->bindValue(':company_size', (int)$data['company_size'], PDO::PARAM_INT);
            $stmt->bindValue(':group_link', $data['group_link'], PDO::PARAM_STR);
            $stmt->bindValue(':slot_in_a_month', (int)$data['slot_in_a_month'], PDO::PARAM_INT);
            $stmt->bindValue(':description', $data['description'], PDO::PARAM_STR);
            $stmt->bindValue(':status', $data['status'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(':updated_by', (int)$updatedBy, PDO::PARAM_INT);
            $stmt->bindValue(':updated_on', $now, PDO::PARAM_STR);
            $stmt->bindValue(':created_by', (int)$data['created_by'], PDO::PARAM_INT);
            $stmt->bindValue(':created_on', $now, PDO::PARAM_STR);

            $stmt->execute();

            $newCompanyId = (int) $this->getDb()->lastInsertId();
            $commonIdModel = new RevoCommonId($this->getDb());
            $commonIdModel->create([
                'company_id' => $newCompanyId,
                'mediator_id' => -1,
                'created_by' => (int) $data['created_by'],
                'updated_by' => (int) $updatedBy,
            ]);
            
            return [
                'status' => true,
                'error' => 201,
                'id' => $newCompanyId,
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
            'company_name',
            'revo_user_id',
            'logo',
            'alias',
            'company_size',
            'group_link',
            'slot_in_a_month',
            'description',
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

    public function getByAlias(string $alias, int $userId, int $role): array
    {
        $sql = "SELECT     
                    id as company,
                    company_name as name,
                    revo_user_id as user,
                    logo,
                    alias,
                    company_size,
                    group_link,
                    slot_in_a_month,
                    description,
                    status,
                    updated_by,
                    updated_on,
                    created_by,
                    created_on FROM {$this->table} WHERE alias LIKE :alias";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':alias', '%' . $alias . '%', PDO::PARAM_STR);
        $stmt->execute();
        $companies = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];


        return $companies;
    }

    public function searchByAlias(string $alias, $user): array
    {


        $sql = "SELECT revo_user_id, id, logo as logo, alias, 2 AS role FROM {$this->table} WHERE alias LIKE :company_alias
                UNION
                SELECT revo_user_id, id, profile_pic as logo, alias, 3 AS role FROM revo_mediator WHERE alias LIKE :mediator_alias";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':company_alias', '%' . $alias . '%', PDO::PARAM_STR);
        $stmt->bindValue(':mediator_alias', '%' . $alias . '%', PDO::PARAM_STR);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $model = new RevoConnect($this->getDb());
        foreach ($data as &$item) {
            $item['connected'] =false;
            $connectdata = $model->getbycompanyormediatoragainstuser(
                ((int) $item['role'] === 2) ? (int) $item['id'] : -1,
                ((int) $item['role'] === 3) ? (int) $item['id'] : -1,
                (int) $user['sub']
            );

            //print_r($connectdata);
            if(count($connectdata) > 0){

                $item['connected'] = false;
                for($i = 0; $i < count($connectdata); $i++){
                    if($connectdata[$i]['status'] == 1){
                        $item['connected'] = true;
                        break;
                    }
                }
                //$item['connected'] = $connectdata[0]['status'] == 1 ? true : false;
            }
            //print_r($item['connect']);
        }
        unset($item);
        return $data;
    }
}

