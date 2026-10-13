<?php

namespace App\Models;

use App\Core\Model;
use PDO;
use PDOException;

class RevoProduct extends Model
{
    private string $table = 'revo_products';
    private int $paginationPerPage = Pagination_Default_Per_Page;

    public function getAllByCompany(int $companyId): array
    {
        return $this->getAllByCompanyPaginated($companyId, 1, 9999)['data'];
    }

    public function getAllByCompanyPaginated(
        int $companyId,
        int $page = 1,
        int $perPage = Pagination_Default_Per_Page,
        ?int $brand_id = null,
        ?int $has_submission = null,
        ?int $has_refund = null
    ): array {
        $page = max(1, $page);
        $perPage = max($this->paginationPerPage, $perPage);
        $offset = ($page - 1) * $perPage;

        $brandClause = '';
        if ($brand_id !== null) {
            $brandClause = ' AND p.brand_id = :brand_id';
        }

        $has_submissionClause = '';
        if ($has_submission === 1) {
            $has_submissionClause = " AND (
                SELECT COUNT(*)
                FROM revo_form_submission
                WHERE form_id = pf.id
                AND promote_id = COALESCE(pp.id, 0)
            ) > 0";
        } elseif ($has_submission === 0) {
            $has_submissionClause = " AND (
                SELECT COUNT(*)
                FROM revo_form_submission
                WHERE form_id = pf.id
                AND promote_id = COALESCE(pp.id, 0)
            ) = 0";
        }

        $has_refundClause = '';
        if ($has_refund === 1) {
            $has_refundClause = " AND (
                SELECT COUNT(*)
                FROM revo_refund_submission AS res
                INNER JOIN revo_form_submission AS s1 ON s1.id = res.submission_id
                WHERE s1.form_id = pf.id
                AND s1.promote_id = COALESCE(pp.id, 0)
            ) > 0";
        } elseif ($has_refund === 0) {
            $has_refundClause = " AND (
                SELECT COUNT(*)
                FROM revo_refund_submission AS res
                INNER JOIN revo_form_submission AS s1 ON s1.id = res.submission_id
                WHERE s1.form_id = pf.id
                AND s1.promote_id = COALESCE(pp.id, 0)
            ) = 0";
        }

        $countSql = "SELECT COUNT(DISTINCT p.id) AS total
                FROM {$this->table} AS p
                LEFT JOIN revo_forms AS pf ON p.id = pf.product_id
                INNER JOIN revo_common_ids AS c
                    ON p.company_id = c.company_id
                    AND c.mediator_id = -1
                LEFT JOIN revo_products_promote AS pp
                    ON p.id = pp.product_id
                    AND pp.common_id = c.unique_id
                WHERE p.company_id = :company_id
                AND pf.form_type = 1
                {$brandClause}
                {$has_submissionClause}
                {$has_refundClause}";

        $countStmt = $this->getDb()->prepare($countSql);
        $countStmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
        if ($brand_id !== null) {
            $countStmt->bindValue(':brand_id', $brand_id, PDO::PARAM_INT);
        }
        $countStmt->execute();
        $total = (int)($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $sql = $this->getCompanyProductsSelectSql($brandClause, $has_submissionClause, $has_refundClause) . "
                LIMIT :limit OFFSET :offset";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
        if ($brand_id !== null) {
            $stmt->bindValue(':brand_id', $brand_id, PDO::PARAM_INT);
        }
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

    private function getCompanyProductsSelectSql(
        string $brandClause = '',
        string $has_submissionClause = '',
        string $has_refundClause = ''
    ): string {
        return "SELECT 
    p.id AS id,
    p.company_id AS company_id,
    p.brand_id AS brand_id,
    p.name AS name,
    b.brand_name AS brand_name,
    p.photo AS photo,
    p.order_limit AS order_limit,
    p.is_multiple_submission_by_one AS is_multiple_submission_by_one,
    p.max_submission_by_one AS max_submission_by_one,
    p.default_completed_order AS default_completed_order,
    p.googlesheet_url AS googlesheet_url,
    p.status AS status,
    pf.status AS form_status,
    p.id AS product_id,
    COALESCE(pp.id, 0) AS promote_id,
    c.unique_id AS common_id,

    pf.id AS form_id,

    ( 
        SELECT COUNT(*) 
        FROM revo_form_submission 
        WHERE form_id = pf.id 
        AND promote_id = COALESCE(pp.id, 0)
    ) AS submission_count,


    ( 
        SELECT COUNT(*) FROM `revo_refund_submission` AS rs 
        INNER JOIN revo_form_submission AS fs ON rs.submission_id = fs.id 
        WHERE fs.form_id=pf.id AND fs.promote_id = COALESCE(pp.id, 0)
    ) AS refund_submission_count,


    COALESCE(pp.status, 0) AS promote_status,

    (SELECT id FROM revo_forms WHERE product_id=p.id AND form_type = 2) AS refund_form_id,

    pf.form_name AS form_name,
    pf.form_json AS form_json,
    pf.status AS form_status,
    pf.form_type AS form_type,
    
    p.updated_on AS updated_on,
    p.updated_by AS updated_by,
    p.created_on AS created_on,
    p.created_by AS created_by

FROM {$this->table} AS p

LEFT JOIN revo_brands AS b 
    ON p.brand_id = b.id

LEFT JOIN revo_forms AS pf 
    ON p.id = pf.product_id

INNER JOIN revo_common_ids AS c 
    ON p.company_id = c.company_id 
    AND c.mediator_id = -1

LEFT JOIN revo_products_promote AS pp 
    ON p.id = pp.product_id 
    AND pp.common_id = c.unique_id

WHERE p.company_id = :company_id AND pf.form_type = 1 
{$brandClause}
{$has_submissionClause}
{$has_refundClause}

ORDER BY p.id ASC";
    }

    public function getById(int $id): ?array
    {
        $sql = "SELECT 
                p.*,
                b.brand_name,
                pf.id as form_id,
                rf.id as refund_form_id,
                pf.form_name,
                pf.form_json,
                rf.form_json as refund_form_json,
                pf.status,
                rf.status as refund_form_status,
                pf.form_type,
                pf.status as form_status,
                pp.id as promote_id,
                pp.status as promote_status,
                c.unique_id as common_id,
                (SELECT COUNT(*) FROM revo_form_submission WHERE form_id = pf.id AND promote_id = COALESCE(pp.id, 0)) as submission_count,
                (SELECT COUNT(*) FROM revo_refund_submission AS rs
                    INNER JOIN revo_form_submission AS fs ON rs.submission_id = fs.id
                    WHERE fs.form_id = pf.id AND fs.promote_id = COALESCE(pp.id, 0)) as refund_submission_count
                FROM {$this->table} AS p
                INNER JOIN revo_brands AS b ON p.brand_id = b.id
                LEFT JOIN revo_forms AS pf ON p.id = pf.product_id AND pf.form_type = 1
                LEFT JOIN revo_forms AS rf ON p.id = rf.product_id AND rf.form_type = 2
                INNER JOIN revo_common_ids AS c ON p.company_id = c.company_id AND c.mediator_id = -1
                LEFT JOIN revo_products_promote AS pp ON p.id = pp.product_id AND pp.common_id = c.unique_id
                WHERE p.id = :id
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
            'brand_id',
            'name',
            
            'order_limit',
            'is_multiple_submission_by_one',
            'max_submission_by_one',
            'default_completed_order',
            'created_by',
        ];

        // print_r($data);
        // exit();

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
                    company_id,
                    brand_id,
                    name,
                    photo,
                    order_limit,
                    is_multiple_submission_by_one,
                    max_submission_by_one,
                    default_completed_order,
                    googlesheet_url,
                    status,
                    updated_on,
                    updated_by,
                    created_on,
                    created_by
                ) VALUES (
                    :company_id,
                    :brand_id,
                    :name,
                    :photo,
                    :order_limit,
                    :is_multiple_submission_by_one,
                    :max_submission_by_one,
                    :default_completed_order,
                    :googlesheet_url,
                    :status,
                    :updated_on,
                    :updated_by,
                    :created_on,
                    :created_by
                )";

        try {
            $stmt = $this->getDb()->prepare($sql);
            $stmt->bindValue(':company_id', (int)$data['company_id'], PDO::PARAM_INT);
            $stmt->bindValue(':brand_id', (int)$data['brand_id'], PDO::PARAM_INT);
            $stmt->bindValue(':name', $data['name'], PDO::PARAM_STR);
            $stmt->bindValue(':photo', $data['photo'], PDO::PARAM_STR);
            $stmt->bindValue(':order_limit', (int)$data['order_limit'], PDO::PARAM_INT);
            $stmt->bindValue(':is_multiple_submission_by_one', (int)$data['is_multiple_submission_by_one'], PDO::PARAM_INT);
            $stmt->bindValue(':max_submission_by_one', (int)$data['max_submission_by_one'], PDO::PARAM_INT);
            $stmt->bindValue(':default_completed_order', (int)$data['default_completed_order'], PDO::PARAM_INT);
            $stmt->bindValue(':googlesheet_url', (string)($data['googlesheet_url'] ?? ''), PDO::PARAM_STR);
            $stmt->bindValue(':status', 0, PDO::PARAM_INT);
            $stmt->bindValue(':updated_on', $now, PDO::PARAM_STR);
            $stmt->bindValue(':updated_by', (int)$updatedBy, PDO::PARAM_INT);
            $stmt->bindValue(':created_on', $now, PDO::PARAM_STR);
            $stmt->bindValue(':created_by', (int)$data['created_by'], PDO::PARAM_INT);

            $stmt->execute();


            //insert into revo_product_forms
            $productId = (int)$this->getDb()->lastInsertId();

            //Create a from
            $formModel = new RevoForm($this->getDb());
            //$form_json='[{"id":"heading_1771845504726_rgp2f","type":"heading","label":"'.$data['name'].'","required":false,"helperText":"","styles":{"fontSize":14,"color":"#111827","textAlign":"left"},"validation":{},"logic":{}},{"id":"divider_1771845505549_1v755","type":"divider","label":"","required":false,"helperText":"","styles":{"fontSize":14,"color":"#111827","textAlign":"left"},"validation":{},"logic":{}},{"id":"email_1771845507537_ltv2y","type":"email","label":"Email","placeholder":"Enter email","required":true,"helperText":"","styles":{"fontSize":14,"color":"#111827","textAlign":"left"},"validation":{},"logic":{}},{"id":"text_1771846070925_lg0y8","type":"text","label":"ORDER ID ( copy paste from app)","placeholder":"Enter text","required":true,"helperText":"","styles":{"fontSize":14,"color":"#111827","textAlign":"left"},"validation":{},"logic":{}},{"id":"text_1771846084131_w43ra","type":"text","label":"REVIEWER NAME[profile name]","placeholder":"Enter text","required":true,"helperText":"","styles":{"fontSize":14,"color":"#111827","textAlign":"left"},"validation":{},"logic":{}},{"id":"text_1771846106348_cl7yh","type":"text","label":"ORDER AMT","placeholder":"Enter text","required":true,"helperText":"","styles":{"fontSize":14,"color":"#111827","textAlign":"left"},"validation":{},"logic":{}},{"id":"dropdown_1771846143094_8kkml","type":"dropdown","label":"DEAL TYPE","required":true,"helperText":"","styles":{"fontSize":14,"color":"#111827","textAlign":"left"},"validation":{},"logic":{},"options":["Review","Rating","Only Order"]}]';

            $form_json = '[{"id":"heading_1771845504726_rgp2f","type":"heading","label":"'.$data['name'].'","required":false,"helperText":"","styles":{"fontSize":14,"color":"#111827","textAlign":"left"},"validation":{},"logic":{}},{"id":"divider_1771845505549_1v755","type":"divider","label":"","required":false,"helperText":"","styles":{"fontSize":14,"color":"#111827","textAlign":"left"},"validation":{},"logic":{}},{"id":"text_1771846070925_lg0y8","type":"text","label":"ORDER ID ( copy paste from app)","placeholder":"Enter text","required":true,"helperText":"","styles":{"fontSize":14,"color":"#111827","textAlign":"left"},"validation":{"pattern":"number"},"logic":{}},{"id":"text_1771846106348_cl7yh","type":"text","label":"ORDER AMT","placeholder":"Enter text","required":true,"helperText":"","styles":{"fontSize":14,"color":"#111827","textAlign":"left"},"validation":{},"logic":{}},{"id":"dropdown_1771846143094_8kkml","type":"dropdown","label":"DEAL TYPE","required":true,"helperText":"","styles":{"fontSize":14,"color":"#111827","textAlign":"left"},"validation":{},"logic":{},"options":["Review","Rating","Only Order"]},{"id":"text_1771846084131_w43ra","type":"text","label":"REVIEWER NAME[profile name]","placeholder":"Enter text","required":true,"helperText":"","styles":{"fontSize":14,"color":"#111827","textAlign":"left"},"validation":{},"logic":{}},{"id":"text_1785840337059_vsplb","type":"text","label":"Less","placeholder":"Enter text","required":true,"helperText":"","styles":{"fontSize":14,"color":"#111827","textAlign":"left"},"validation":{},"logic":{}},{"id":"image_upload_1785841152009_hr9qh","type":"image_upload","label":"Image Upload","placeholder":"Choose an image","required":false,"helperText":"","styles":{"fontSize":14,"color":"#111827","textAlign":"left"},"validation":{},"logic":{}}]';

            $form =$formModel->create([
                'product_id' => $productId,
                'form_name' => $data['name'],
                'form_json' => $form_json,
                'created_by' => (int)$data['created_by'],
                'status' => 0,
                'form_type' => 1,
            ]);


            //$refundJson = '[{"id":"heading_1778394501139_mqjdg","type":"heading","label":"Refund Form","required":false,"helperText":"","styles":{"fontSize":14,"color":"#111827","textAlign":"left"},"validation":{},"logic":{}},{"id":"divider_1778394542838_lcvyb","type":"divider","label":"","required":false,"helperText":"","styles":{"fontSize":14,"color":"#111827","textAlign":"left"},"validation":{},"logic":{}},{"id":"text_1778394545588_hv6lt","type":"text","label":"ORDER ID (dont type order id copy paste only)","placeholder":"Enter text","required":true,"helperText":"","styles":{"fontSize":14,"color":"#111827","textAlign":"left"},"validation":{},"logic":{}},{"id":"text_1780142330183_4dmcy","type":"text","label":"REVIEWER NAME","placeholder":"Enter text","required":true,"helperText":"","styles":{"fontSize":14,"color":"#111827","textAlign":"left"},"validation":{},"logic":{}},{"id":"text_1780142338324_ss95n","type":"text","label":"ORDER AMT","placeholder":"Enter text","required":true,"helperText":"","styles":{"fontSize":14,"color":"#111827","textAlign":"left"},"validation":{},"logic":{}},{"id":"text_1780142787280_k7his","type":"text","label":"REVIEW LINK","placeholder":"Enter text","required":true,"helperText":"","styles":{"fontSize":14,"color":"#111827","textAlign":"left"},"validation":{},"logic":{}},{"id":"text_1780142806796_fyi47","type":"text","label":"LESS","placeholder":"Enter text","required":true,"helperText":"","styles":{"fontSize":14,"color":"#111827","textAlign":"left"},"validation":{},"logic":{}},{"id":"text_1780142819788_88itz","type":"text","label":"UPI ID","placeholder":"Enter text","required":true,"helperText":"","styles":{"fontSize":14,"color":"#111827","textAlign":"left"},"validation":{},"logic":{}},{"id":"text_1780142832312_tjsid","type":"text","label":"MEDIATOR NAME","placeholder":"Enter text","required":true,"helperText":"","styles":{"fontSize":14,"color":"#111827","textAlign":"left"},"validation":{},"logic":{}}]';
            $refundJson = '[{"id":"heading_1778394501139_mqjdg","type":"heading","label":"Refund Form","required":false,"helperText":"","styles":{"fontSize":14,"color":"#111827","textAlign":"left"},"validation":{},"logic":{}},{"id":"divider_1778394542838_lcvyb","type":"divider","label":"","required":false,"helperText":"","styles":{"fontSize":14,"color":"#111827","textAlign":"left"},"validation":{},"logic":{}},{"id":"text_1780142787280_k7his","type":"text","label":"REVIEW LINK","placeholder":"Enter text","required":true,"helperText":"","styles":{"fontSize":14,"color":"#111827","textAlign":"left"},"validation":{},"logic":{}},{"id":"text_1780142806796_fyi47","type":"text","label":"LESS","placeholder":"Enter text","required":true,"helperText":"","styles":{"fontSize":14,"color":"#111827","textAlign":"left"},"validation":{},"logic":{}}]';
            $form =$formModel->create([
                'product_id' => $productId,
                'form_name' => "Refund ".$data['name'],
                'form_json' => $refundJson,
                'created_by' => (int)$data['created_by'],
                'status' => 0,
                'form_type' => 2,
            ]);

//            print_r($form);

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
            'brand_id',
            'name',
            'photo',
            'order_limit',
            'is_multiple_submission_by_one',
            'max_submission_by_one',
            'default_completed_order',
            'googlesheet_url',
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
        $params[':company_id'] = (int)$data['company_id'];
        $params[':id'] = (int)$data['id'];
        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = :id AND company_id = :company_id";

        $stmt = $this->getDb()->prepare($sql);
        //$stmt->bindValue(':company_id', (int)$data['company_id'], PDO::PARAM_INT);
        //$stmt->bindValue(':id', (int)$data['id'], PDO::PARAM_INT);
        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }
    

    public function promoteDemoteProduct(int $id, int $companyId, int $status): bool
    {
        $sql = "UPDATE {$this->table} SET status = :status WHERE id = :id AND company_id = :company_id";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
        $stmt->bindValue(':status', $status, PDO::PARAM_INT);
        $updated = $stmt->execute();
        if (!$updated) {
            return false;
        }

        //get common_id from revo_common_ids where company_id = $companyId
        $commonIdModel = new RevoCommonId($this->getDb());
        $commonId = $commonIdModel->getByCompanyId($companyId);
        if (!$commonId || !isset($commonId['unique_id'])) {
            return false;
        }
        $common_id = $commonId['unique_id'];
        

        $createdBy = $companyId;

        //Insert into revo_products_promote
        $promoteProductModel = new RevoProductPromote($this->getDb());
        $promoteProduct = $promoteProductModel->create([
            'product_id' => $id,
            'common_id' => $common_id,
            'status' => $status,
            'updated_by' => $createdBy,
            'created_by' => $createdBy,
        ]);
        return is_array($promoteProduct) ? (bool)($promoteProduct['status'] ?? false) : (bool)$promoteProduct;
    }

    public function getMediatorProducts(int $mediatorId): array
    {
        return $this->getMediatorProductsPaginated($mediatorId, 1, 9999)['data'];
    }


    public function getPromoteIdByProductId(int $id, int $userId, int $roleId): ?array
    {

        if ($roleId == 4) {

            $sqlUserConnections = "SELECT * FROM revo_connects WHERE user_id=:user_id AND status=1";
            $stmt = $this->getDb()->prepare($sqlUserConnections);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $userConnections= $stmt->fetchAll(PDO::FETCH_ASSOC) ?: null;

            $commonIds = [];
            foreach($userConnections as $userConnection){
                if($userConnection['company_id'] != -1){
                    $commonIdSql = "SELECT unique_id FROM revo_common_ids WHERE company_id=:company_id";
                    $commonstmt = $this->getDb()->prepare($commonIdSql);
                    $commonstmt->bindValue(':company_id', $userConnection['company_id'], PDO::PARAM_INT);
                    $commonstmt->execute();
                    $commonId = $commonstmt->fetch(PDO::FETCH_ASSOC) ?: null;
                    if($commonId){
                        $commonIds[] = $commonId['unique_id'];
                    }
                }else if($userConnection['mediator_id'] != -1){
                    $commonIdSql = "SELECT unique_id FROM revo_common_ids WHERE mediator_id=:mediator_id";
                    $commonstmt = $this->getDb()->prepare($commonIdSql);
                    $commonstmt->bindValue(':mediator_id', $userConnection['mediator_id'], PDO::PARAM_INT);
                    $commonstmt->execute();
                    $commonId = $commonstmt->fetch(PDO::FETCH_ASSOC) ?: null;
                    if($commonId){
                        $commonIds[] = $commonId['unique_id'];
                    }
                }

            }
            // print_r($userConnections);
            // print_r($commonIds);

            $sql = "
            SELECT pp.id as promote_id 
            FROM `revo_products_promote` as pp 
            WHERE pp.product_id=:id AND pp.common_id IN (".implode(',', $commonIds).") AND pp.status=1
            ";
            $stmt = $this->getDb()->prepare($sql);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
//            exit();
        }else{
            $sql = "
                SELECT pp.id as promote_id 
                FROM `revo_products_promote` as pp 
                INNER JOIN revo_company as m ON m.revo_user_id=:user_id
                INNER JOIN revo_common_ids as ci ON ci.company_id=m.id
                WHERE pp.product_id=:id AND ci.unique_id=pp.common_id AND pp.status=1
            ";
            if($roleId == 3){
                $sql = "
                SELECT pp.id as promote_id 
                FROM `revo_products_promote` as pp 
                INNER JOIN revo_mediator as m ON m.revo_user_id=:user_id
                INNER JOIN revo_common_ids as ci ON ci.mediator_id=m.id
                WHERE pp.product_id=:id AND ci.unique_id=pp.common_id AND pp.status=1
            ";
            }

            
            $stmt = $this->getDb()->prepare($sql);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

    }

    public function getProductByIdAndUserIdAndRole(int $id, int $mediatorId, int $userId, int $role): ?array
    {

        $sql = "SELECT 
                p.*,
                b.brand_name,
                f.id as form_id,
                (SELECT COUNT(*) FROM revo_form_submission WHERE form_id = f.id AND promote_id = pp.id) as submission_count,
                pp.id as promote_id
                FROM {$this->table} AS p
                INNER JOIN revo_brands AS b ON p.brand_id = b.id
                INNER JOIN revo_forms AS f ON p.id = f.product_id AND f.form_type = 1 AND f.status = 1
                INNER JOIN revo_common_ids AS co ON co.mediator_id = :mediator_id AND co.company_id = -1
                INNER JOIN revo_products_promote AS pp ON p.id = pp.product_id AND pp.common_id = co.unique_id
                INNER JOIN revo_connects AS c ON p.company_id = c.company_id AND c.mediator_id = :promote_mediator_id AND c.user_id = -1
                WHERE p.id = :id";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':mediator_id', $mediatorId, PDO::PARAM_INT);
        $stmt->bindValue(':promote_mediator_id', $mediatorId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getMediatorProductsPaginated(int $mediatorId, int $page = 1, int $perPage = Pagination_Default_Per_Page, int $brand_id = null, int $has_submission = null, int $has_refund = null): array
    {
        $page = max(1, $page);
        $perPage = max($this->paginationPerPage, $perPage);
        $offset = ($page - 1) * $perPage;
        $brand_id = $brand_id ?? null;
        $brandClause = "";
        if($brand_id!=null){
            $brandClause = " AND p.brand_id = $brand_id";
        }

        $has_submissionClause = "";
        if($has_submission!==null && $has_submission===1){
            $has_submissionClause = " AND (SELECT COUNT(*) FROM revo_form_submission WHERE form_id = f.id AND promote_id = pp.id) > 0";
        }else if($has_submission!==null && $has_submission===0){
            $has_submissionClause = " AND (SELECT COUNT(*) FROM revo_form_submission WHERE form_id = f.id AND promote_id = pp.id) = 0";
        }
        $has_refundClause = "";
        if($has_refund!==null && $has_refund===1){
            $has_refundClause = " AND (SELECT COUNT(res.id) FROM `revo_refund_submission` res  
                inner join revo_form_submission s1 ON s1.id=res.submission_id 
                WHERE s1.form_id=f.id AND s1.promote_id = pp.id) > 0";
        }else if($has_refund!==null && $has_refund===0){
            $has_refundClause = " AND (SELECT COUNT(res.id) FROM `revo_refund_submission` res  
                inner join revo_form_submission s1 ON s1.id=res.submission_id 
                WHERE s1.form_id=f.id AND s1.promote_id = pp.id) = 0";
        }

        $countSql = "SELECT COUNT(DISTINCT p.id) AS total
                FROM revo_products p
                INNER JOIN revo_connects c ON p.company_id = c.company_id
                INNER JOIN revo_forms f ON p.id = f.product_id
                INNER JOIN revo_common_ids co ON co.mediator_id = :mediator_id AND co.company_id = -1
                LEFT JOIN revo_products_promote pp ON pp.product_id = p.id AND pp.common_id = co.unique_id
                WHERE c.mediator_id = :promote_mediator_id
                AND c.user_id = -1
                AND p.status = 1
                AND c.status = 1
                AND f.status = 1
                AND f.form_type = 1 
                $brandClause
                $has_submissionClause
                $has_refundClause";


        $countStmt = $this->getDb()->prepare($countSql);
        $countStmt->bindValue(':mediator_id', $mediatorId, PDO::PARAM_INT);
        $countStmt->bindValue(':promote_mediator_id', $mediatorId, PDO::PARAM_INT);
        $countStmt->execute();
        $total = (int)($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);


        

        $sql = $this->getMediatorProductsSelectSql()." 
                $brandClause
                $has_submissionClause
                $has_refundClause
                ORDER BY p.id ASC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':mediator_id', $mediatorId, PDO::PARAM_INT);
        $stmt->bindValue(':promote_mediator_id', $mediatorId, PDO::PARAM_INT);
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

    private function getMediatorProductsSelectSql(): string
    {
        return "SELECT  p.*,
                b.brand_name,
                f.form_name,
                f.form_json,
                f.status,
                pp.id as promote_id,
                pp.status as promote_status,
                p.id as product_id,
                co.unique_id as common_id,
                p.id as product_id,
                (SELECT COUNT(*) FROM revo_form_submission WHERE form_id = f.id AND promote_id = pp.id) as submission_count, 
                (SELECT COUNT(res.id) FROM `revo_refund_submission` res  
                inner join revo_form_submission s1 ON s1.id=res.submission_id 
                WHERE s1.form_id=f.id AND s1.promote_id = pp.id) as refund_submission_count
                FROM revo_products p
                INNER JOIN revo_connects c ON p.company_id = c.company_id
                INNER JOIN revo_brands b ON p.brand_id = b.id
                INNER JOIN revo_forms f ON p.id = f.product_id
                INNER JOIN revo_common_ids co ON co.mediator_id = :mediator_id AND co.company_id = -1
                LEFT JOIN revo_products_promote pp ON pp.product_id = p.id AND pp.common_id = co.unique_id
                WHERE c.mediator_id = :promote_mediator_id
                AND c.user_id = -1
                AND p.status = 1
                AND c.status = 1
                AND f.status = 1
                AND f.form_type = 1";
    }
    
    public function getUserProducts(int $userId): array
    {
        $result = $this->getUserProductsPaginated($userId, 1, 9999);
        if ($result === false) {
            return false;
        }
        return $result['data'];
    }

    /**
     * @return array{data: array, pagination: array}|false
     */
    public function getUserProductsPaginated(int $userId, int $page = 1, int $perPage = Pagination_Default_Per_Page)
    {
        $page = max(1, $page);
        $perPage = max($this->paginationPerPage, $perPage);
        $offset = ($page - 1) * $perPage;

        $commonIds = $this->resolveUserCommonIds($userId);
        if ($commonIds === false) {
            return false;
        }

        if (empty($commonIds)) {
            return [
                'data' => [],
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => 0,
                    'total_pages' => 0,
                ],
            ];
        }

        $inPlaceholders = [];
        foreach ($commonIds as $index => $commonId) {
            $inPlaceholders[] = ":common_id_{$index}";
        }
        $inClause = implode(',', $inPlaceholders);

        $fromWhere = "FROM `revo_products_promote` as pp
                INNER JOIN revo_common_ids as co ON pp.common_id = co.unique_id
                INNER JOIN revo_products AS p ON p.id = pp.product_id
                INNER JOIN revo_brands AS b ON p.brand_id = b.id
                INNER JOIN revo_forms AS f ON p.id = f.product_id
                WHERE f.form_type = 1 AND p.status = 1 AND co.unique_id IN ({$inClause})";

        // $countSql = "SELECT COUNT(DISTINCT p.id) AS total {$fromWhere}";
        $countSql = "SELECT COUNT(*) AS total 
                {$fromWhere}
                ";
        $countStmt = $this->getDb()->prepare($countSql);
        foreach ($commonIds as $index => $commonId) {
            $countStmt->bindValue(":common_id_{$index}", $commonId, PDO::PARAM_STR);
        }
        $countStmt->execute();
        $total = (int)($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $sql = "SELECT p.*, b.brand_name, f.form_name, f.form_json,
                pp.status as promote_status, pp.id as promote_id, pp.common_id as common_id,
                p.id as product_id,
                (SELECT COUNT(*) FROM revo_form_submission
                    WHERE form_id = f.id AND user_id = :user_id AND promote_id = pp.id) as submission_count
                {$fromWhere} AND pp.status = 1 
                ORDER BY p.id ASC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        foreach ($commonIds as $index => $commonId) {
            $stmt->bindValue(":common_id_{$index}", $commonId, PDO::PARAM_STR);
        }
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

    /**
     * @return array<int, string>|false
     */
    private function resolveUserCommonIds(int $userId)
    {
        $connectModel = new RevoConnect($this->getDb());
        $userConnections = $connectModel->getUserConnections($userId);

        $commonIds = [];
        $commonIdModel = new RevoCommonId($this->getDb());

        foreach ($userConnections as $connection) {
            $companyId = $connection['company_id'];
            $mediatorId = $connection['mediator_id'];

            if ($companyId != -1) {
                $commonId = $commonIdModel->getByCompanyId($companyId);
            } elseif ($mediatorId != -1) {
                $commonId = $commonIdModel->getByMediatorId($mediatorId);
            } else {
                continue;
            }

            if (!$commonId || !isset($commonId['unique_id'])) {
                return false;
            }
            $commonIds[] = $commonId['unique_id'];
        }

        return $commonIds;
    }

    public function getByPromoteId(int $promoteId): ?array
    {
       $sql = "SELECT p.*,b.brand_name,f.form_name, f.form_json,
                (SELECT COUNT(*) FROM revo_form_submission WHERE form_id = f.id AND promote_id = pp.id) as submission_count
                FROM `revo_products_promote` AS pp 
                INNER JOIN revo_products AS p ON pp.product_id=p.id 
                INNER JOIN revo_brands AS b ON p.brand_id=b.id
                INNER JOIN revo_forms AS f ON p.id=f.product_id
                WHERE pp.id=:promote_id AND p.status=1 AND f.status=1 AND f.form_type = 1";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':promote_id', $promoteId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }   

    public function getMediatorByPromoteId(int $productId,int $mediatorId): ?array{
        $sql = "SELECT srci.unique_id,
                spp.status as promote_status, b.brand_name,
                f.form_name,f.status as form_status,
                (SELECT COUNT(*) FROM revo_form_submission WHERE form_id = f.id  AND promote_id = spp.id) as submission_count,
                COALESCE((SELECT COUNT(*) FROM revo_refund_submission AS rs
                    INNER JOIN revo_form_submission AS fs ON rs.submission_id = fs.id
                    WHERE fs.form_id = f.id AND fs.promote_id = COALESCE(spp.id, 0)),0) as refund_submission_count,
                p.* FROM `revo_products` AS p 
                INNER JOIN revo_brands AS b ON p.brand_id = b.id
                INNER JOIN revo_forms AS f ON p.id = f.product_id AND f.form_type=1 
                INNER JOIN revo_connects AS rc ON rc.company_id = p.company_id AND rc.mediator_id=:mediator_id1
                INNER JOIN revo_common_ids AS rci ON rci.company_id = p.company_id
                INNER JOIN revo_common_ids AS srci ON srci.mediator_id  = :mediator_id2
                INNER JOIN revo_products_promote AS pp ON pp.product_id = :product_id1 AND pp.common_id=rci.unique_id AND pp.status=1
                LEFT JOIN revo_products_promote AS spp ON spp.product_id = :product_id2 AND spp.common_id=srci.unique_id
                WHERE p.id=:product_id3";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':product_id1', $productId, PDO::PARAM_INT);
        $stmt->bindValue(':product_id2', $productId, PDO::PARAM_INT);
        $stmt->bindValue(':product_id3', $productId, PDO::PARAM_INT);

        $stmt->bindValue(':mediator_id1', $mediatorId, PDO::PARAM_INT);
        $stmt->bindValue(':mediator_id2', $mediatorId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getDashboard(int $userId, int $userRole): array
    {
        if ((int)$userRole === 2) {
            return $this->getCompanyDashboard($userId);
        }

        if ((int)$userRole === 3) {
            return $this->getMediatorDashboard($userId);
        }

        if ((int)$userRole === 4) {
            $usersDashboard = $this->getUserDashboard($userId);
            $usersDashboard['products'] = $this->getUserTotalProducts($userId);
            return $usersDashboard;
        }

        return [
            'mediators_connected' => 0,
            'users_connected' => 0,
            'products' => 0,
            'active_promoted_products' => 0,
            'demoted_products' => 0,
            'mediator_promoted_products' => 0,
            'mediator_demoted_products' => 0,
            'form_submissions' => 0,
            'refund_submissions' => 0,
            'accepted_refunds' => 0,
            'pending_refunds' => 0,
            'rejected_refunds' => 0,
        ];
    }

    private function getCompanyDashboard(int $userId): array
    {
        $companyModel = new RevoCompany($this->getDb());
        $company = $companyModel->getByUserId($userId);
        if (!$company || !isset($company['company'])) {
            return $this->emptyDashboard();
        }

        $companyId = (int)$company['company'];
        $connectModel = new RevoConnect($this->getDb());

        return [
            'mediators_connected' => count($connectModel->getCompaniesConnectedMediator($companyId)),
            'users_connected' => $this->fetchCount(
                "SELECT COUNT(*) FROM revo_connects
                WHERE company_id = :company_id AND user_id != -1 AND status = 1",
                [':company_id' => $companyId]
            ),
            'products' => $this->fetchCount(
                "SELECT COUNT(*) FROM {$this->table} WHERE company_id = :company_id",
                [':company_id' => $companyId]
            ),
            'active_promoted_products' => $this->fetchCount(
                "SELECT COUNT(DISTINCT p.id)
                FROM {$this->table} AS p
                INNER JOIN revo_common_ids AS ci ON ci.company_id = p.company_id AND ci.mediator_id = -1
                INNER JOIN revo_products_promote AS pp ON pp.product_id = p.id AND pp.common_id = ci.unique_id AND pp.status = 1
                WHERE p.company_id = :company_id",
                [':company_id' => $companyId]
            ),
            'demoted_products' => $this->fetchCount(
                "SELECT COUNT(DISTINCT p.id)
                FROM {$this->table} AS p
                INNER JOIN revo_common_ids AS ci ON ci.company_id = p.company_id AND ci.mediator_id = -1
                LEFT JOIN revo_products_promote AS pp ON pp.product_id = p.id AND pp.common_id = ci.unique_id 
                WHERE p.company_id = :company_id AND pp.status != 1",
                [':company_id' => $companyId]
            ),
            'mediator_promoted_products' => $this->fetchCount(
                "SELECT COUNT(DISTINCT p.id)
                FROM {$this->table} AS p
                INNER JOIN revo_connects AS rc ON rc.company_id = p.company_id AND rc.mediator_id != -1 AND rc.user_id = -1 AND rc.status = 1
                INNER JOIN revo_common_ids AS mci ON mci.mediator_id = rc.mediator_id AND mci.company_id = -1
                INNER JOIN revo_products_promote AS pp ON pp.product_id = p.id AND pp.common_id = mci.unique_id AND pp.status = 1
                WHERE p.company_id = :company_id",
                [':company_id' => $companyId]
            ),
            'mediator_demoted_products' => $this->fetchCount(
                "SELECT COUNT(DISTINCT p.id)
                FROM {$this->table} AS p
                INNER JOIN revo_connects AS rc ON rc.company_id = p.company_id AND rc.mediator_id != -1 AND rc.user_id = -1 AND rc.status = 1
                INNER JOIN revo_common_ids AS mci ON mci.mediator_id = rc.mediator_id AND mci.company_id = -1
                LEFT JOIN revo_products_promote AS pp ON pp.product_id = p.id AND pp.common_id = mci.unique_id 
                WHERE p.company_id = :company_id AND pp.status = 1",
                [':company_id' => $companyId]
            ),
            'form_submissions' => $this->fetchCount(
                "SELECT COUNT(*)
                FROM revo_form_submission AS fs
                INNER JOIN revo_forms AS f ON fs.form_id = f.id
                INNER JOIN {$this->table} AS p ON f.product_id = p.id
                WHERE p.company_id = :company_id AND f.form_type = 1",
                [':company_id' => $companyId]
            ),
            'refund_submissions' => $this->fetchCount(
                "SELECT COUNT(*)
                FROM revo_refund_submission AS rs
                INNER JOIN revo_form_submission AS fs ON rs.submission_id = fs.id
                INNER JOIN revo_forms AS f ON fs.form_id = f.id
                INNER JOIN {$this->table} AS p ON f.product_id = p.id
                WHERE p.company_id = :company_id",
                [':company_id' => $companyId]
            ),
            'accepted_refunds' => $this->fetchCompanyRefundCountByStatus($companyId, 1),
            'pending_refunds' => $this->fetchCompanyRefundCountByStatus($companyId, 0),
            'rejected_refunds' => $this->fetchCompanyRefundCountByStatus($companyId, 2),
        ];
    }

    private function getMediatorDashboard(int $userId): array
    {
        $mediatorModel = new RevoMediator($this->getDb());
        $mediator = $mediatorModel->getByUserId($userId);
        if (!$mediator || !isset($mediator['id'])) {
            return $this->emptyDashboard();
        }

        $mediatorId = (int)$mediator['id'];
        $connectModel = new RevoConnect($this->getDb());

        return [
            'companies_connected' => count($connectModel->getMediatorConnectedCompanies($mediatorId, 1)),
            'users_connected' => $this->fetchCount(
                "SELECT COUNT(*) FROM revo_connects
                WHERE mediator_id = :mediator_id AND user_id != -1 AND status = 1",
                [':mediator_id' => $mediatorId]
            ),
            'products' => $this->fetchCount(
                "SELECT COUNT(DISTINCT p.id)
                FROM {$this->table} AS p
                INNER JOIN revo_connects AS rc ON rc.company_id = p.company_id AND rc.mediator_id = :mediator_id AND rc.user_id = -1 AND rc.status = 1
                WHERE p.status = 1",
                [':mediator_id' => $mediatorId]
            ),
            'active_promoted_products' => $this->fetchCount(
                "SELECT COUNT(DISTINCT p.id)
                FROM {$this->table} AS p
                INNER JOIN revo_connects AS rc ON rc.company_id = p.company_id AND rc.mediator_id = :mediator_id AND rc.user_id = -1 AND rc.status = 1
                INNER JOIN revo_common_ids AS mci ON mci.mediator_id = :source_mediator_id AND mci.company_id = -1
                INNER JOIN revo_products_promote AS pp ON pp.product_id = p.id AND pp.common_id = mci.unique_id AND pp.status = 1
                WHERE p.status = 1",
                [
                    ':mediator_id' => $mediatorId,
                    ':source_mediator_id' => $mediatorId,
                ]
            ),
            'demoted_products' => $this->fetchCount(
                "SELECT COUNT(DISTINCT p.id)
                FROM {$this->table} AS p
                INNER JOIN revo_connects AS rc ON rc.company_id = p.company_id AND rc.mediator_id = :mediator_id AND rc.user_id = -1 AND rc.status = 1
                INNER JOIN revo_common_ids AS mci ON mci.mediator_id = :source_mediator_id AND mci.company_id = -1
                LEFT JOIN revo_products_promote AS pp ON pp.product_id = p.id AND pp.common_id = mci.unique_id AND pp.status = 1
                WHERE p.status = 1 AND pp.id IS NULL",
                [
                    ':mediator_id' => $mediatorId,
                    ':source_mediator_id' => $mediatorId,
                ]
            ),
            'form_submissions' => $this->fetchCount(
                "SELECT COUNT(*)
                FROM revo_form_submission AS fs
                INNER JOIN revo_forms AS f ON fs.form_id = f.id
                INNER JOIN {$this->table} AS p ON f.product_id = p.id
                INNER JOIN revo_connects AS rc ON rc.company_id = p.company_id AND rc.mediator_id = :mediator_id AND rc.user_id = -1 AND rc.status = 1
                INNER JOIN revo_common_ids AS mci ON mci.mediator_id = :source_mediator_id AND mci.company_id = -1
                INNER JOIN revo_products_promote AS pp ON pp.product_id = p.id AND pp.common_id = mci.unique_id
                WHERE f.form_type = 1 AND fs.promote_id = pp.id",
                [
                    ':mediator_id' => $mediatorId,
                    ':source_mediator_id' => $mediatorId,
                ]
            ),
            'refund_submissions' => $this->fetchCount(
                "SELECT COUNT(*)
                FROM revo_refund_submission AS rs
                INNER JOIN revo_form_submission AS fs ON rs.submission_id = fs.id
                INNER JOIN revo_forms AS f ON fs.form_id = f.id
                INNER JOIN {$this->table} AS p ON f.product_id = p.id
                INNER JOIN revo_connects AS rc ON rc.company_id = p.company_id AND rc.mediator_id = :mediator_id AND rc.user_id = -1 AND rc.status = 1
                INNER JOIN revo_common_ids AS mci ON mci.mediator_id = :source_mediator_id AND mci.company_id = -1
                INNER JOIN revo_products_promote AS pp ON pp.product_id = p.id AND pp.common_id = mci.unique_id
                WHERE fs.promote_id = pp.id",
                [
                    ':mediator_id' => $mediatorId,
                    ':source_mediator_id' => $mediatorId,
                ]
            ),
            'accepted_refunds' => $this->fetchMediatorRefundCountByStatus($mediatorId, 1),
            'pending_refunds' => $this->fetchMediatorRefundCountByStatus($mediatorId, 0),
            'rejected_refunds' => $this->fetchMediatorRefundCountByStatus($mediatorId, 2),
        ];
    }


    public function getUserTotalProducts(int $userId): int{

        $commonIds = $this->resolveUserCommonIds($userId);
        if (!$commonIds) {
        return $this->emptyDashboard();
        }

        $inPlaceholders = [];
        foreach ($commonIds as $index => $commonId) {
            $inPlaceholders[] = ":common_id_{$index}";
        }
        $inClause = implode(',', $inPlaceholders);

        $fromWhere = "FROM `revo_products_promote` as pp
                INNER JOIN revo_common_ids as co ON pp.common_id = co.unique_id
                INNER JOIN revo_products AS p ON p.id = pp.product_id
                INNER JOIN revo_brands AS b ON p.brand_id = b.id
                INNER JOIN revo_forms AS f ON p.id = f.product_id
                WHERE f.form_type = 1 AND co.unique_id IN ({$inClause})";

                        // $countSql = "SELECT COUNT(DISTINCT p.id) AS total {$fromWhere}";
        $countSql = "SELECT COUNT(*) AS total 
                {$fromWhere}
                ";
        $countStmt = $this->getDb()->prepare($countSql);
        foreach ($commonIds as $index => $commonId) {
            $countStmt->bindValue(":common_id_{$index}", $commonId, PDO::PARAM_STR);
        }
        $countStmt->execute();
        $total = (int)($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        return $total;
    }

    private function getUserDashboard(int $userId): array
    {


        return [
            'companies_connected' => $this->fetchCount(
                "SELECT COUNT(*)
                FROM revo_connects
                WHERE user_id = :user_id AND status = 1",
                [
                    ':user_id' => $userId,
                ]
            ),
            'products' => '
            
            ',
            'form_submissions' => $this->fetchCount(
                "SELECT COUNT(*)
                FROM revo_form_submission AS fs
                INNER JOIN revo_forms AS f ON fs.form_id = f.id
                INNER JOIN {$this->table} AS p ON f.product_id = p.id
                WHERE fs.user_id = :user_id AND f.form_type = 1",
                [
                    ':user_id' => $userId,
                ]
            ),
            'refund_submissions' => $this->fetchCount(
                "SELECT COUNT(*)
                FROM revo_refund_submission AS rs
                INNER JOIN revo_form_submission AS fs ON rs.submission_id = fs.id
                INNER JOIN revo_forms AS f ON fs.form_id = f.id
                INNER JOIN {$this->table} AS p ON f.product_id = p.id
                WHERE rs.user_id = :user_id",
                [
                    ':user_id' => $userId,
                ]
            ),
            'accepted_refunds' => $this->fetchCount(
                "SELECT COUNT(*)
                FROM revo_refund_submission AS rs
                INNER JOIN revo_form_submission AS fs ON rs.submission_id = fs.id
                INNER JOIN revo_forms AS f ON fs.form_id = f.id
                INNER JOIN {$this->table} AS p ON f.product_id = p.id
                WHERE rs.user_id = :user_id AND rs.accept_status = 1",
                [':user_id' => $userId]
            )   ,
            'pending_refunds' => $this->fetchCount(
                "SELECT COUNT(*)
                FROM revo_refund_submission AS rs
                INNER JOIN revo_form_submission AS fs ON rs.submission_id = fs.id
                INNER JOIN revo_forms AS f ON fs.form_id = f.id
                INNER JOIN {$this->table} AS p ON f.product_id = p.id
                WHERE rs.user_id = :user_id AND rs.accept_status = 0",
                [':user_id' => $userId]
            ),
            'rejected_refunds' => $this->fetchCount(
                "SELECT COUNT(*)
                FROM revo_refund_submission AS rs
                INNER JOIN revo_form_submission AS fs ON rs.submission_id = fs.id
                INNER JOIN revo_forms AS f ON fs.form_id = f.id
                INNER JOIN {$this->table} AS p ON f.product_id = p.id
                WHERE rs.user_id = :user_id AND rs.accept_status = 2",
                [':user_id' => $userId]
            ),
        ];
    }


    private function emptyDashboard(): array
    {
        return [
            'mediators_connected' => 0,
            'users_connected' => 0,
            'products' => 0,
            'active_promoted_products' => 0,
            'demoted_products' => 0,
            'mediator_promoted_products' => 0,
            'mediator_demoted_products' => 0,
            'form_submissions' => 0,
            'refund_submissions' => 0,
            'accepted_refunds' => 0,
            'pending_refunds' => 0,
            'rejected_refunds' => 0,
        ];
    }

    private function fetchCount(string $sql, array $params): int
    {
        $stmt = $this->getDb()->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        return (int)($stmt->fetchColumn() ?: 0);
    }

    private function fetchCompanyRefundCountByStatus(int $companyId, int $status): int
    {
        return $this->fetchCount(
            "SELECT COUNT(*)
            FROM revo_refund_submission AS rs
            INNER JOIN revo_form_submission AS fs ON rs.submission_id = fs.id
            INNER JOIN revo_forms AS f ON fs.form_id = f.id
            INNER JOIN {$this->table} AS p ON f.product_id = p.id
            WHERE p.company_id = :company_id AND rs.accept_status = :accept_status",
            [
                ':company_id' => $companyId,
                ':accept_status' => $status,
            ]
        );
    }

    private function fetchMediatorRefundCountByStatus(int $mediatorId, int $status): int
    {
        return $this->fetchCount(
            "SELECT COUNT(*)
            FROM revo_refund_submission AS rs
            INNER JOIN revo_form_submission AS fs ON rs.submission_id = fs.id
            INNER JOIN revo_forms AS f ON fs.form_id = f.id
            INNER JOIN {$this->table} AS p ON f.product_id = p.id
            INNER JOIN revo_connects AS rc ON rc.company_id = p.company_id AND rc.mediator_id = :mediator_id AND rc.user_id = -1 AND rc.status = 1
            INNER JOIN revo_common_ids AS mci ON mci.mediator_id = :source_mediator_id AND mci.company_id = -1
            INNER JOIN revo_products_promote AS pp ON pp.product_id = p.id AND pp.common_id = mci.unique_id
            WHERE fs.promote_id = pp.id AND rs.accept_status = :accept_status",
            [
                ':mediator_id' => $mediatorId,
                ':source_mediator_id' => $mediatorId,
                ':accept_status' => $status,
            ]
        );
    }
}

