<?php

namespace App\Controllers;

use App\Core\Controller;

class RevoMediatorController extends Controller
{
    /**
     * GET /revo-mediators - list mediators with pagination (20 per page)
     *
     * Optional query parameter: page (int, default 1)
     */
    public function index()
    {
        $user = $this->requireAuth();
        if (!$user) {
            $this->error('Authentication required. Please provide a valid token.', 401);
            return;
        }
        if ($user['role'] != 1) {
            $this->error('You are not authorized to get a mediators list.', 401);
            return;
        }

        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $page = max(1, $page);

        $mediatorModel = $this->model('RevoMediator');
        $result = $mediatorModel->getPaginated($page, 20);

        $userModel = $this->model('RevoUser');
        foreach ($result['data'] as &$mediator) {
            $mediator['user'] = $userModel->getById((int)$mediator['revo_user_id']);
        }
        unset($mediator);

        $this->success($result, 'Mediators retrieved successfully');
    }

    /**
     * GET /revo-mediators/{id} - get a single mediator
     */
    public function show($id = null)
    {
        $id = $id ?? ($this->params['id'] ?? null);

        if (!$id) {
            $this->error('Mediator ID is required', 400);
            return;
        }

        $mediatorModel = $this->model('RevoMediator');
        $mediator = $mediatorModel->getByUserId((int)$id);

        if (!$mediator) {
            $this->notFound('Mediator not found');
            return;
        }

        $this->success($mediator, 'Mediator retrieved successfully');
    }




    /**
     * POST /revo-mediators - create new mediator
     *
     * Expected JSON body fields (matching DB columns):
     * - revo_user_id
     * - profile_pic
     * - alias
     * - size
     * - group_link
     * - slot_in_a_month
     * - description
     * - created_by
     * - upated_by (optional; falls back to created_by)
     */
    public function create()
    {
        if ($this->getMethod() !== 'POST') {
            $this->error('Method not allowed', 405);
            return;
        }

        $data = $this->getRequestBody();

        $mediatorModel = $this->model('RevoMediator');
        //print_r($data);
        $result = $mediatorModel->create($data);

        if ($result['status'] && $result['error'] === 201) {
            $response = [
                'id' => $result['id'],
                'revo_user_id' => (int)$data['revo_user_id'],
                'mediator_name' => $data['revo_user_name'],
                'profile_pic' => $data['profile_pic'],
                'alias' => $data['alias'],
                'size' => (int)$data['size'],
                'group_link' => $data['group_link'],
                'slot_in_a_month' => (int)$data['slot_in_a_month'],
                'description' => $data['description'],
                'created_by' => (int)$data['created_by'],
            ];
            $this->success($response, 'Mediator created successfully', 201);
        } elseif ($result['error'] === 400) {
            $this->error($result['message'] ?? 'Invalid data for mediator', 400);
        } elseif ($result['error'] === 409) {
            $this->error('Mediator already exists', 409);
        } else {
            $this->error($result['message'] ?? 'Failed to create mediator', 500);
        }
    }

    /**
     * PUT/PATCH /revo-mediators/{id} - update mediator
     *
     * Body may include any updatable fields:
     * - revo_user_id
     * - profile_pic
     * - alias
     * - size
     * - group_link
     * - slot_in_a_month
     * - description
     * - upated_by
     */
    public function update()
    {
        if (!in_array($this->getMethod(), ['PUT', 'PATCH'], true)) {
            $this->error('Method not allowed', 405);
            return;
        }

        $id = $this->params['id'] ?? null;
        if (!$id) {
            $this->error('Mediator ID is required', 400);
            return;
        }

        $data = $this->getRequestBody();

        $mediatorModel = $this->model('RevoMediator');
        $updated = $mediatorModel->update((int)$id, $data);

        if ($updated) {
            $updatedMediator = $mediatorModel->getById((int)$id);
            $this->success($updatedMediator, 'Mediator updated successfully');
        } else {
            $this->error('Failed to update mediator', 500);
        }
    }

    /**
     * DELETE /revo-mediators/{id} - delete mediator
     */
    public function delete()
    {
        if ($this->getMethod() !== 'DELETE') {
            $this->error('Method not allowed', 405);
            return;
        }

        $id = $this->params['id'] ?? null;
        if (!$id) {
            $this->error('Mediator ID is required', 400);
            return;
        }

        $mediatorModel = $this->model('RevoMediator');
        $deleted = $mediatorModel->delete((int)$id);

        if ($deleted) {
            $this->success(null, 'Mediator deleted successfully');
        } else {
            $this->error('Failed to delete mediator', 500);
        }
    }
}

