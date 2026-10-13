<?php

namespace App\Controllers;

use App\Core\Controller;

class RoleController extends Controller
{
    /**
     * GET /roles - list all roles
     */
    public function index()
    {
        $roleModel = $this->model('Role');
        $roles = $roleModel->getAll();

        $this->success($roles, 'Roles retrieved successfully');
    }

    /**
     * GET /roles/{id} - get a single role
     */
    public function show($id = null)
    {
        $id = $id ?? ($this->params['id'] ?? null);

        if (!$id) {
            $this->error('Role ID is required', 400);
            return;
        }

        $roleModel = $this->model('Role');
        $role = $roleModel->getById((int)$id);

        if (!$role) {
            $this->notFound('Role not found');
            return;
        }

        $this->success($role, 'Role retrieved successfully');
    }

    /**
     * POST /roles - create new role
     
    public function create()
    {
        if ($this->getMethod() !== 'POST') {
            $this->error('Method not allowed', 405);
            return;
        }

        $data = $this->getRequestBody();

        if (empty($data['role'])) {
            $this->error('Field "role" is required', 400);
            return;
        }

        $roleModel = $this->model('Role');
        $result = $roleModel->create($data);

        if ($result['status'] && $result['error'] === 201) {
            $response = [
                'id' => $result['id'],
                'role' => $data['role'],
            ];
            $this->success($response, 'Role created successfully', 201);
        } elseif ($result['error'] === 400) {
            $this->error($result['message'] ?? 'Invalid data for role', 400);
        } elseif ($result['error'] === 409) {
            $this->error('Role already exists', 409);
        } else {
            $this->error($result['message'] ?? 'Failed to create role', 500);
        }
    }*/

    /**
     * PUT/PATCH /roles/{id} - update role
     
    public function update()
    {
        if (!in_array($this->getMethod(), ['PUT', 'PATCH'], true)) {
            $this->error('Method not allowed', 405);
            return;
        }

        $id = $this->params['id'] ?? null;
        if (!$id) {
            $this->error('Role ID is required', 400);
            return;
        }

        $data = $this->getRequestBody();

        $roleModel = $this->model('Role');
        $updated = $roleModel->update((int)$id, $data);

        if ($updated) {
            $updatedRole = $roleModel->getById((int)$id);
            $this->success($updatedRole, 'Role updated successfully');
        } else {
            $this->error('Failed to update role', 500);
        }
    }*/

    /**
     * DELETE /roles/{id} - delete role
     
    public function delete()
    {
        if ($this->getMethod() !== 'DELETE') {
            $this->error('Method not allowed', 405);
            return;
        }

        $id = $this->params['id'] ?? null;
        if (!$id) {
            $this->error('Role ID is required', 400);
            return;
        }

        $roleModel = $this->model('Role');
        $deleted = $roleModel->delete((int)$id);

        if ($deleted) {
            $this->success(null, 'Role deleted successfully');
        } else {
            $this->error('Failed to delete role', 500);
        }
    }*/
}

