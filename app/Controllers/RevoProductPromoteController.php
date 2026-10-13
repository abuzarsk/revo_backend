<?php

namespace App\Controllers;

use App\Core\Controller;

class RevoProductPromoteController extends Controller
{
    /**
     * GET /products/promotions - list promotions for logged-in mediator
     */
    public function index()
    {
        $user = $this->requireAuth();
        if (!$user) {
            return;
        }

        if (!isset($user['role']) || (int)$user['role'] !== 3) {
            $this->error('You are not authorized to get promotions.', 401);
            return;
        }

        $mediatorModel = $this->model('RevoMediator');
        $mediator = $mediatorModel->getByUserId((int)$user['sub']);
        if (!$mediator) {
            $this->error('Mediator not found', 404);
            return;
        }

        $promoteModel = $this->model('RevoProductPromote');
        $rows = $promoteModel->getAllByMediator((int)$mediator['id']);
        $this->success($rows, 'Promotions retrieved successfully');
    }

    /**
     * GET /products/promotions/{id} - get single promotion
     */
    public function show()
    {
        $id = $this->params['id'] ?? null;
        if (!$id) {
            $this->error('Promotion ID is required', 400);
            return;
        }

        $user = $this->requireAuth();
        if (!$user) {
            return;
        }

        $promoteModel = $this->model('RevoProductPromote');
        $row = $promoteModel->getById((int)$id);
        if (!$row) {
            $this->notFound('Promotion not found');
            return;
        }

        if ((int)$user['role'] !== 1) {
            $mediatorModel = $this->model('RevoMediator');
            $mediator = $mediatorModel->getByUserId((int)$user['sub']);
            if (!$mediator || (int)$row['mediator_id'] !== (int)$mediator['id']) {
                $this->error('You are not authorized to view this promotion.', 401);
                return;
            }
        }

        $this->success($row, 'Promotion retrieved successfully');
    }

    /**
     * POST /products/promotions - create new promotion
     */
    public function create()
    {
        if ($this->getMethod() !== 'POST') {
            $this->error('Method not allowed', 405);
            return;
        }

        $user = $this->requireAuth();
        if (!$user) {
            return;
        }

        if (!isset($user['role']) || (int)$user['role'] !== 3) {
            $this->error('You are not authorized to create a promotion.', 401);
            return;
        }

        $mediatorModel = $this->model('RevoMediator');
        $mediator = $mediatorModel->getByUserId((int)$user['sub']);
        if (!$mediator) {
            $this->error('Mediator not found', 404);
            return;
        }

        $commonIdModel = $this->model('RevoCommonId');
        $commonId = $commonIdModel->getByMediatorId((int)$mediator['id']);
        if (!$commonId) {
            $this->error('Common ID not found', 404);
            return;
        }

        $data = $this->getRequestBody();
        $data['product_id'] = isset($data['product_id']) ? (int)$data['product_id'] : 0;
        $data['common_id'] = (int)$commonId['unique_id'];
        $data['status'] = isset($data['status']) ? (int)$data['status'] : 1;
        $data['created_by'] = (int)$user['sub'];
        $data['updated_by'] = (int)$user['sub'];

        if (!$data['product_id']) {
            $this->error('Product ID is required', 400);
            return;
        }

        $productModel = $this->model('RevoProduct');
        $product = $productModel->getById((int)$data['product_id']);
        if (!$product) {
            $this->error('Product not found', 404);
            return;
        }

        $productPromoteModel = $this->model('RevoProductPromote');
        $productPromote = $productPromoteModel->getByProductIdByMediator((int)$data['product_id'], (int)$data['common_id']);

        if ($productPromote) {
            $updated = $productPromoteModel->update((int)$productPromote['id'], $data);
            if ($updated) {
                $result = [
                    'status' => true,
                    'error' => 201,
                    'id' => (int)$productPromote['id'],
                ];
            } else {
                $result = [
                    'status' => false,
                    'error' => 500,
                    'message' => 'Failed to update promotion',
                ];
            }
        } else {

            $result = $productPromoteModel->create($data);
        }

        if ($result['status'] && $result['error'] === 201) {
            $response = [
                'id' => (int)$result['id'],
                'product_id' => (int)$data['product_id'],
                'common_id' => (int)$data['common_id'],
                'status' => (int)$data['status'],
                'created_by' => (int)$data['created_by'],
            ];
            $this->success($response, 'Promotion created successfully', 201);
        } elseif ($result['error'] === 400) {
            $this->error($result['message'] ?? 'Invalid data for promotion', 400);
        } elseif ($result['error'] === 409) {
            $this->error('Promotion already exists', 409);
        } else {
            $this->error($result['message'] ?? 'Failed to create promotion', 500);
        }
    }

    /**
     * POST /products/promotions/update - update promotion
     */
    public function update()
    {
        if (!in_array($this->getMethod(), ['PUT', 'PATCH', 'POST'], true)) {
            $this->error('Method not allowed', 405);
            return;
        }

        $user = $this->requireAuth();
        if (!$user) {
            return;
        }

        $data = $this->getRequestBody();
        $id = isset($data['id']) ? (int)$data['id'] : 0;
        if (!$id) {
            $this->error('Promotion ID is required', 400);
            return;
        }

        $promoteModel = $this->model('RevoProductPromote');
        $existing = $promoteModel->getById($id);
        if (!$existing) {
            $this->error('Promotion not found', 404);
            return;
        }

        if ((int)$user['role'] !== 1) {
            $mediatorModel = $this->model('RevoMediator');
            $mediator = $mediatorModel->getByUserId((int)$user['sub']);
            if (!$mediator || (int)$existing['mediator_id'] !== (int)$mediator['id']) {
                $this->error('You are not authorized to update this promotion.', 401);
                return;
            }
        }

        if (isset($data['product_id'])) {
            $data['product_id'] = (int)$data['product_id'];
        }
        if (isset($data['status'])) {
            $data['status'] = (int)$data['status'];
        }
        $data['updated_by'] = (int)$user['sub'];

        $updated = $promoteModel->update($id, $data);
        if ($updated) {
            $updatedRow = $promoteModel->getById($id);
            $this->success($updatedRow, 'Promotion updated successfully');
        } else {
            $this->error('Failed to update promotion', 500);
        }
    }

    /**
     * DELETE /products/promotions/{id} - delete promotion
     */
    public function delete()
    {
        if ($this->getMethod() !== 'DELETE') {
            $this->error('Method not allowed', 405);
            return;
        }

        $id = $this->params['id'] ?? null;
        if (!$id) {
            $this->error('Promotion ID is required', 400);
            return;
        }

        $user = $this->requireAuth();
        if (!$user) {
            return;
        }

        $promoteModel = $this->model('RevoProductPromote');
        $existing = $promoteModel->getById((int)$id);
        if (!$existing) {
            $this->error('Promotion not found', 404);
            return;
        }

        if ((int)$user['role'] !== 1) {
            $mediatorModel = $this->model('RevoMediator');
            $mediator = $mediatorModel->getByUserId((int)$user['sub']);
            if (!$mediator || (int)$existing['mediator_id'] !== (int)$mediator['id']) {
                $this->error('You are not authorized to delete this promotion.', 401);
                return;
            }
        }

        $deleted = $promoteModel->delete((int)$id);
        if ($deleted) {
            $this->success(null, 'Promotion deleted successfully');
        } else {
            $this->error('Failed to delete promotion', 500);
        }
    }
}
