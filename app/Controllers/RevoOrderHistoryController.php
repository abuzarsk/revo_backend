<?php

namespace App\Controllers;

use App\Core\Controller;

class RevoOrderHistoryController extends Controller
{
    /**
     * GET /user/order-history - paginated order history for logged-in user
     *
     * Query parameters:
     * - page (int, default 1)
     * - per_page (int, default 10)
     */
    public function index()
    {
        if ($this->getMethod() !== 'GET') {
            $this->error('Method not allowed', 405);
            return;
        }

        $user = $this->requireAuth();
        if (!$user) {
            return;
        }

        if ((int)$user['role'] !== 4) {
            $this->error('You are not authorized to view order history.', 401);
            return;
        }

        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : Pagination_Default_Subsmission_Per_Page;
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $orderHistoryModel = $this->model('RevoOrderHistory');
        $result = $orderHistoryModel->getByUserIdPaginated((int)$user['sub'], $page, $perPage);

        $this->success($result, 'Order history retrieved successfully');
    }

    /**
     * GET /user/order-history/{id} - single order history by submission id
     */
    public function show()
    {
        if ($this->getMethod() !== 'GET') {
            $this->error('Method not allowed', 405);
            return;
        }

        $submissionId = isset($this->params['id']) ? (int)$this->params['id'] : 0;
        if (!$submissionId) {
            $this->error('Submission ID is required', 400);
            return;
        }

        $user = $this->requireAuth();
        if (!$user) {
            return;
        }

        if ((int)$user['role'] !== 4) {
            $this->error('You are not authorized to view order history.', 401);
            return;
        }

        $orderHistoryModel = $this->model('RevoOrderHistory');
        $row = $orderHistoryModel->getBySubmissionIdForUser($submissionId, (int)$user['sub']);
        if (!$row) {
            $this->error('Order not found', 404);
            return;
        }

        $this->success($row, 'Order history retrieved successfully');
    }
}
