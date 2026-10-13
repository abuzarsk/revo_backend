<?php

namespace App\Controllers;

use App\Core\Controller;

class RevoBrandController extends Controller
{
    /**
     * GET /brands - list brands for logged-in company (paginated)
     *
     * Query parameters:
     * - page (int, default 1)
     * - per_page (int, default 1)
     */
    public function index()
    {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 1;
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $user = $this->requireAuth();
        if (!$user) {
            return;
        }

        if (!isset($user['role']) || (int)$user['role'] !== 2) {
            $this->error('You are not authorized to get a brands list.', 401);
            return;
        }

        $companyModel = $this->model('RevoCompany');
        $company = $companyModel->getByUserId((int)$user['sub']);
        if (!$company) {
            $this->error('Company not found', 404);
            return;
        }

        $brandModel = $this->model('RevoBrand');
        $result = $brandModel->getPaginatedByCompany((int)$company['company'], $page, $perPage);

        $this->success($result, 'Brands retrieved successfully');
    }

    /**
     * GET /revo-brands/{id} - get a single brand
     */
    public function show($id = null)
    {
        $id = $id ?? ($this->params['id'] ?? null);

        if (!$id) {
            $this->error('Brand ID is required', 400);
            return;
        }

        $brandModel = $this->model('RevoBrand');
        $brand = $brandModel->getById((int)$id);

        if (!$brand) {
            $this->notFound('Brand not found');
            return;
        }

        $this->success($brand, 'Brand retrieved successfully');
    }

    /**
     * POST /revo-brands - create new brand
     *
     * Expected JSON body fields (matching DB columns):
     * - company_id
     * - brand_name
     * - created_by
     * - updated_by (optional; falls back to created_by)
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
        if (!isset($user['role']) || (int)$user['role'] !== 2) {
            $this->error('You are not authorized to reject role requests.', 401);
            return;
        }

        $companyModel = $this->model('RevoCompany');
        $company = $companyModel->getByUserId((int)$user['sub']);
        if (!$company) {
            $this->error('Company not found', 404);
            return;
        }

        $data = $this->getRequestBody();
        $data['company_id'] = (int)$company['company'];
        $data['created_by'] = (int)$user['sub'];
       // print_r($data);

//        exit();

        $brandModel = $this->model('RevoBrand');
        $result = $brandModel->create($data);


        if ($result['status'] && $result['error'] === 201) {
            $response = [
                'id' => $result['id'],
                'company_id' => (int)$company['company'],//$data['company_id'],
                'brand_name' => $data['brand_name'],
                'updated_by' => (int)$user['sub'],
                'updated_on' => date('Y-m-d H:i:s'),
                'created_by' => (int)$data['created_by'],
                'created_on' => date('Y-m-d H:i:s'),
            ];

            $this->success($response, 'Brand created successfully', 201);
        } elseif ($result['error'] === 400) {
            $this->error($result['message'] ?? 'Invalid data for brand', 400);
        } elseif ($result['error'] === 409) {
            $this->error('Brand already exists', 409);
        } else {
            $this->error($result['message'] ?? 'Failed to create brand', 500);
        }
    }

    /**
     * PUT/PATCH /revo-brands/{id} - update brand
     *
     * Body may include any updatable fields:
     * - company_id
     * - brand_name
     * - updated_by
     */
    public function update()
    {
        if (!in_array($this->getMethod(), ['PUT', 'PATCH','POST'], true)) {
            $this->error('Method not allowed', 405);
            return;
        }

        $id = $this->params['id'] ?? null;
        if (!$id) {
            $this->error('Brand ID is required', 400);
            return;
        }

        $user = $this->requireAuth();
        if (!$user) {
            return;
        }
        if (!isset($user['role']) || (int)$user['role'] !== 2) {
            $this->error('You are not authorized to update a brand.', 401);
            return;
        }

        $companyModel = $this->model('RevoCompany');
        $company = $companyModel->getByUserId((int)$user['sub']);
        if (!$company) {
            $this->error('Company not found', 404);
            return;
        }

        $data = $this->getRequestBody();
        $data['company_id'] = (int)$company['company'];
        $data['updated_by'] = (int)$user['sub'];

        $brandModel = $this->model('RevoBrand');
        $updated = $brandModel->update((int)$id, $data);

        if ($updated) {
            $updatedBrand = $brandModel->getById((int)$id);
            $this->success($updatedBrand, 'Brand updated successfully');
        } else {
            $this->error('Failed to update brand', 500);
        }
    }

    /**
     * DELETE /revo-brands/{id} - delete brand
     */
    public function delete()
    {
        if ($this->getMethod() !== 'DELETE') {
            $this->error('Method not allowed', 405);
            return;
        }

        $id = $this->params['id'] ?? null;
        if (!$id) {
            $this->error('Brand ID is required', 400);
            return;
        }

        $brandModel = $this->model('RevoBrand');
        $deleted = $brandModel->delete((int)$id);

        if ($deleted) {
            $this->success(null, 'Brand deleted successfully');
        } else {
            $this->error('Failed to delete brand', 500);
        }
    }

    public function getbrandslist()
    {
        $brandModel = $this->model('RevoBrand');
        $user = $this->requireAuth();
        if (!$user) {
            return;
        }
        
        $role = $user['role'];

        $userRoleModel = $this->model('RevoUserRole');
        $userRole = $userRoleModel->getByUserId((int)$user['sub']);
        if (!$userRole) {
            $this->error('User role not found', 404);
            return;
        }
        $hasRole = false;
        foreach($userRole as $userRole){
            if($userRole['revo_role_id'] == $role){
                $hasRole=true;
                break;
            }
        }
        if (!$hasRole) {
            $this->error('You are not authorized to get a brands list.', 401);
            return;
        }

        

        $brands = $brandModel->getBrandsList((int)$role, (int)$user['sub']);
        $this->success($brands ?: [], 'Brands retrieved successfully');
    }

}

