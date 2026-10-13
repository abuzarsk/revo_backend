<?php

namespace App\Controllers;

use App\Core\Controller;

class RevoUserRoleRequestController extends Controller
{
    /**
     * GET /revo-user-role-requests - list all requests
     */
    public function index()
    {
        $model = $this->model('RevoUserRoleRequest');
        $rows = $model->getAll();

        $this->success($rows, 'User role requests retrieved successfully');
    }

    /**
     * GET /revo-user-role-requests/{id} - get a single request
     */
    public function show($id = null)
    {
        $id = $id ?? ($this->params['id'] ?? null);

        if (!$id) {
            $this->error('Role request ID is required', 400);
            return;
        }

        $model = $this->model('RevoUserRoleRequest');
        $row = $model->getById((int)$id);

        if (!$row) {
            $this->notFound('User role request not found');
            return;
        }

        $this->success($row, 'User role request retrieved successfully');
    }

    /**
     * GET /admin/role-requests/role-2 - list requests where revo_role_id=2 (admin only, role==1) with pagination.
     *
     * Optional query parameter:
     * - page (int, default 1)
     */
    public function forRole2()
    {
        $user = $this->requireAuth();
        if (!$user) {
            return;
        }
        if (!isset($user['role']) || (int)$user['role'] !== 1) {
            $this->error('You are not authorized to get a role requests list.', 401);
            return;
        }

        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $page = max(1, $page);

        $model = $this->model('RevoUserRoleRequest');
        $result = $model->getByRoleIdPaginated(2, $page, 20);

        // Attach related company_temp records for each request's revo_user_id
        $companyModel = $this->model('RevoCompany');
        $userModel = $this->model('RevoUser');
        foreach ($result['data'] as &$row) {
            $row['company'] = $companyModel->getByUserId((int)$row['revo_user_id']);
            $row['user'] = $userModel->getById((int)$row['revo_user_id']);
        }
        unset($row);

        $this->success($result, 'Role requests for role 2 retrieved successfully');
    }

    /**
     * GET /revo-user-role-requests/user/{revo_user_id} - list requests for a user
     */
    public function byUserId($revoUserId = null)
    {
        $revoUserId = $revoUserId ?? ($this->params['revo_user_id'] ?? null);

        if (!$revoUserId) {
            $this->error('User ID is required', 400);
            return;
        }

        $model = $this->model('RevoUserRoleRequest');
        $rows = $model->getByUserId((int)$revoUserId);

        $this->success($rows, 'User role requests for user retrieved successfully');
    }

    /**
     * POST /revo-user-role-requests - create new request
     *
     * Expected JSON body fields (matching DB column names):
     * - revo_user_id
     * - revo_role_id
     * - status (optional)
     * - created_by
     * - updated_by (optional; falls back to created_by)
     */
    public function create()
    {
        if ($this->getMethod() !== 'POST') {
            $this->error('Method not allowed', 405);
            return;
        }

        $data = $this->getRequestBody();

        $model = $this->model('RevoUserRoleRequest');
        $result = $model->create($data);

        if ($result['status'] && $result['error'] === 201) {
            $response = [
                'revo_role_request_id' => $result['id'],
                'revo_user_id' => (int)$data['revo_user_id'],
                'revo_role_id' => (int)$data['revo_role_id'],
                'status' => $data['status'] ?? null,
            ];
            $this->success($response, 'User role request created successfully', 201);
        } elseif ($result['error'] === 400) {
            $this->error($result['message'] ?? 'Invalid data for user role request', 400);
        } elseif ($result['error'] === 409) {
            $this->error('User role request already exists', 409);
        } else {
            $this->error($result['message'] ?? 'Failed to create user role request', 500);
        }
    }

    /**
     * PUT/PATCH /revo-user-role-requests/{id} - update request
     *
     * Body may include:
     * - revo_user_id
     * - revo_role_id
     * - status
     * - updated_by
     */
    public function update()
    {
        if (!in_array($this->getMethod(), ['PUT', 'PATCH'], true)) {
            $this->error('Method not allowed', 405);
            return;
        }

        $id = $this->params['id'] ?? null;
        if (!$id) {
            $this->error('Role request ID is required', 400);
            return;
        }

        $data = $this->getRequestBody();

        $model = $this->model('RevoUserRoleRequest');
        $updated = $model->update((int)$id, $data);

        if ($updated) {
            $updatedRow = $model->getById((int)$id);
            $this->success($updatedRow, 'User role request updated successfully');
        } else {
            $this->error('Failed to update user role request', 500);
        }
    }

    /**
     * DELETE /revo-user-role-requests/{id} - delete request
     */
    public function delete()
    {
        if ($this->getMethod() !== 'DELETE') {
            $this->error('Method not allowed', 405);
            return;
        }

        $id = $this->params['id'] ?? null;
        if (!$id) {
            $this->error('Role request ID is required', 400);
            return;
        }

        $model = $this->model('RevoUserRoleRequest');
        $deleted = $model->delete((int)$id);

        if ($deleted) {
            $this->success(null, 'User role request deleted successfully');
        } else {
            $this->error('Failed to delete user role request', 500);
        }
    }
}

