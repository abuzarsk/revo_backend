<?php

namespace App\Models;

use App\Core\Model;
use App\Services\GoogleSheetsService;
use PDO;
use PDOException;

class RevoRefundSubmission extends Model
{
    private string $table = 'revo_refund_submission';
    private int $paginationPerPage = Pagination_Default_Subsmission_Per_Page;

    private function getBaseSelectColumns(string $alias = ''): string
    {
        $p = $alias !== '' ? "{$alias}." : '';

        return "{$p}id,
                    {$p}submission_id,
                    {$p}submission_text,
                    {$p}user_id,
                    {$p}accept_status,
                    {$p}accept_reject_remark,
                    {$p}accept_reject_screenshot,
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

    public function getAllBySubmissionId(int $submissionId): array
    {
        $sql = "SELECT
                    {$this->getBaseSelectColumns()}
                FROM {$this->table}
                WHERE submission_id = :submission_id
                ORDER BY id ASC";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':submission_id', $submissionId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getAllBySubmissionIdForCompanyAndMediator(int $submissionId): array
    {
        return $this->getAllBySubmissionIdForCompanyAndMediatorPaginated($submissionId, 1, 9999)['data'];
    }

    public function getAllBySubmissionIdForCompanyAndMediatorPaginated(
        int $submissionId,
        int $page = 1,
        int $perPage = Pagination_Default_Subsmission_Per_Page
    ): array {
        $page = max(1, $page);
        $perPage = max($this->paginationPerPage, $perPage);
        $offset = ($page - 1) * $perPage;

        $fromWhere = "FROM {$this->table} AS r
                INNER JOIN revo_form_submission AS s ON r.submission_id = s.id
                WHERE r.submission_id = :submission_id";

        $countSql = "SELECT COUNT(*) AS total {$fromWhere}";
        $countStmt = $this->getDb()->prepare($countSql);
        $countStmt->bindValue(':submission_id', $submissionId, PDO::PARAM_INT);
        $countStmt->execute();
        $total = (int)($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $sql = "SELECT
                    r.id,
                    r.submission_id,
                    r.submission_text as submission,
                    r.user_id,
                    r.accept_status,
                    r.accept_reject_remark,
                    r.accept_reject_screenshot,
                    r.updated_on,
                    r.updated_by,
                    r.created_on,
                    r.created_by,
                    u.revo_user_name,
                    u.revo_user_email,
                    u.revo_user_phone
                FROM {$this->table} AS r
                INNER JOIN revo_form_submission AS s ON r.submission_id = s.id
                INNER JOIN revo_users AS u ON r.user_id = u.revo_user_id
                WHERE r.submission_id = :submission_id
                ORDER BY r.id ASC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':submission_id', $submissionId, PDO::PARAM_INT);
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

    public function getAllBySubmissionIdForUser(int $submissionId, int $userId): array
    {
        return $this->getAllBySubmissionIdForUserPaginated($submissionId, $userId, 1, 9999)['data'];
    }

    public function getAllBySubmissionIdForUserPaginated(
        int $submissionId,
        int $userId,
        int $page = 1,
        int $perPage = Pagination_Default_Subsmission_Per_Page
    ): array {
        $page = max(1, $page);
        $perPage = max($this->paginationPerPage, $perPage);
        $offset = ($page - 1) * $perPage;

        $fromWhere = "FROM {$this->table} AS r
                INNER JOIN revo_form_submission AS s ON r.submission_id = s.id
                WHERE r.submission_id = :submission_id
                AND r.user_id = :user_id
                AND s.user_id = :user_id";

        $countSql = "SELECT COUNT(*) AS total {$fromWhere}";
        $countStmt = $this->getDb()->prepare($countSql);
        $countStmt->bindValue(':submission_id', $submissionId, PDO::PARAM_INT);
        $countStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $countStmt->execute();
        $total = (int)($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $sql = "SELECT
                    r.id,
                    r.submission_id,
                    r.submission_text as submission,
                    r.user_id,
                    r.accept_status,
                    r.accept_reject_remark,
                    r.accept_reject_screenshot,
                    r.updated_on,
                    r.updated_by,
                    r.created_on,
                    r.created_by
                {$fromWhere}
                ORDER BY r.id ASC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':submission_id', $submissionId, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
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

    public function getAllByUser(int $userId): array
    {
        $sql = "SELECT
                    {$this->getBaseSelectColumns()}
                FROM {$this->table}
                WHERE user_id = :user_id
                ORDER BY id DESC";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function create(array $data): array
    {
        $required = [
            'submission_id',
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

        $sql = "INSERT INTO {$this->table} (
                    submission_id,
                    submission_text,
                    user_id,
                    accept_status,
                    accept_reject_remark,
                    accept_reject_screenshot,
                    updated_on,
                    updated_by,
                    created_on,
                    created_by
                ) VALUES (
                    :submission_id,
                    :submission_text,
                    :user_id,
                    :accept_status,
                    :accept_reject_remark,
                    :accept_reject_screenshot,
                    :updated_on,
                    :updated_by,
                    :created_on,
                    :created_by
                )";

        try {
            $stmt = $this->getDb()->prepare($sql);
            $stmt->bindValue(':submission_id', (int)$data['submission_id'], PDO::PARAM_INT);
            $stmt->bindValue(':submission_text', (string)$data['submission'], PDO::PARAM_STR);
            $stmt->bindValue(':user_id', (int)$data['user_id'], PDO::PARAM_INT);
            $stmt->bindValue(':accept_status', isset($data['accept_status']) ? (int)$data['accept_status'] : 0, PDO::PARAM_INT);
            $stmt->bindValue(':accept_reject_remark', isset($data['accept_reject_remark']) ? (string)$data['accept_reject_remark'] : '', PDO::PARAM_STR);
            $stmt->bindValue(':accept_reject_screenshot', isset($data['accept_reject_screenshot']) ? (string)$data['accept_reject_screenshot'] : '', PDO::PARAM_STR);
            $stmt->bindValue(':updated_on', $now, PDO::PARAM_STR);
            $stmt->bindValue(':updated_by', (int)$updatedBy, PDO::PARAM_INT);
            $stmt->bindValue(':created_on', $now, PDO::PARAM_STR);
            $stmt->bindValue(':created_by', (int)$data['created_by'], PDO::PARAM_INT);
            $stmt->execute();

            $refundId = (int)$this->getDb()->lastInsertId();
            $this->syncRefundToGoogleSheet(
                $refundId,
                (int)$data['submission_id'],
                (string)$data['submission'],
                (int)$data['user_id'],
                $now
            );

            return [
                'status' => true,
                'error' => 201,
                'id' => $refundId,
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

    private function syncRefundToGoogleSheet(
        int $refundId,
        int $parentSubmissionId,
        string $submissionJson,
        int $userId,
        string $createdOn
    ): void {
        try {
            $sheets = new GoogleSheetsService();

            $parentSql = "SELECT s.form_id, f.product_id, p.googlesheet_url
                          FROM revo_form_submission AS s
                          LEFT JOIN revo_forms AS f ON f.id = s.form_id
                          LEFT JOIN revo_products AS p ON p.id = f.product_id
                          WHERE s.id = :id
                          LIMIT 1";
            $parentStmt = $this->getDb()->prepare($parentSql);
            $parentStmt->bindValue(':id', $parentSubmissionId, PDO::PARAM_INT);
            $parentStmt->execute();
            $parent = $parentStmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $sheetUrl = trim((string)($parent['googlesheet_url'] ?? ''));
            if ($sheetUrl === '' || !$sheets->isEnabled($sheetUrl)) {
                return;
            }

            $productId = (int)($parent['product_id'] ?? 0);
            $form = null;
            if ($productId > 0) {
                $formSql = "SELECT id, form_name, form_json FROM revo_forms
                            WHERE product_id = :product_id AND form_type = 2
                            LIMIT 1";
                $formStmt = $this->getDb()->prepare($formSql);
                $formStmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
                $formStmt->execute();
                $form = $formStmt->fetch(PDO::FETCH_ASSOC) ?: null;
            }

            $heads = $sheets->fieldHeadsFromFormJson((string)($form['form_json'] ?? '[]'));
            $submissionData = json_decode($submissionJson, true);
            if (!is_array($submissionData)) {
                $submissionData = [];
            }

            $fieldValues = [];
            foreach ($heads as $head) {
                $fieldId = $head['id'];
                $fieldValue = $submissionData[$fieldId] ?? null;
                $fieldValues[$head['label']] = $sheets->valueFromSubmissionField($fieldValue);
            }
            if (empty($heads)) {
                foreach ($submissionData as $fieldId => $fieldData) {
                    $label = is_array($fieldData)
                        ? (string)($fieldData['label'] ?? $fieldId)
                        : (string)$fieldId;
                    $fieldValues[$label !== '' ? $label : (string)$fieldId] = $sheets->valueFromSubmissionField($fieldData);
                }
            }

            $encodedFormId = $sheets->encodeFormId($parentSubmissionId);
            $sheets->updateRefundByEncodedFormId(
                $encodedFormId,
                $fieldValues,
                [
                    'refund_status' => 'Pending',
                    'refund_created_on' => $createdOn,
                ],
                $sheetUrl
            );
        } catch (\Throwable $e) {
            // Keep refund submit successful even if Sheets fails.
        }
    }

    public function update(int $id, array $data): bool
    {
        if (array_key_exists('submission', $data) && !array_key_exists('submission_text', $data)) {
            $data['submission_text'] = $data['submission'];
        }

        $fields = [];
        $params = [':id' => $id];

        $updatable = [
            'submission_id',
            'submission_text',
            'accept_status',
            'accept_reject_remark',
            'accept_reject_screenshot',
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

        $sql = 'UPDATE ' . $this->table . ' SET ' . implode(', ', $fields) . ' WHERE submission_id = :id';
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

    /**
     * All refund submissions for one company.
     * expected_price = product form order amount − product form less.
     */
    public function getCompanyRefundsPaginated(
        int $companyId,
        int $page = 1,
        int $perPage = 10,
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

        $allowedStatus = ['all', 'under_review', 'paid', 'rejected'];
        if (!in_array($status, $allowedStatus, true)) {
            $status = 'all';
        }

        $fromWhere = "FROM {$this->table} AS rs
            INNER JOIN revo_form_submission AS s ON s.id = rs.submission_id
            INNER JOIN revo_forms AS f ON f.id = s.form_id AND f.form_type = 1
            INNER JOIN revo_products AS p ON p.id = f.product_id
            LEFT JOIN revo_brands AS b ON b.id = p.brand_id
            INNER JOIN revo_company AS c ON c.id = p.company_id
            INNER JOIN revo_users AS u ON u.revo_user_id = s.user_id
            WHERE p.company_id = :company_id";

        $summaryStmt = $this->getDb()->prepare(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN COALESCE(rs.accept_status, 0) = 0 THEN 1 ELSE 0 END) AS under_review,
                SUM(CASE WHEN COALESCE(rs.accept_status, 0) = 1 THEN 1 ELSE 0 END) AS paid,
                SUM(CASE WHEN COALESCE(rs.accept_status, 0) = 2 THEN 1 ELSE 0 END) AS rejected
            {$fromWhere}"
        );
        $summaryStmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
        $summaryStmt->execute();
        $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $statusClause = match ($status) {
            'under_review' => ' AND COALESCE(rs.accept_status, 0) = 0',
            'paid' => ' AND COALESCE(rs.accept_status, 0) = 1',
            'rejected' => ' AND COALESCE(rs.accept_status, 0) = 2',
            default => '',
        };
        $searchClause = $search !== ''
            ? ' AND (CAST(rs.id AS CHAR) LIKE :q_id OR CAST(s.id AS CHAR) LIKE :q_submission OR u.revo_user_name LIKE :q_member OR p.name LIKE :q_product OR s.order_id LIKE :q_order)'
            : '';

        $filteredWhere = $fromWhere . $statusClause . $searchClause;
        $countStmt = $this->getDb()->prepare("SELECT COUNT(*) AS total {$filteredWhere}");
        $countStmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
        if ($search !== '') {
            $like = '%' . $search . '%';
            $countStmt->bindValue(':q_id', $like, PDO::PARAM_STR);
            $countStmt->bindValue(':q_submission', $like, PDO::PARAM_STR);
            $countStmt->bindValue(':q_member', $like, PDO::PARAM_STR);
            $countStmt->bindValue(':q_product', $like, PDO::PARAM_STR);
            $countStmt->bindValue(':q_order', $like, PDO::PARAM_STR);
        }
        $countStmt->execute();
        $total = (int)($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $sql = "SELECT
                rs.id,
                rs.submission_id,
                rs.submission_text AS refund_submission,
                rs.accept_status,
                COALESCE(rs.accept_reject_remark, '') AS remark,
                COALESCE(rs.accept_reject_screenshot, '') AS screenshot,
                rs.created_on,
                rs.updated_on,
                s.order_id,
                s.order_amount,
                s.`less` AS product_less,
                s.submission,
                p.id AS product_id,
                p.name AS product_name,
                COALESCE(b.brand_name, '') AS brand_name,
                c.company_name,
                u.revo_user_name,
                CASE
                    WHEN COALESCE(rs.accept_status, 0) = 1 THEN 'paid'
                    WHEN COALESCE(rs.accept_status, 0) = 2 THEN 'rejected'
                    ELSE 'under_review'
                END AS status
            {$filteredWhere}
            ORDER BY COALESCE(rs.updated_on, rs.created_on) DESC, rs.id DESC
            LIMIT :limit OFFSET :offset";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
        if ($search !== '') {
            $like = '%' . $search . '%';
            $stmt->bindValue(':q_id', $like, PDO::PARAM_STR);
            $stmt->bindValue(':q_submission', $like, PDO::PARAM_STR);
            $stmt->bindValue(':q_member', $like, PDO::PARAM_STR);
            $stmt->bindValue(':q_product', $like, PDO::PARAM_STR);
            $stmt->bindValue(':q_order', $like, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $data = array_map(fn (array $row): array => $this->withExpectedPrice($row), $rows);

        return [
            'data' => $data,
            'counts' => [
                'under_review' => (int)($summary['under_review'] ?? 0),
                'paid' => (int)($summary['paid'] ?? 0),
                'rejected' => (int)($summary['rejected'] ?? 0),
            ],
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $perPage > 0 ? (int)ceil($total / $perPage) : 0,
            ],
        ];
    }

    private function withExpectedPrice(array $row): array
    {
        $submission = $this->decodeJsonMap((string)($row['submission'] ?? ''));
        $refund = $this->decodeJsonMap((string)($row['refund_submission'] ?? ''));

        $price = $this->numericAmount($row['order_amount'] ?? null);
        if ($price === null) {
            $price = $this->numericAmount($this->matchAnswer($submission, ['fixed_order_amount'], static function (string $label): bool {
                return str_contains($label, 'order amount') || str_contains($label, 'order amt');
            }));
        }

        $less = $this->numericAmount($row['product_less'] ?? null);
        if ($less === null) {
            $less = $this->numericAmount($this->matchAnswer($submission, ['fixed_less'], static function (string $label): bool {
                return $label === 'less';
            }));
        }

        $row['product_price'] = $price;
        $row['less'] = $less;
        $row['expected_price'] = ($price !== null && $less !== null) ? round($price - $less, 2) : null;
        $row['review_link'] = $this->matchAnswer($refund, [], static function (string $label): bool {
            return str_contains($label, 'review link');
        });
        unset($row['submission'], $row['order_amount'], $row['product_less']);

        return $row;
    }

    private function decodeJsonMap(string $json): array
    {
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function matchAnswer(array $data, array $ids, callable $labelMatch): ?string
    {
        foreach ($ids as $id) {
            if (!array_key_exists($id, $data)) {
                continue;
            }
            $value = trim($this->answerText($data[$id]));
            return $value === '' ? null : $value;
        }

        foreach ($data as $fieldData) {
            $label = is_array($fieldData) ? (string)($fieldData['label'] ?? '') : '';
            $normalized = strtolower(trim((string)preg_replace('/[_-]+/', ' ', $label)));
            $normalized = trim((string)preg_replace('/\s+/', ' ', $normalized));
            if ($normalized === '' || !$labelMatch($normalized)) {
                continue;
            }
            $value = trim($this->answerText($fieldData));
            return $value === '' ? null : $value;
        }

        return null;
    }

    private function answerText(mixed $field): string
    {
        if (is_array($field)) {
            $value = $field['value'] ?? '';
            if (is_array($value)) {
                return implode(', ', array_map('strval', $value));
            }
            return is_scalar($value) || $value === null ? (string)$value : '';
        }

        return is_scalar($field) || $field === null ? (string)$field : '';
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

    public function getSubmissionCountBySubmissionId(int $submissionId): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE submission_id = :submission_id";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':submission_id', $submissionId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn();
    }
}
