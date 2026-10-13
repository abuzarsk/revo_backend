<?php

namespace App\Models;

use App\Core\Model;
use PDO;
use PDOException;

class RevoForm extends Model
{
    private string $table = 'revo_forms';

    public function getAllByProduct(int $productId, int $formType=1): array
    {
        $sql = "SELECT
                    id,
                    product_id,
                    form_name,
                    form_json,
                    status,
                    form_type,
                    updated_on,
                    updated_by,
                    created_on,
                    created_by
                FROM {$this->table}
                WHERE product_id = :product_id AND form_type = :form_type
                ORDER BY id ASC";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $stmt->bindValue(':form_type', $formType, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }


    public function getById(int $id, int $userId): ?array
    {

       $sql = "SELECT
                    f.id,
                    p.id as product_id,
                    p.name as product_name,
                    p.is_multiple_submission_by_one as is_multiple_submission_by_one,
                    p.max_submission_by_one as max_submission_by_one,
                    p.default_completed_order as default_completed_order,
                    (SELECT COUNT(*) FROM revo_form_submission WHERE form_id = f.id AND user_id = $userId) as submission_count,
                    f.form_name,
                    f.form_json,
                    f.status,
                    f.form_type,
                    f.updated_on,
                    f.updated_by,
                    f.created_on,
                    f.created_by
                FROM {$this->table} as f
                LEFT JOIN revo_products as p ON f.product_id = p.id
                WHERE f.id = $id 
                LIMIT 1";

        $stmt = $this->getDb()->prepare($sql);
        //$stmt->bindValue(':id', $id, PDO::PARAM_INT);
        //$stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        //$stmt->bindValue(':form_type', $formType, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    // public function getByPromoteId(int $id, int $userId, int $formType=1): ?array
    // {
    //     if($formType == 2){
    //         $somegets = "
    //         0 as is_multiple_submission_by_one,
    //         1 as max_submission_by_one,
    //         0 as default_completed_order
    //         ";
    //     }else{
    //         $somegets = "p.is_multiple_submission_by_one as is_multiple_submission_by_one,
    //                 p.max_submission_by_one as max_submission_by_one,
    //                 p.default_completed_order as default_completed_order,
    //                 (SELECT COUNT(*) FROM revo_form_submission WHERE form_id = f.id AND user_id = $userId) as submission_count";
    //     }

    //    $sql = "SELECT
    //                 f.id,
    //                 p.id as product_id,
    //                 p.name as product_name,

    //                 $somegets,
    //                 f.form_name,
    //                 f.form_json,
    //                 f.status,
    //                 f.form_type,
    //                 f.updated_on,
    //                 f.updated_by,
    //                 f.created_on,
    //                 f.created_by
    //             FROM {$this->table} as f
    //             INNER JOIN revo_products_promote AS pp ON f.product_id = pp.product_id
    //             LEFT JOIN revo_products as p ON pp.product_id = p.id
    //             WHERE pp.id = $id AND f.form_type = $formType
    //             LIMIT 1";

    //     $stmt = $this->getDb()->prepare($sql);
    //     //$stmt->bindValue(':id', $id, PDO::PARAM_INT);
    //     //$stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    //     //$stmt->bindValue(':form_type', $formType, PDO::PARAM_INT);
    //     $stmt->execute();

    //     $row = $stmt->fetch(PDO::FETCH_ASSOC);
    //     return $row !== false ? $row : null;
    // }


    public function create(array $data): array
    {
        $required = [
            'product_id',
            'form_name',
            'form_json',
            'status',
            'form_type',
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

        $now = date('Y-m-d H:i:s');
        $updatedBy = $data['updated_by'] ?? $data['created_by'];

        $sql = "INSERT INTO {$this->table} (
                    product_id,
                    form_name,
                    form_json,
                    status,
                    form_type,
                    updated_on,
                    updated_by,
                    created_on,
                    created_by
                ) VALUES (
                    :product_id,
                    :form_name,
                    :form_json,
                    :status,
                    :form_type,
                    :updated_on,
                    :updated_by,
                    :created_on,
                    :created_by
                )";

        try {
            $stmt = $this->getDb()->prepare($sql);
            $stmt->bindValue(':product_id', (int)$data['product_id'], PDO::PARAM_INT);
            $stmt->bindValue(':form_name', (string)$data['form_name'], PDO::PARAM_STR);
            $stmt->bindValue(':form_json', (string)$data['form_json'], PDO::PARAM_STR);
            $stmt->bindValue(':status', (int)$data['status'], PDO::PARAM_INT);
            $stmt->bindValue(':form_type', (int)$data['form_type'], PDO::PARAM_INT);
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
    

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [':id' => $id];

        $updatable = [
            'product_id',
            'form_name',
            'form_json',
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

    public function getByPromoteId(int $promoteId, int $userId=null, int $formType=1): ?array
    {

        $where = "";
        if($userId){
            $where = " AND user_id = :user_id";    
        }
        $somegets = "p.is_multiple_submission_by_one as is_multiple_submission_by_one,
        p.max_submission_by_one as max_submission_by_one,
        p.default_completed_order as default_completed_order
        ";
        if($formType == 2){
            $somegets = "
            0 as is_multiple_submission_by_one,
            1 as max_submission_by_one,
            0 as default_completed_order
            ";
        }
        $sql = "SELECT f.*,
        $somegets,
        p.id as product_id,
        p.name as product_name,
        p.photo as product_image,
        b.brand_name as brand_name,
        pp.common_id,
        (SELECT COUNT(*) FROM revo_form_submission WHERE form_id = f.id $where  AND promote_id = pp.id) as submission_count
         FROM `revo_products_promote` AS pp 
        INNER JOIN revo_forms AS f ON pp.product_id=f.product_id
        INNER JOIN revo_products AS p ON pp.product_id=p.id
        INNER JOIN revo_brands AS b ON p.brand_id=b.id
        WHERE pp.id=:promote_id AND f.status=1 AND f.form_type = :form_type AND p.status = 1";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':promote_id', $promoteId, PDO::PARAM_INT);
        if($userId){
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        }
        $stmt->bindValue(':form_type', $formType, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        //print_r($result);

        if($result!="" && $result!=null && $result!="null" && $result!="undefined"){
        
           $result['submission_count'] = 0;
           $sql_unique = "SELECT * FROM revo_common_ids WHERE unique_id = :unique_id";
           $stmt_unique = $this->getDb()->prepare($sql_unique);
           $stmt_unique->bindValue(':unique_id', $result['common_id'], PDO::PARAM_INT);
           $stmt_unique->execute();
           $result_unique = $stmt_unique->fetch(PDO::FETCH_ASSOC);
           //print_r($result_unique);
           //exit;
           if($result_unique['company_id'] == -1){
            $meditor = "SELECT mediator_name as company_name, profile_pic as company_image, alias as company_alias 
            FROM revo_mediator WHERE id = :id";
            $stmt_meditor = $this->getDb()->prepare($meditor);
            $stmt_meditor->bindValue(':id', $result_unique['mediator_id'], PDO::PARAM_INT);
            $stmt_meditor->execute();
            $result_meditor = $stmt_meditor->fetch(PDO::FETCH_ASSOC);
            $result['company'] = $result_meditor;
            $result['role'] = 3;
           }else{
            $meditor = "SELECT company_name, logo as company_image, alias as company_alias FROM revo_company WHERE id = :id";
            $stmt_meditor = $this->getDb()->prepare($meditor);
            $stmt_meditor->bindValue(':id', $result_unique['company_id'], PDO::PARAM_INT);
            $stmt_meditor->execute();
            $result_meditor = $stmt_meditor->fetch(PDO::FETCH_ASSOC);
            $result['company'] = $result_meditor;
            $result['role'] = 2;

           }
        }

        
        return $result!="" && $result!=null && $result!="null" && $result!="undefined" ? $result : [];
    }


    public function getByProductIdAndFormType(int $productId, int $formType): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE product_id = :product_id AND form_type = :form_type LIMIT 1";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $stmt->bindValue(':form_type', $formType, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

}
