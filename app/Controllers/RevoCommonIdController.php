<?php

namespace App\Controllers;

use App\Core\Controller;

class RevoCommonIdController extends Controller
{
    private function authorize(array $user): bool
    {
        if (!isset($user['role']) || !in_array((int) $user['role'], [1, 2, 3], true)) {
            $this->error('You are not authorized to access common IDs.', 401);
            return false;
        }
        return true;
    }

    public function index()
    {
        $user = $this->requireAuth();
        if (!$user || !$this->authorize($user)) {
            return;
        }

        $commonIdModel = $this->model('RevoCommonId');
        $rows = $commonIdModel->getAll();
        $this->success($rows, 'Common IDs retrieved successfully');
    }

    public function show()
    {
        $id = $this->params['id'] ?? null;
        if (!$id) {
            $this->error('ID is required', 400);
            return;
        }

        $user = $this->requireAuth();
        if (!$user || !$this->authorize($user)) {
            return;
        }

        $commonIdModel = $this->model('RevoCommonId');
        $row = $commonIdModel->getById((int) $id);
        if (!$row) {
            $this->notFound('Common ID record not found');
            return;
        }

        $this->success($row, 'Common ID retrieved successfully');
    }

    public function create()
    {
        if ($this->getMethod() !== 'POST') {
            $this->error('Method not allowed', 405);
            return;
        }

        $user = $this->requireAuth();
        if (!$user || !$this->authorize($user)) {
            return;
        }

        $data = $this->getRequestBody();
        $data['unique_id'] = isset($data['unique_id']) ? (int) $data['unique_id'] : 0;
        $data['company_id'] = isset($data['company_id']) ? (int) $data['company_id'] : 0;
        $data['mediator_id'] = isset($data['mediator_id']) ? (int) $data['mediator_id'] : 0;
        $data['created_by'] = (int) $user['sub'];
        $data['updated_by'] = (int) $user['sub'];

        $commonIdModel = $this->model('RevoCommonId');
        $result = $commonIdModel->create($data);

        if ($result['status'] && $result['error'] === 201) {
            $response = [
                'id' => (int) $result['id'],
                'unique_id' => (int) $data['unique_id'],
                'company_id' => (int) $data['company_id'],
                'mediator_id' => (int) $data['mediator_id'],
                'created_by' => (int) $data['created_by'],
            ];
            $this->success($response, 'Common ID created successfully', 201);
        } elseif (($result['error'] ?? 500) === 400) {
            $this->error($result['message'] ?? 'Invalid data for common ID', 400);
        } elseif (($result['error'] ?? 500) === 409) {
            $this->error('Common ID already exists', 409);
        } else {
            $this->error($result['message'] ?? 'Failed to create common ID', 500);
        }
    }

    public function update()
    {
        if (!in_array($this->getMethod(), ['PUT', 'PATCH', 'POST'], true)) {
            $this->error('Method not allowed', 405);
            return;
        }

        $user = $this->requireAuth();
        if (!$user || !$this->authorize($user)) {
            return;
        }

        $data = $this->getRequestBody();
        $id = isset($data['id']) ? (int) $data['id'] : 0;
        if (!$id) {
            $this->error('ID is required', 400);
            return;
        }

        if (isset($data['unique_id'])) {
            $data['unique_id'] = (int) $data['unique_id'];
        }
        if (isset($data['company_id'])) {
            $data['company_id'] = (int) $data['company_id'];
        }
        if (isset($data['mediator_id'])) {
            $data['mediator_id'] = (int) $data['mediator_id'];
        }
        $data['updated_by'] = (int) $user['sub'];

        $commonIdModel = $this->model('RevoCommonId');
        $existing = $commonIdModel->getById($id);
        if (!$existing) {
            $this->notFound('Common ID record not found');
            return;
        }

        $updated = $commonIdModel->update($id, $data);
        if ($updated) {
            $updatedRow = $commonIdModel->getById($id);
            $this->success($updatedRow, 'Common ID updated successfully');
        } else {
            $this->error('Failed to update common ID', 500);
        }
    }

    public function delete()
    {
        if ($this->getMethod() !== 'DELETE') {
            $this->error('Method not allowed', 405);
            return;
        }

        $id = $this->params['id'] ?? null;
        if (!$id) {
            $this->error('ID is required', 400);
            return;
        }

        $user = $this->requireAuth();
        if (!$user || !$this->authorize($user)) {
            return;
        }

        $commonIdModel = $this->model('RevoCommonId');
        $existing = $commonIdModel->getById((int) $id);
        if (!$existing) {
            $this->notFound('Common ID record not found');
            return;
        }

        $deleted = $commonIdModel->delete((int) $id);
        if ($deleted) {
            $this->success(null, 'Common ID deleted successfully');
        } else {
            $this->error('Failed to delete common ID', 500);
        }
    }
}
