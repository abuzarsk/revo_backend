<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class RevoOrderHistory extends Model
{
    private string $submissionTable = 'revo_form_submission';

    public function getByUserIdPaginated(
        int $userId,
        int $page = 1,
        int $perPage = Pagination_Default_Subsmission_Per_Page
    ): array {
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;

        $fromWhere = "FROM {$this->submissionTable} AS s
                INNER JOIN revo_forms AS f ON s.form_id = f.id AND f.form_type = 1
                INNER JOIN revo_products AS p ON f.product_id = p.id
                INNER JOIN revo_brands AS b ON p.brand_id = b.id
                LEFT JOIN revo_products_promote AS pp ON pp.id = s.promote_id
                LEFT JOIN revo_refund_submission AS rs ON rs.submission_id = s.id
                WHERE s.user_id = :user_id";

        $countSql = "SELECT COUNT(*) AS total {$fromWhere}";
        $countStmt = $this->getDb()->prepare($countSql);
        $countStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $countStmt->execute();
        $total = (int)($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $sql = "SELECT
                    s.id AS submission_id,
                    s.form_id,
                    s.promote_id,
                    s.submission,
                    s.user_id,
                    s.created_on AS submission_created_on,
                    s.updated_on AS submission_updated_on,
                    p.id AS product_id,
                    p.name AS product_name,
                    p.photo AS product_photo,
                    p.status AS product_status,
                    p.order_limit AS product_order_limit,
                    b.id AS brand_id,
                    b.brand_name,
                    f.form_name,
                    pp.status AS promote_status,
                    pp.common_id AS promote_common_id,
                    rs.id AS refund_id,
                    rs.submission_text AS refund_submission_text,
                    rs.accept_status AS refund_accept_status,
                    rs.accept_reject_remark AS refund_remark,
                    rs.accept_reject_screenshot AS refund_image,
                    rs.created_on AS refund_created_on,
                    rs.updated_on AS refund_updated_on
                {$fromWhere}
                ORDER BY s.id DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $data = array_map([$this, 'formatOrderHistoryRow'], $rows);

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

    public function getBySubmissionIdForUser(int $submissionId, int $userId): ?array
    {
        $sql = "SELECT
                    s.id AS submission_id,
                    s.form_id,
                    s.promote_id,
                    s.submission,
                    s.user_id,
                    s.created_on AS submission_created_on,
                    s.updated_on AS submission_updated_on,
                    p.id AS product_id,
                    p.name AS product_name,
                    p.photo AS product_photo,
                    p.status AS product_status,
                    p.order_limit AS product_order_limit,
                    b.id AS brand_id,
                    b.brand_name,
                    f.form_name,
                    pp.status AS promote_status,
                    pp.common_id AS promote_common_id,
                    rs.id AS refund_id,
                    rs.submission_text AS refund_submission_text,
                    rs.accept_status AS refund_accept_status,
                    rs.accept_reject_remark AS refund_remark,
                    rs.accept_reject_screenshot AS refund_image,
                    rs.created_on AS refund_created_on,
                    rs.updated_on AS refund_updated_on
                FROM {$this->submissionTable} AS s
                INNER JOIN revo_forms AS f ON s.form_id = f.id AND f.form_type = 1
                INNER JOIN revo_products AS p ON f.product_id = p.id
                INNER JOIN revo_brands AS b ON p.brand_id = b.id
                LEFT JOIN revo_products_promote AS pp ON pp.id = s.promote_id
                LEFT JOIN revo_refund_submission AS rs ON rs.submission_id = s.id
                WHERE s.id = :submission_id AND s.user_id = :user_id
                LIMIT 1";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':submission_id', $submissionId, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $this->formatOrderHistoryRow($row) : null;
    }

    private function formatOrderHistoryRow(array $row): array
    {
        $hasRefund = !empty($row['refund_id']);
        $refundAcceptStatus = $hasRefund ? (int)$row['refund_accept_status'] : null;
        $status = $this->resolveOrderStatus($hasRefund, $refundAcceptStatus);

        $productPhoto = (string)($row['product_photo'] ?? '');
        $refundImage = (string)($row['refund_image'] ?? '');

        $formatted = [
            'submission_id' => (int)$row['submission_id'],
            'status' => $status['label'],
            'status_code' => $status['code'],
            'product' => [
                'id' => (int)$row['product_id'],
                'name' => $row['product_name'],
                'photo' => $productPhoto,
                'photo_url' => $productPhoto !== '' ? PRODUCT_UPLOAD_URL . $productPhoto : '',
                'status' => (int)$row['product_status'],
                'order_limit' => (int)$row['product_order_limit'],
                'brand_id' => (int)$row['brand_id'],
                'brand_name' => $row['brand_name'],
            ],
            'form_submission' => [
                'id' => (int)$row['submission_id'],
                'form_id' => (int)$row['form_id'],
                'promote_id' => (int)$row['promote_id'],
                'form_name' => $row['form_name'],
                'submission' => $row['submission'],
                'promote_status' => isset($row['promote_status']) ? (int)$row['promote_status'] : 0,
                'promote_common_id' => isset($row['promote_common_id']) ? (int)$row['promote_common_id'] : 0,
                'created_on' => $row['submission_created_on'],
                'updated_on' => $row['submission_updated_on'],
            ],
            'refund_submission' => null,
            'refund_remark' => '',
            'refund_image' => '',
            'refund_image_url' => '',
        ];

        if ($hasRefund) {
            $formatted['refund_submission'] = [
                'id' => (int)$row['refund_id'],
                'submission_id' => (int)$row['submission_id'],
                'submission_text' => $row['refund_submission_text'],
                'accept_status' => $refundAcceptStatus,
                'accept_reject_remark' => $row['refund_remark'],
                'accept_reject_screenshot' => $row['refund_image'],
                'created_on' => $row['refund_created_on'],
                'updated_on' => $row['refund_updated_on'],
            ];
            $formatted['refund_remark'] = (string)($row['refund_remark'] ?? '');
            $formatted['refund_image'] = $refundImage;
            $formatted['refund_image_url'] = $refundImage !== '' ? UPLOAD_URL . 'refunds/' . $refundImage : '';
        }

        return $formatted;
    }

    private function resolveOrderStatus(bool $hasRefund, ?int $refundAcceptStatus): array
    {
        if (!$hasRefund) {
            return ['label' => 'submitted', 'code' => 0];
        }

        return match ($refundAcceptStatus) {
            1 => ['label' => 'refund_accepted', 'code' => 2],
            2 => ['label' => 'refund_rejected', 'code' => 3],
            default => ['label' => 'refund_pending', 'code' => 1],
        };
    }
}
