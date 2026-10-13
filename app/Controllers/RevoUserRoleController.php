<?php

namespace App\Controllers;

use App\Core\Controller;

class RevoUserRoleController extends Controller
{
    /**
     * GET /revo-user-roles - list all user-role links
     */
    public function index()
    {
        $model = $this->model('RevoUserRole');
        $rows = $model->getAll();

        $this->success($rows, 'User roles retrieved successfully');
    }

    /**
     * GET /revo-user-roles/{id} - get a single user-role link
     */
    public function show($id = null)
    {
        $id = $id ?? ($this->params['id'] ?? null);

        if (!$id) {
            $this->error('User role ID is required', 400);
            return;
        }

        $model = $this->model('RevoUserRole');
        $row = $model->getById((int)$id);

        if (!$row) {
            $this->notFound('User role not found');
            return;
        }

        $this->success($row, 'User role retrieved successfully');
    }

    /**
     * GET /revo-user-roles/user/{revo_user_id} - list roles for a given user
     */
    public function byUserId($revoUserId = null)
    {
        $revoUserId = $revoUserId ?? ($this->params['revo_user_id'] ?? null);

        if (!$revoUserId) {
            $this->error('User ID is required', 400);
            return;
        }

        $model = $this->model('RevoUserRole');
        $rows = $model->getByUserId((int)$revoUserId);

        $this->success($rows, 'User roles for user retrieved successfully');
    }

    

    /**
     * POST /revo-user-roles - create new user-role link
     *
     * Expected JSON body fields:
     * - revo_user_id
     * - revo_role_id
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

        $model = $this->model('RevoUserRole');
        $result = $model->create($data);

        if ($result['status'] && $result['error'] === 201) {
            $response = [
                'revo_user_role_id' => $result['id'],
                'revo_user_id' => (int)$data['revo_user_id'],
                'revo_role_id' => (int)$data['revo_role_id'],
            ];
            $this->success($response, 'User role created successfully', 201);
        } elseif ($result['error'] === 400) {
            $this->error($result['message'] ?? 'Invalid data for user role', 400);
        } elseif ($result['error'] === 409) {
            $this->error('User role link already exists', 409);
        } else {
            $this->error($result['message'] ?? 'Failed to create user role', 500);
        }
    }

    /**
     * PUT/PATCH /revo-user-roles/{id} - update user-role link
     *
     * Body may include:
     * - revo_user_id
     * - revo_role_id
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
            $this->error('User role ID is required', 400);
            return;
        }

        $data = $this->getRequestBody();

        $model = $this->model('RevoUserRole');
        $updated = $model->update((int)$id, $data);

        if ($updated) {
            $updatedRow = $model->getById((int)$id);
            $this->success($updatedRow, 'User role updated successfully');
        } else {
            $this->error('Failed to update user role', 500);
        }
    }

    /**
     * DELETE /revo-user-roles/{id} - delete user-role link
     */
    public function delete()
    {
        if ($this->getMethod() !== 'DELETE') {
            $this->error('Method not allowed', 405);
            return;
        }

        $id = $this->params['id'] ?? null;
        if (!$id) {
            $this->error('User role ID is required', 400);
            return;
        }

        $model = $this->model('RevoUserRole');
        $deleted = $model->delete((int)$id);

        if ($deleted) {
            $this->success(null, 'User role deleted successfully');
        } else {
            $this->error('Failed to delete user role', 500);
        }
    }
}

