<?php

namespace App\Controllers;

use App\Core\Controller;

class RevoUserBankController extends Controller
{
    /**
     * GET /user/bank - get logged-in user's bank details
     */
    public function show()
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
            $this->error('You are not authorized to access bank details.', 401);
            return;
        }

        $bankModel = $this->model('RevoUserBank');
        $row = $bankModel->getByUserId((int)$user['sub']);
        if (!$row) {
            $this->error('Bank details not found', 404);
            return;
        }

        $this->success($row, 'Bank details retrieved successfully');
    }

    /**
     * POST /user/bank - create bank details for logged-in user
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


        if ((int)$user['role'] !== 4) {
            $this->error('You are not authorized to create bank details.', 401);
            return;
        }

        $data = $this->getRequestBody();
        $data['user_id'] = (int)$user['sub'];
        $data['created_by'] = (int)$user['sub'];
        $data['updated_by'] = (int)$user['sub'];
        $data['revo_user_upi'] = isset($data['revo_user_upi']) ? (string)$data['revo_user_upi'] : '';
        $data['revo_user_bank_name'] = isset($data['revo_user_bank_name']) ? (string)$data['revo_user_bank_name'] : '';
        $data['revo_bank_holder_name'] = isset($data['revo_bank_holder_name']) ? (string)$data['revo_bank_holder_name'] : '';
        $data['revo_back_account_no'] = isset($data['revo_back_account_no']) ? (string)$data['revo_back_account_no'] : '';
        $data['revo_bank_ifsc'] = isset($data['revo_bank_ifsc']) ? (string)$data['revo_bank_ifsc'] : '';

        $bankModel = $this->model('RevoUserBank');
        $result = $bankModel->create($data);

        if ($result['status'] && $result['error'] === 201) {
            $createdRow = $bankModel->getById((int)$result['id']);
            $this->success($createdRow, 'Bank details created successfully', 201);
        } elseif (($result['error'] ?? 500) === 400) {
            $this->error($result['message'] ?? 'Invalid data for bank details', 400);
        } elseif (($result['error'] ?? 500) === 409) {
            $this->error($result['message'] ?? 'Bank details already exist', 409);
        } else {
            $this->error($result['message'] ?? 'Failed to create bank details', 500);
        }
    }

    /**
     * POST /user/bank/update - update logged-in user's bank details
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

        if ((int)$user['role'] !== 4) {
            $this->error('You are not authorized to update bank details.', 401);
            return;
        }

        $data = $this->getRequestBody();
        $bankModel = $this->model('RevoUserBank');
        $existing = $bankModel->getByUserId((int)$user['sub']);
        if (!$existing) {
            $this->error('Bank details not found', 404);
            return;
        }

        if (isset($data['revo_user_upi'])) {
            $data['revo_user_upi'] = (string)$data['revo_user_upi'];
        }
        if (isset($data['revo_user_bank_name'])) {
            $data['revo_user_bank_name'] = (string)$data['revo_user_bank_name'];
        }
        if (isset($data['revo_bank_holder_name'])) {
            $data['revo_bank_holder_name'] = (string)$data['revo_bank_holder_name'];
        }
        if (isset($data['revo_back_account_no'])) {
            $data['revo_back_account_no'] = (string)$data['revo_back_account_no'];
        }
        if (isset($data['revo_bank_ifsc'])) {
            $data['revo_bank_ifsc'] = (string)$data['revo_bank_ifsc'];
        }
        $data['updated_by'] = (int)$user['sub'];

        $updated = $bankModel->update((int)$existing['id'], $data);
        if ($updated) {
            $updatedRow = $bankModel->getById((int)$existing['id']);
            $this->success($updatedRow, 'Bank details updated successfully');
        } else {
            $this->error('Failed to update bank details', 500);
        }
    }

    /**
     * DELETE /user/bank - delete logged-in user's bank details
     */
    public function delete()
    {
        if ($this->getMethod() !== 'DELETE') {
            $this->error('Method not allowed', 405);
            return;
        }

        $user = $this->requireAuth();
        if (!$user) {
            return;
        }

        if ((int)$user['role'] !== 4) {
            $this->error('You are not authorized to delete bank details.', 401);
            return;
        }

        $bankModel = $this->model('RevoUserBank');
        $existing = $bankModel->getByUserId((int)$user['sub']);
        if (!$existing) {
            $this->error('Bank details not found', 404);
            return;
        }

        $deleted = $bankModel->delete((int)$existing['id']);
        if ($deleted) {
            $this->success(null, 'Bank details deleted successfully');
        } else {
            $this->error('Failed to delete bank details', 500);
        }
    }
}
