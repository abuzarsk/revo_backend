<?php

namespace App\Models;

use App\Core\Model;
use App\Services\GoogleSheetsService;
use PDO;
use PDOException;

class RevoFormSubmission extends Model
{
    private string $table = 'revo_form_submission';
    private int $paginationPerPage = Pagination_Default_Subsmission_Per_Page;

    public function getAllByForm(int $formId): array
    {
        $sql = "SELECT
                    id,
                    form_id,
                    promote_id,
                    submission,
                    order_id,
                    order_amount,
                    reviewer_name,
                    deal_type,
                    `less`,
                    user_id,
                    updated_on,
                    updated_by,
                    created_on,
                    created_by
                FROM {$this->table}
                WHERE form_id = :form_id
                ORDER BY id ASC";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':form_id', $formId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    
    public function getAllByFormSubmissions(int $userId, int $promote_id): array
    {
        return $this->getAllByFormSubmissionsPaginated($userId, $promote_id, 1, 9999)['data'];
    }

    public function getAllByFormSubmissionsPaginated(
        int $userId,
        int $company_id,
        int $mediator_id,
        int $product_id,
        int $page = 1,
        int $perPage = Pagination_Default_Subsmission_Per_Page
    ): array {
        $page = max(1, $page);
        $perPage = max($this->paginationPerPage, $perPage);
        $offset = ($page - 1) * $perPage;

        $mediator_join = "LEFT JOIN revo_refund_submission AS rs ON s.id = rs.submission_id";

        if($mediator_id != -1){
            $mediator_join = "INNER JOIN revo_common_ids AS ci ON ci.company_id=$company_id AND ci.mediator_id=$mediator_id
                INNER JOIN revo_products_promote AS pp ON pp.product_id = pp.product_id  AND s.promote_id=pp.id AND pp.common_id= ci.unique_id
                LEFT JOIN revo_refund_submission AS rs ON s.id = rs.submission_id AND s.promote_id = pp.id 
                ";
        }

        $fromWhere = "FROM revo_form_submission AS s
                INNER JOIN revo_users AS u ON s.user_id = u.revo_user_id
                INNER JOIN revo_forms AS f ON f.id = s.form_id

                {$mediator_join}
                WHERE f.product_id = $product_id";

        $countSql = "SELECT COUNT(*) AS total {$fromWhere}"; 
        $countStmt = $this->getDb()->prepare($countSql);
        // $countStmt->bindValue(':promote_id', $promote_id, PDO::PARAM_INT);
        $countStmt->execute();
        $total = (int)($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $sql = "SELECT
                    s.id,
                    s.form_id,
                    s.promote_id,
                    s.user_id,
                    s.submission,
                    (SELECT product_id FROM revo_forms WHERE id = s.form_id LIMIT 1) as product_id,
                    (SELECT COUNT(*) FROM revo_refund_submission WHERE submission_id = s.id) as refund_submission_count,
                    rs.submission_text as refund_submission_text,
                    COALESCE(rs.accept_status, 0) as refund_accept_status,
                    COALESCE(rs.accept_reject_remark, '') as refund_accept_reject_remark,
                    COALESCE(rs.accept_reject_screenshot, '') as refund_accept_reject_screenshot,
                    COALESCE((SELECT form_json FROM revo_forms WHERE id = s.form_id AND status = 1 AND form_type = 1 LIMIT 1), '[]') as form_heads,
                    COALESCE((SELECT form_json FROM revo_forms WHERE product_id = (SELECT product_id FROM revo_forms WHERE id = s.form_id LIMIT 1) AND status = 1 AND form_type = 2 LIMIT 1), '[]') as form_submission_heads,
                    s.updated_on,
                    s.updated_by,
                    s.created_on,
                    s.created_by,
                    u.revo_user_name,
                    u.revo_user_email,
                    u.revo_user_phone
                {$fromWhere}

                ORDER BY s.id ASC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->getDb()->prepare($sql);
        //$stmt->bindValue(':promote_id', $promote_id, PDO::PARAM_INT);
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
     * All product-form submissions for one company, with refund status.
     */
    public function getCompanySubmissionsPaginated(
        int $companyId,
        int $page = 1,
        int $perPage = 10,
        int $productId = 0,
        string $status = 'all',
        string $search = ''
    ): array {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;
        $search = trim($search);
        if (strlen($search) > 80) {
            $search = substr($search, 0, 80);
        }

        $allowedStatus = ['all', 'action', 'submitted', 'under_review', 'approved', 'rejected', 'paid'];
        if (!in_array($status, $allowedStatus, true)) {
            $status = 'all';
        }

        $productClause = $productId > 0 ? ' AND p.id = :product_id' : '';
        $fromWhere = "FROM revo_form_submission AS s
            INNER JOIN revo_forms AS f ON f.id = s.form_id AND f.form_type = 1
            INNER JOIN revo_products AS p ON p.id = f.product_id
            INNER JOIN revo_company AS c ON c.id = p.company_id
            INNER JOIN revo_users AS u ON u.revo_user_id = s.user_id
            LEFT JOIN revo_refund_submission AS rs
                ON rs.submission_id = s.id
                AND rs.id = (
                    SELECT MAX(r2.id)
                    FROM revo_refund_submission AS r2
                    WHERE r2.submission_id = s.id
                )
            WHERE p.company_id = :company_id{$productClause}";

        $summarySql = "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN rs.id IS NULL THEN 1 ELSE 0 END) AS submitted,
                SUM(CASE WHEN rs.id IS NOT NULL AND COALESCE(rs.accept_status, 0) = 0 THEN 1 ELSE 0 END) AS under_review,
                SUM(CASE WHEN COALESCE(rs.accept_status, 0) = 2 THEN 1 ELSE 0 END) AS rejected,
                SUM(CASE WHEN COALESCE(rs.accept_status, 0) = 1 THEN 1 ELSE 0 END) AS paid
            {$fromWhere}";
        $summaryStmt = $this->getDb()->prepare($summarySql);
        $this->bindCompanySubmissionFilters($summaryStmt, $companyId, $productId);
        $summaryStmt->execute();
        $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $statusClause = match ($status) {
            'submitted' => ' AND rs.id IS NULL',
            'under_review', 'action' => ' AND rs.id IS NOT NULL AND COALESCE(rs.accept_status, 0) = 0',
            'rejected' => ' AND COALESCE(rs.accept_status, 0) = 2',
            'paid' => ' AND COALESCE(rs.accept_status, 0) = 1',
            'approved' => ' AND 1 = 0',
            default => '',
        };
        $searchClause = $search !== ''
            ? ' AND (CAST(s.id AS CHAR) LIKE :q_id OR u.revo_user_name LIKE :q_member OR p.name LIKE :q_product OR c.company_name LIKE :q_company)'
            : '';

        $filteredWhere = $fromWhere . $statusClause . $searchClause;
        $countStmt = $this->getDb()->prepare("SELECT COUNT(*) AS total {$filteredWhere}");
        $this->bindCompanySubmissionFilters($countStmt, $companyId, $productId, $search);
        $countStmt->execute();
        $total = (int)($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $sql = "SELECT
                s.id,
                s.form_id,
                s.promote_id,
                s.user_id,
                s.submission,
                s.order_amount AS product_price,
                s.`less` AS less,
                f.form_json,
                s.created_on,
                s.updated_on,
                p.id AS product_id,
                p.name AS product_name,
                c.company_name,
                u.revo_user_name,
                u.revo_user_email,
                (SELECT COUNT(*) FROM revo_refund_submission WHERE submission_id = s.id) AS refund_submission_count,
                rs.id AS refund_submission_id,
                rs.submission_text AS refund_submission,
                COALESCE(rs.accept_status, 0) AS refund_accept_status,
                COALESCE(rs.accept_reject_remark, '') AS refund_accept_reject_remark,
                COALESCE(rs.accept_reject_screenshot, '') AS refund_screenshot,
                (
                    SELECT rf.form_json
                    FROM revo_forms AS rf
                    WHERE rf.product_id = p.id AND rf.form_type = 2 AND rf.status = 1
                    ORDER BY rf.id DESC
                    LIMIT 1
                ) AS refund_form_json,
                CASE
                    WHEN rs.id IS NULL THEN 'submitted'
                    WHEN COALESCE(rs.accept_status, 0) = 1 THEN 'paid'
                    WHEN COALESCE(rs.accept_status, 0) = 2 THEN 'rejected'
                    ELSE 'under_review'
                END AS status
            {$filteredWhere}
            ORDER BY COALESCE(s.updated_on, s.created_on) DESC, s.id DESC
            LIMIT :limit OFFSET :offset";

        $stmt = $this->getDb()->prepare($sql);
        $this->bindCompanySubmissionFilters($stmt, $companyId, $productId, $search);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($data as &$submissionRow) {
            $price = $this->numericAmount($submissionRow['product_price'] ?? null);
            $less = $this->numericAmount($submissionRow['less'] ?? null);
            $submissionRow['product_price'] = $price;
            $submissionRow['less'] = $less;
            $submissionRow['expected_price'] = ($price !== null && $less !== null)
                ? round($price - $less, 2)
                : null;
        }
        unset($submissionRow);

        $productsStmt = $this->getDb()->prepare(
            "SELECT DISTINCT p.id, p.name
            FROM revo_products AS p
            INNER JOIN revo_forms AS f ON f.product_id = p.id AND f.form_type = 1
            INNER JOIN revo_form_submission AS s ON s.form_id = f.id
            WHERE p.company_id = :company_id
            ORDER BY p.name ASC"
        );
        $productsStmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
        $productsStmt->execute();

        return [
            'data' => $data,
            'products' => $productsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'counts' => [
                'submitted' => (int)($summary['submitted'] ?? 0),
                'under_review' => (int)($summary['under_review'] ?? 0),
                'approved' => 0,
                'rejected' => (int)($summary['rejected'] ?? 0),
                'paid' => (int)($summary['paid'] ?? 0),
            ],
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $perPage > 0 ? (int)ceil($total / $perPage) : 0,
            ],
        ];
    }

    private function bindCompanySubmissionFilters(
        \PDOStatement $stmt,
        int $companyId,
        int $productId,
        string $search = ''
    ): void {
        $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
        if ($productId > 0) {
            $stmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        }
        if ($search !== '') {
            $like = '%' . $search . '%';
            $stmt->bindValue(':q_id', $like, PDO::PARAM_STR);
            $stmt->bindValue(':q_member', $like, PDO::PARAM_STR);
            $stmt->bindValue(':q_product', $like, PDO::PARAM_STR);
            $stmt->bindValue(':q_company', $like, PDO::PARAM_STR);
        }
    }

    public function getAllByUser(int $userId): array
    {
        $sql = "SELECT
                    id,
                    form_id,
                    promote_id,
                    submission,
                    order_id,
                    order_amount,
                    reviewer_name,
                    deal_type,
                    `less`,
                    user_id,
                    updated_on,
                    updated_by,
                    created_on,
                    created_by
                FROM {$this->table}
                WHERE user_id = :user_id
                ORDER BY id ASC";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getById(int $id): ?array
    {
        $sql = "SELECT
                    id,
                    form_id,
                    promote_id,
                    submission,
                    order_id,
                    order_amount,
                    reviewer_name,
                    deal_type,
                    `less`,
                    user_id,
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
        $required = [
            'form_id',
            'promote_id',
            'submission',
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

        $now = date('Y-m-d H:i:s');
        $updatedBy = $data['updated_by'] ?? $data['created_by'];
        $fixed = $this->fixedSubmissionColumns((string)$data['submission']);

        $sql = "INSERT INTO {$this->table} (
                    form_id,
                    promote_id,
                    submission,
                    order_id,
                    order_amount,
                    reviewer_name,
                    deal_type,
                    `less`,
                    user_id,
                    updated_on,
                    updated_by,
                    created_on,
                    created_by
                ) VALUES (
                    :form_id,
                    :promote_id,
                    :submission,
                    :order_id,
                    :order_amount,
                    :reviewer_name,
                    :deal_type,
                    :less,
                    :user_id,
                    :updated_on,
                    :updated_by,
                    :created_on,
                    :created_by
                )";

        try {
            $stmt = $this->getDb()->prepare($sql);
            $stmt->bindValue(':form_id', (int)$data['form_id'], PDO::PARAM_INT);
            $stmt->bindValue(':promote_id', (int)$data['promote_id'], PDO::PARAM_INT);
            $stmt->bindValue(':submission', (string)$data['submission'], PDO::PARAM_STR);
            $this->bindNullableString($stmt, ':order_id', $fixed['order_id']);
            $this->bindNullableString($stmt, ':order_amount', $fixed['order_amount']);
            $this->bindNullableString($stmt, ':reviewer_name', $fixed['reviewer_name']);
            $this->bindNullableString($stmt, ':deal_type', $fixed['deal_type']);
            $this->bindNullableString($stmt, ':less', $fixed['less']);
            $stmt->bindValue(':user_id', (int)$data['user_id'], PDO::PARAM_INT);
            $stmt->bindValue(':updated_on', $now, PDO::PARAM_STR);
            $stmt->bindValue(':updated_by', (int)$updatedBy, PDO::PARAM_INT);
            $stmt->bindValue(':created_on', $now, PDO::PARAM_STR);
            $stmt->bindValue(':created_by', (int)$data['created_by'], PDO::PARAM_INT);
            $stmt->execute();

            $submissionId = (int)$this->getDb()->lastInsertId();
            $this->syncSubmissionToGoogleSheet(
                $submissionId,
                (int)$data['form_id'],
                (string)$data['submission'],
                (int)$data['user_id'],
                $now,
                'form'
            );

            return [
                'status' => true,
                'error' => 201,
                'id' => $submissionId,
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

    /**
     * Push a submission row to the product's Google Sheet (never blocks DB create).
     */
    private function syncSubmissionToGoogleSheet(
        int $submissionId,
        int $formId,
        string $submissionJson,
        int $userId,
        string $createdOn,
        string $type
    ): void {
        try {
            $sheets = new GoogleSheetsService();

            $formSql = "SELECT f.id, f.form_name, f.form_json, f.form_type, p.googlesheet_url
                        FROM revo_forms AS f
                        LEFT JOIN revo_products AS p ON p.id = f.product_id
                        WHERE f.id = :id
                        LIMIT 1";
            $formStmt = $this->getDb()->prepare($formSql);
            $formStmt->bindValue(':id', $formId, PDO::PARAM_INT);
            $formStmt->execute();
            $form = $formStmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $sheetUrl = trim((string)($form['googlesheet_url'] ?? ''));
            if ($sheetUrl === '' || !$sheets->isEnabled($sheetUrl)) {
                return;
            }

            $userSql = "SELECT revo_user_name, revo_user_email, revo_user_phone
                        FROM revo_users WHERE revo_user_id = :id LIMIT 1";
            $userStmt = $this->getDb()->prepare($userSql);
            $userStmt->bindValue(':id', $userId, PDO::PARAM_INT);
            $userStmt->execute();
            $user = $userStmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $heads = $sheets->fieldHeadsFromFormJson((string)($form['form_json'] ?? '[]'));
            $submissionData = $this->decodeJsonMap($submissionJson);
            $fieldValues = [];
            foreach ($heads as $head) {
                $fieldValues[$head['label']] = $this->getFieldValue($submissionData, $head['id']);
            }
            // Fallback: include any submission keys not in form_json
            if (empty($heads)) {
                foreach ($submissionData as $fieldId => $fieldData) {
                    $label = is_array($fieldData)
                        ? (string)($fieldData['label'] ?? $fieldId)
                        : (string)$fieldId;
                    $fieldValues[$label !== '' ? $label : (string)$fieldId] = $this->getFieldValue(
                        $submissionData,
                        (string)$fieldId
                    );
                }
            }

            $sheets->appendSubmission([
                'submission_id' => $submissionId,
                'type' => $type,
                'form_id' => $formId,
                'form_name' => (string)($form['form_name'] ?? ''),
                'user_name' => (string)($user['revo_user_name'] ?? ''),
                'user_email' => (string)($user['revo_user_email'] ?? ''),
                'user_phone' => (string)($user['revo_user_phone'] ?? ''),
                'created_on' => $createdOn,
            ], $fieldValues, $sheetUrl);
        } catch (\Throwable $e) {
            // Keep form submit successful even if Sheets fails.
        }
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [':id' => $id];

        $updatable = [
            'form_id',
            'promote_id',
            'submission',
            'updated_by',
        ];

        foreach ($updatable as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }

        if (array_key_exists('submission', $data)) {
            $fixed = $this->fixedSubmissionColumns((string)$data['submission']);
            foreach ($fixed as $column => $value) {
                $fields[] = "`{$column}` = :fixed_{$column}";
                $params[":fixed_{$column}"] = $value;
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

    public function getAllByUserSubmissions(int $userId, int $promote_id, int $form_type = 1): array
    {
        return $this->getAllByUserSubmissionsPaginated($userId, $promote_id, $form_type, 1, 9999)['data'];
    }

    public function getAllByUserSubmissionsPaginated(
        int $userId,
        int $promote_id,
        int $form_type = 1,
        int $page = 1,
        int $perPage = Pagination_Default_Subsmission_Per_Page
    ): array {
        $page = max(1, $page);
        $perPage = max($this->paginationPerPage, $perPage);
        $offset = ($page - 1) * $perPage;

        $fromWhere = "FROM {$this->table} AS s
                INNER JOIN revo_forms AS f ON s.form_id = f.id
                WHERE s.promote_id = :promote_id
                AND s.user_id = :user_id
                AND f.form_type = :form_type";

        $countSql = "SELECT COUNT(*) AS total {$fromWhere}";
        $countStmt = $this->getDb()->prepare($countSql);
        $countStmt->bindValue(':promote_id', $promote_id, PDO::PARAM_INT);
        $countStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $countStmt->bindValue(':form_type', $form_type, PDO::PARAM_INT);
        $countStmt->execute();
        $total = (int)($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $sql = "SELECT
                    s.id,
                    s.form_id,
                    s.promote_id,
                    s.submission,
                    s.user_id,
                    s.updated_on,
                    s.updated_by,
                    s.created_on,
                    s.created_by,
                    u.revo_user_name,
                    u.revo_user_email,
                    u.revo_user_phone,
                    
                    (SELECT COUNT(*) FROM revo_refund_submission WHERE submission_id = s.id) as refund_submission_count,
                    COALESCE((SELECT form_json FROM revo_forms WHERE id = s.form_id AND status = 1 AND form_type = 1 LIMIT 1), '[]') as form_heads,
                    COALESCE((SELECT form_json FROM revo_forms WHERE product_id = (SELECT product_id FROM revo_forms WHERE id = s.form_id LIMIT 1) AND status = 1 AND form_type = 2 LIMIT 1), '[]') as form_submission_heads,
                    COALESCE(rs.submission_text, '') as refund_submission_text,
                    COALESCE(rs.accept_status, 0) as refund_accept_status,
                    COALESCE(rs.accept_reject_remark, '') as refund_accept_reject_remark,
                    COALESCE(rs.accept_reject_screenshot, '') as refund_accept_reject_screenshot
                    
                FROM {$this->table} AS s
                INNER JOIN revo_users AS u ON s.user_id = u.revo_user_id
                INNER JOIN revo_forms AS f ON s.form_id = f.id
                LEFT JOIN revo_refund_submission AS rs ON s.id = rs.submission_id
                WHERE s.promote_id = :promote_id
                AND s.user_id = :user_id
                AND f.form_type = :form_type
                ORDER BY s.id ASC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':promote_id', $promote_id, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':form_type', $form_type, PDO::PARAM_INT);
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

    public function delete(int $id): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function getByFormIdAndUserId(int $formId, int $userId): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE form_id = :form_id AND user_id = :user_id";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':form_id', $formId, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }


    public function getExcel(int $formId): array
    {
        $formSql = "SELECT id, product_id, form_json
                FROM revo_forms
                WHERE id = :form_id
                LIMIT 1";
        $formStmt = $this->getDb()->prepare($formSql);
        $formStmt->bindValue(':form_id', $formId, PDO::PARAM_INT);
        $formStmt->execute();
        $formRow = $formStmt->fetch(PDO::FETCH_ASSOC);
        if (!$formRow) {
            return [
                'status' => false,
                'message' => 'Form not found',
            ];
        }

        $skipTypes = ['heading', 'divider', 'paragraph', 'html', 'section'];
        $fieldHeads = $this->buildFieldHeadsFromFormJson((string)($formRow['form_json'] ?? '[]'), $skipTypes);

        // Get all refund heads from refund form (form_type = 2) for same product
        $refundFieldHeads = [];
        $productId = (int)($formRow['product_id'] ?? 0);
        $refundForm = null;
        if ($productId > 0) {
            $formModel = new RevoForm($this->getDb());
            $refundForm = $formModel->getByProductIdAndFormType($productId, 2);
            if ($refundForm && !empty($refundForm['form_json'])) {
                $refundFieldHeads = $this->buildFieldHeadsFromFormJson(
                    (string)$refundForm['form_json'],
                    $skipTypes
                );
            }
        }

        $sql = "SELECT
                    s.id,
                    s.form_id,
                    s.promote_id,
                    s.submission,
                    s.user_id,
                    s.created_on,
                    s.updated_on,
                    u.revo_user_name,
                    u.revo_user_email,
                    u.revo_user_phone
                FROM {$this->table} AS s
                LEFT JOIN revo_users AS u ON u.revo_user_id = s.user_id
                WHERE s.form_id = :form_id
                ORDER BY s.id ASC";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':form_id', $formId, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (empty($fieldHeads)) {
            $fieldHeads = $this->discoverFieldHeads($rows, 'submission');
        }

        // Fetch refunds by submission_id
        $refundBySubmissionId = [];
        $submissionIds = [];
        foreach ($rows as $row) {
            $sid = (int)($row['id'] ?? 0);
            if ($sid > 0) {
                $submissionIds[] = $sid;
            }
        }

        if (!empty($submissionIds)) {
            $placeholders = [];
            foreach ($submissionIds as $index => $submissionId) {
                $placeholders[] = ":sid_{$index}";
            }
            $inClause = implode(',', $placeholders);

            $refundSql = "SELECT
                        id,
                        submission_id,
                        submission_text,
                        accept_status,
                        accept_reject_remark,
                        accept_reject_screenshot,
                        created_on
                    FROM revo_refund_submission
                    WHERE submission_id IN ({$inClause})
                    ORDER BY id ASC";
            $refundStmt = $this->getDb()->prepare($refundSql);
            foreach ($submissionIds as $index => $submissionId) {
                $refundStmt->bindValue(":sid_{$index}", $submissionId, PDO::PARAM_INT);
            }
            $refundStmt->execute();
            foreach ($refundStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $refundRow) {
                $refundBySubmissionId[(int)$refundRow['submission_id']] = $refundRow;
            }
        }

        $dir = defined('EXCEL_UPLOAD_DIR') ? EXCEL_UPLOAD_DIR : (rtrim(UPLOAD_DIR, '/') . '/excels/');
        $dir = rtrim($dir, '/');
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return [
                'status' => false,
                'message' => 'Failed to create excel upload directory',
            ];
        }

        $filename = 'form_' . $formId . '_submissions_' . date('Ymd_His') . '.csv';
        $filepath = $dir . '/' . $filename;

        $handle = fopen($filepath, 'w');
        if ($handle === false) {
            return [
                'status' => false,
                'message' => 'Failed to create CSV file',
            ];
        }

        // UTF-8 BOM so Excel opens CSV as a table correctly
        fwrite($handle, "\xEF\xBB\xBF");

        $metaHeaders = ['Submission ID', 'User Name', 'User Email', 'User Phone', 'Created On', 'Bank Name', 'Bank Account No', 'Bank Holder Name', 'Bank IFSC', 'UPI', 'UPI Display Name'];
        $submissionHeaders = array_map(static fn(array $h) => $h['label'], $fieldHeads);
        $refundMetaHeaders = ['Refund Status', 'Refund Remark', 'Refund Screenshot', 'Refund Created On'];
        $refundValueHeaders = array_map(static fn(array $h) => 'Refund: ' . $h['label'], $refundFieldHeads);
        $headers = array_merge($metaHeaders, $submissionHeaders, $refundMetaHeaders, $refundValueHeaders);
        fputcsv($handle, $headers);

        foreach ($rows as $row) {
            $submissionId = (int)($row['id'] ?? 0);
            $submissionData = $this->decodeJsonMap((string)($row['submission'] ?? ''));
            $refund = $refundBySubmissionId[$submissionId] ?? null;
            $hasRefund = is_array($refund);
            $refundData = $hasRefund
                ? $this->decodeJsonMap((string)($refund['submission_text'] ?? ''))
                : [];

            $csvRow = [
                $submissionId,
                $row['revo_user_name'] ?? '',
                $row['revo_user_email'] ?? '',
                $row['revo_user_phone'] ?? '',
                $row['created_on'] ?? '',
            ];

            //print_r($row);
            //GET BANK AND UP DETAILS
            $bankModel = new RevoUserBank($this->getDb());
            $bank = $bankModel->getByUserId($row['user_id']);
            if($bank){
                $csvRow[] = $bank['revo_user_bank_name'];
                $csvRow[] = $bank['revo_back_account_no'];
                $csvRow[] = $bank['revo_bank_holder_name'];
                $csvRow[] = $bank['revo_bank_ifsc'];
                $csvRow[] = $bank['revo_user_upi'];
                $csvRow[] = $bank['revo_upi_display_name'];
            }else{
                $csvRow[] = '';
                $csvRow[] = '';
                $csvRow[] = '';
                $csvRow[] = '';
                $csvRow[] = '';
                $csvRow[] = '';
            }

            foreach ($fieldHeads as $fieldHead) {
                $csvRow[] = $this->getFieldValue($submissionData, $fieldHead['id']);
            }

            $screenshot = (string)($refund['accept_reject_screenshot'] ?? '');
            $screenshotUrl = '';
            if ($screenshot !== '') {
                if (preg_match('#^https?://#i', $screenshot)) {
                    $screenshotUrl = $screenshot;
                } else {
                    $screenshotUrl = rtrim(UPLOAD_URL, '/') . '/refunds/' . ltrim($screenshot, '/');
                }
            }

            $csvRow[] = $this->formatRefundStatus($hasRefund ? (int)$refund['accept_status'] : null);
            $csvRow[] = (string)($refund['accept_reject_remark'] ?? '');
            $csvRow[] = $screenshotUrl;
            $csvRow[] = (string)($refund['created_on'] ?? '');

            foreach ($refundFieldHeads as $fieldHead) {
                $csvRow[] = $this->getFieldValue($refundData, $fieldHead['id']);
            }

            fputcsv($handle, $csvRow);
        }
        fclose($handle);

        return [
            'status' => true,
            'filename' => $filename,
            'path' => $filepath,
            'url' => rtrim(UPLOAD_URL, '/') . '/excels/' . $filename
            // 'count' => count($rows),
            // 'headers' => $headers,
            // 'refund_form_id' => $refundForm['id'] ?? null,
        ];
    }

    private function buildFieldHeadsFromFormJson(string $formJson, array $skipTypes): array
    {
        $formFields = json_decode($formJson, true);
        if (!is_array($formFields)) {
            return [];
        }

        $fieldHeads = [];
        foreach ($formFields as $field) {
            if (!is_array($field) || empty($field['id'])) {
                continue;
            }
            $type = strtolower((string)($field['type'] ?? ''));
            if (in_array($type, $skipTypes, true)) {
                continue;
            }
            $label = trim((string)($field['label'] ?? ''));
            if ($label === '') {
                $label = (string)$field['id'];
            }
            $fieldHeads[] = [
                'id' => (string)$field['id'],
                'label' => $label,
            ];
        }
        return $fieldHeads;
    }

    private function discoverFieldHeads(array $rows, string $column): array
    {
        $seen = [];
        $fieldHeads = [];
        foreach ($rows as $row) {
            $decoded = $this->decodeJsonMap((string)($row[$column] ?? ''));
            foreach ($decoded as $fieldId => $fieldData) {
                if (isset($seen[$fieldId])) {
                    continue;
                }
                $label = is_array($fieldData)
                    ? (string)($fieldData['label'] ?? $fieldId)
                    : (string)$fieldId;
                $seen[$fieldId] = true;
                $fieldHeads[] = [
                    'id' => (string)$fieldId,
                    'label' => $label !== '' ? $label : (string)$fieldId,
                ];
            }
        }
        return $fieldHeads;
    }

    /**
     * Columns for the mandatory product-form fields, pulled out of submission JSON.
     *
     * @return array{order_id:?string,order_amount:?string,reviewer_name:?string,deal_type:?string,less:?string}
     */
    private function fixedSubmissionColumns(string $submissionJson): array
    {
        $data = $this->decodeJsonMap($submissionJson);

        return [
            'order_id' => $this->matchFixedValue($data, ['fixed_order_id'], static function (string $label): bool {
                return str_contains($label, 'order id');
            }),
            'order_amount' => $this->matchFixedValue($data, ['fixed_order_amount'], static function (string $label): bool {
                return str_contains($label, 'order amount') || str_contains($label, 'order amt');
            }),
            'reviewer_name' => $this->matchFixedValue($data, ['fixed_reviewer_name'], static function (string $label): bool {
                return str_contains($label, 'reviewer name');
            }),
            'deal_type' => $this->matchFixedValue($data, ['fixed_deal_type'], static function (string $label): bool {
                return str_contains($label, 'deal type');
            }),
            'less' => $this->matchFixedValue($data, ['fixed_less'], static function (string $label): bool {
                return $label === 'less';
            }),
        ];
    }

    private function matchFixedValue(array $data, array $ids, callable $labelMatch): ?string
    {
        foreach ($ids as $id) {
            if (!array_key_exists($id, $data)) {
                continue;
            }
            $value = trim($this->getFieldValue($data, $id));
            return $value === '' ? null : $value;
        }

        foreach ($data as $fieldId => $fieldData) {
            $label = is_array($fieldData) ? (string)($fieldData['label'] ?? '') : '';
            $normalized = strtolower(trim((string)preg_replace('/[_-]+/', ' ', $label)));
            $normalized = trim((string)preg_replace('/\s+/', ' ', $normalized));
            if ($normalized === '' || !$labelMatch($normalized)) {
                continue;
            }
            $value = trim($this->getFieldValue($data, (string)$fieldId));
            return $value === '' ? null : $value;
        }

        return null;
    }

    private function bindNullableString(\PDOStatement $stmt, string $name, ?string $value): void
    {
        if ($value === null || $value === '') {
            $stmt->bindValue($name, null, PDO::PARAM_NULL);
            return;
        }
        $stmt->bindValue($name, $value, PDO::PARAM_STR);
    }

    private function numericAmount(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        $clean = preg_replace('/[^0-9.\-]/', '', (string)$value);
        if ($clean === '' || !is_numeric($clean)) {
            return null;
        }
        return (float)$clean;
    }

    private function decodeJsonMap(string $json): array
    {
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function getFieldValue(array $data, string $fieldId): string
    {
        if (!array_key_exists($fieldId, $data)) {
            return '';
        }

        $fieldValue = $data[$fieldId];
        if (is_array($fieldValue)) {
            $value = $fieldValue['value'] ?? '';
            if (is_array($value)) {
                return implode(', ', array_map('strval', $value));
            }
            return is_scalar($value) || $value === null ? (string)$value : json_encode($value);
        }

        return is_scalar($fieldValue) || $fieldValue === null ? (string)$fieldValue : json_encode($fieldValue);
    }

    private function formatRefundStatus(?int $status): string
    {
        if ($status === null) {
            return 'No Refund';
        }

        return match ($status) {
            1 => 'Accepted',
            2 => 'Rejected',
            default => 'Pending',
        };
    }
}
