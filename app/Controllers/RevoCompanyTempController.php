<?php

namespace App\Controllers;

use App\Core\Controller;

class RevoCompanyTempController extends Controller
{
    /**
     * GET /revo-company-temps - list all temp companies
     */
    public function index()
    {
        $companyModel = $this->model('RevoCompanyTemp');
        $companies = $companyModel->getAll();

        $this->success($companies, 'Temp companies retrieved successfully');
    }

    /**
     * GET /revo-company-temps/{id} - get a single temp company
     */
    public function show($id = null)
    {
        $id = $id ?? ($this->params['id'] ?? null);

        if (!$id) {
            $this->error('Temp company ID is required', 400);
            return;
        }

        $companyModel = $this->model('RevoCompanyTemp');
        $company = $companyModel->getById((int)$id);

        if (!$company) {
            $this->notFound('Temp company not found');
            return;
        }

        $this->success($company, 'Temp company retrieved successfully');
    }

    /**
     * POST /revo-company-temps - create new temp company
     *
     * Expected JSON body fields (matching DB columns):
     * - revo_user_id
     * - logo
     * - alias
     * - company_size
     * - group_link
     * - slot_in_a_month
     * - description
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

        $companyModel = $this->model('RevoCompanyTemp');
        $result = $companyModel->create($data);

        if ($result['status'] && $result['error'] === 201) {
            $response = [
                'id' => $result['id'],
                'revo_user_id' => (int)$data['revo_user_id'],
                'logo' => $data['logo'],
                'alias' => $data['alias'],
                'company_size' => (int)$data['company_size'],
                'group_link' => $data['group_link'],
                'slot_in_a_month' => (int)$data['slot_in_a_month'],
                'description' => $data['description'],
                'created_by' => (int)$data['created_by'],
                'updated_by' => (int)$data['updated_by'],
            ];
            $this->success($response, 'Temp company created successfully', 201);
        } elseif ($result['error'] === 400) {
            $this->error($result['message'] ?? 'Invalid data for temp company', 400);
        } elseif ($result['error'] === 409) {
            $this->error('Temp company already exists', 409);
        } else {
            $this->error($result['message'] ?? 'Failed to create temp company', 500);
        }
    }

    /**
     * PUT/PATCH /revo-company-temps/{id} - update temp company
     *
     * Body may include any updatable fields:
     * - revo_user_id
     * - logo
     * - alias
     * - company_size
     * - group_link
     * - slot_in_a_month
     * - description
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
            $this->error('Temp company ID is required', 400);
            return;
        }

        $data = $this->getRequestBody();

        $companyModel = $this->model('RevoCompanyTemp');
        $updated = $companyModel->update((int)$id, $data);

        if ($updated) {
            $updatedCompany = $companyModel->getById((int)$id);
            $this->success($updatedCompany, 'Temp company updated successfully');
        } else {
            $this->error('Failed to update temp company', 500);
        }
    }

    /**
     * DELETE /revo-company-temps/{id} - delete temp company
     */
    public function delete()
    {
        if ($this->getMethod() !== 'DELETE') {
            $this->error('Method not allowed', 405);
            return;
        }

        $id = $this->params['id'] ?? null;
        if (!$id) {
            $this->error('Temp company ID is required', 400);
            return;
        }

        $companyModel = $this->model('RevoCompanyTemp');
        $deleted = $companyModel->delete((int)$id);

        if ($deleted) {
            $this->success(null, 'Temp company deleted successfully');
        } else {
            $this->error('Failed to delete temp company', 500);
        }
    }
}

