<?php

namespace App\Models;

use App\Core\Model;
use PDO;
use PDOException;

class RevoBrand extends Model
{
    private string $table = 'revo_brands';
    private int $paginationPerPage = Pagination_Default_Brand_Per_Page;

    public function getAll(int $companyId): array
    {
        return $this->getPaginatedByCompany($companyId, 1, 9999)['data'];
    }

    public function getPaginatedByCompany(int $companyId, int $page = 1, int $perPage = Pagination_Default_Brand_Per_Page): array
    {
        $page = max(1, $page);
        $perPage = max($this->paginationPerPage, $perPage);
        $offset = ($page - 1) * $perPage;

        $countSql = "SELECT COUNT(*) AS total FROM {$this->table} WHERE company_id = :companyId";
        $countStmt = $this->getDb()->prepare($countSql);
        $countStmt->bindValue(':companyId', $companyId, PDO::PARAM_INT);
        $countStmt->execute();
        $total = (int)($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $sql = "SELECT
                    id,
                    company_id,
                    brand_name,
                    updated_by,
                    updated_on,
                    created_by,
                    created_on
                FROM {$this->table}
                WHERE company_id = :companyId
                ORDER BY id ASC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':companyId', $companyId, PDO::PARAM_INT);
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
                    company_id,
                    brand_name,
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
            'company_id',
            'brand_name',
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
                    company_id,
                    brand_name,
                    updated_by,
                    updated_on,
                    created_by,
                    created_on
                ) VALUES (
                    :company_id,
                    :brand_name,
                    :updated_by,
                    :updated_on,
                    :created_by,
                    :created_on
                )";

        try {
            $stmt = $this->getDb()->prepare($sql);
            $stmt->bindValue(':company_id', (int)$data['company_id'], PDO::PARAM_INT);
            $stmt->bindValue(':brand_name', $data['brand_name'], PDO::PARAM_STR);
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
            ///'company_id',
            'brand_name',
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
        $params[':company_id'] = (int)$data['company_id'];
        
        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = :id AND company_id = :company_id";
        //print_r($params);
        //exit();

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

    public function getBrandsList(int $role, int $userId): array
    {
        if ((int)$role === 2) {
            $companyModel = new RevoCompany($this->getDb());
            $company = $companyModel->getByUserId($userId);
            if (!$company || empty($company['company'])) {
                return [];
            }

            $sql = "SELECT
                        id,
                        company_id,
                        brand_name
                    FROM {$this->table}
                    WHERE company_id = :companyId
                    ORDER BY id ASC";

            $stmt = $this->getDb()->prepare($sql);
            $stmt->bindValue(':companyId', (int)$company['company'], PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        if ((int)$role === 3) {
            $mediatorModel = new RevoMediator($this->getDb());
            $mediator = $mediatorModel->getMediatorFromUserId($userId);
            if (!$mediator || empty($mediator['id'])) {
                return [];
            }

            $sql = "SELECT
                        b.id,
                        b.company_id,
                        b.brand_name
                    FROM {$this->table} AS b
                    INNER JOIN revo_connects AS rc ON b.company_id = rc.company_id
                    WHERE rc.mediator_id = :mediator_id
                    AND rc.user_id = -1
                    AND rc.status = 1
                    ORDER BY b.id ASC";

            $stmt = $this->getDb()->prepare($sql);
            $stmt->bindValue(':mediator_id', (int)$mediator['id'], PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        return [];
    }
}

