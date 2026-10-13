<?php

namespace App\Controllers;

use App\Core\Controller;

class RevoCompanyController extends Controller
{
    /**
     * GET /revo-companies - list companies with pagination (20 per page)
     *
     * Optional query parameter:
     * - page (int, default 1)
     */
    public function index()
    {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $page = max(1, $page);

        $user = $this->requireAuth();
        if (!$user) {
            $this->error('Authentication required. Please provide a valid token.', 401);
            return;
        }
        if($user['role'] != 1){
            $this->error('You are not authorized to get a companies list.', 401);
            return;
        }

        $companyModel = $this->model('RevoCompany');
        $result = $companyModel->getPaginated($page, 20);

        $userModel = $this->model('RevoUser');
        foreach ($result['data'] as &$company) {
            //print_r($company['user']);
            $company['user'] = $userModel->getById((int)$company['user']);
        }
        unset($company);

        $this->success($result, 'Companies retrieved successfully');
    }

    /**
     * GET /revo-companies/{id} - get a single company
     */
    public function show($id = null)
    {
        $id = $id ?? ($this->params['id'] ?? null);

        if (!$id) {
            $this->error('Company ID is required', 400);
            return;
        }

        $companyModel = $this->model('RevoCompany');
        $company = $companyModel->getById((int)$id);

        if (!$company) {
            $this->notFound('Company not found');
            return;
        }

        $this->success($company, 'Company retrieved successfully');
    }

    /**
     * POST /revo-companies - create new company
     *
     * Expected JSON body fields (matching DB columns):
     * - revo_user_id
     * - logo
     * - alias
     * - company_size
     * - group_link
     * - slot_in_a_month
     * - description
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

        $companyModel = $this->model('RevoCompany');
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
                'status' => $data['status'] ?? null,
                'created_by' => (int)$data['created_by'],
            ];
            $this->success($response, 'Company created successfully', 201);
        } elseif ($result['error'] === 400) {
            $this->error($result['message'] ?? 'Invalid data for company', 400);
        } elseif ($result['error'] === 409) {
            $this->error('Company already exists', 409);
        } else {
            $this->error($result['message'] ?? 'Failed to create company', 500);
        }
    }

    /**
     * PUT/PATCH /revo-companies/{id} - update company
     *
     * Body may include any updatable fields:
     * - revo_user_id
     * - logo
     * - alias
     * - company_size
     * - group_link
     * - slot_in_a_month
     * - description
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
            $this->error('Company ID is required', 400);
            return;
        }

        $data = $this->getRequestBody();

        $companyModel = $this->model('RevoCompany');
        $updated = $companyModel->update((int)$id, $data);

        if ($updated) {
            $updatedCompany = $companyModel->getById((int)$id);
            $this->success($updatedCompany, 'Company updated successfully');
        } else {
            $this->error('Failed to update company', 500);
        }
    }

    /**
     * DELETE /revo-companies/{id} - delete company
     */
    public function delete()
    {
        if ($this->getMethod() !== 'DELETE') {
            $this->error('Method not allowed', 405);
            return;
        }

        $id = $this->params['id'] ?? null;
        if (!$id) {
            $this->error('Company ID is required', 400);
            return;
        }

        $companyModel = $this->model('RevoCompany');
        $deleted = $companyModel->delete((int)$id);

        if ($deleted) {
            $this->success(null, 'Company deleted successfully');
        } else {
            $this->error('Failed to delete company', 500);
        }
    }

    public function getbyalias()
    {
        $alias = $this->params['alias'] ?? null;
        if (!$alias) {
            $this->error('Company alias is required', 400);
            return;
        }

        $user = $this->requireAuth();
        if (!$user) {
            $this->error('Authentication required. Please provide a valid token.', 401);
            return;
        }

        $companyModel = $this->model('RevoCompany');
        $companies = $companyModel->getByAlias($alias,(int)$user['sub'],(int)$user['role']);
        
        if($user['role'] == 3){
            $mediatorModel = $this->model('RevoMediator');
            $mediator = $mediatorModel->getByUserId((int)$user['sub']);
            if (!$mediator) {
                $this->error('Mediator not found', 404);
                return;
            }
            for($i=0;$i<count($companies);$i++){
                $connectModel = $this->model('RevoConnect');
                $connects = $connectModel->getMediatorCompanyConnects($companies[$i]['company'], $mediator['id']);
                $companies[$i]['connects'] = $connects;
            }
        }
        if (!$companies) {
            $this->error('Company not found', 404);
            return;
        }
        $this->success($companies, 'Company retrieved successfully');
    }

    /**
     * PATCH /admin/companies/{id}/verify - verify (approve) a company by setting its status.
     * Admin-only (role == 1).
     * Optional JSON body field: status (defaults to 1).
     */
    public function verify()
    {
        if ($this->getMethod() !== 'POST') {
            $this->error('Method not allowed', 405);
            return;
        }

        $user = $this->requireAuth();
        if (!$user) {
            return;
        }
        if (!isset($user['role']) || (int)$user['role'] !== 1) {
            $this->error('You are not authorized to verify companies.', 401);
            return;
        }

        
        $data = $this->getRequestBody();
        $id = isset($data['company']) ? $data['company'] : null;
        $status = isset($data['status']) ? $data['status'] : 1;

        $companyModel = $this->model('RevoCompany');
        $updated = $companyModel->update((int)$id, [
            'status' => $status,
            'default_role' => 2,
            'updated_by' => isset($user['sub']) ? (int)$user['sub'] : null,
            'updated_on' => date('Y-m-d H:i:s'),
        ]);

        // Add user role as 2 in USERROLE after company approval
        if ($updated) {
            $userRoleModel = $this->model('RevoUserRole');
            // Check if the user already has this role (avoid duplicate)
            $company = $companyModel->getById((int)$id);
            if ($company && isset($company['revo_user_id'])) {
                $userRoles = $userRoleModel->getByUserId((int)$company['revo_user_id']);
                $hasRole2 = false;
                foreach ($userRoles as $role) {
                    if (isset($role['revo_role_id']) && (int)$role['revo_role_id'] === 2) {
                        $hasRole2 = true;
                        break;
                    }
                }
                if (!$hasRole2) {
                    $userRoleModel->create([
                        'revo_user_id' => (int)$company['revo_user_id'],
                        'revo_role_id' => 2,
                        'created_by' => isset($user['sub']) ? (int)$user['sub'] : null,
                        'updated_by' => isset($user['sub']) ? (int)$user['sub'] : null,
                    ]);
                }
            }

                // Remove all user role requests for this company user after approval
                $userRoleRequestModel = $this->model('RevoUserRoleRequest');
                if ($company && isset($company['revo_user_id'])) {
                    $userRoleRequestModel->deleteByUserId((int)$company['revo_user_id']);
                }
        }

        if ($updated) {
            $updatedCompany = $companyModel->getById((int)$id);
            $this->success($updatedCompany, 'Company verified successfully');
        } else {
            $this->error('Failed to verify company', 500);
        }
    }

    /**
     * POST /admin/companies/pause - pause a company (set status = 3).
     * Admin-only (role == 1).
     * Expected JSON body: { "company": <company_id> }
     */
    public function pause()
    {
        if ($this->getMethod() !== 'POST') {
            $this->error('Method not allowed', 405);
            return;
        }

        $user = $this->requireAuth();
        if (!$user) {
            return;
        }
        if (!isset($user['role']) || (int)$user['role'] !== 1) {
            $this->error('You are not authorized to pause companies.', 401);
            return;
        }

        $data = $this->getRequestBody();
        $id = isset($data['company']) ? $data['company'] : null;

        if (!$id) {
            $this->error('Company ID is required', 400);
            return;
        }

        $companyModel = $this->model('RevoCompany');
        $updated = $companyModel->update((int)$id, [
            'status' => 3,
            'updated_by' => isset($user['sub']) ? (int)$user['sub'] : null,
            'updated_on' => date('Y-m-d H:i:s'),
        ]);

        if ($updated) {
            $updatedCompany = $companyModel->getById((int)$id);
            $this->success($updatedCompany, 'Company paused successfully');
        } else {
            $this->error('Failed to pause company', 500);
        }
    }

    /**
     * POST /admin/companies/cancel - cancel a company (set status = 5).
     * Admin-only (role == 1).
     * Expected JSON body: { "company": <company_id> }
     */
    public function cancel()
    {
        if ($this->getMethod() !== 'POST') {
            $this->error('Method not allowed', 405);
            return;
        }

        $user = $this->requireAuth();
        if (!$user) {
            return;
        }
        if (!isset($user['role']) || (int)$user['role'] !== 1) {
            $this->error('You are not authorized to cancel companies.', 401);
            return;
        }

        $data = $this->getRequestBody();
        $id = isset($data['company']) ? $data['company'] : null;

        if (!$id) {
            $this->error('Company ID is required', 400);
            return;
        }

        $companyModel = $this->model('RevoCompany');
        $updated = $companyModel->update((int)$id, [
            'status' => 5,
            'updated_by' => isset($user['sub']) ? (int)$user['sub'] : null,
            'updated_on' => date('Y-m-d H:i:s'),
        ]);

        if ($updated) {
            $updatedCompany = $companyModel->getById((int)$id);
            $this->success($updatedCompany, 'Company cancelled successfully');
        } else {
            $this->error('Failed to cancel company', 500);
        }
    }

    /**
     * POST /admin/companies/resume - resume a company (set status = 1).
     * Admin-only (role == 1).
     * Expected JSON body: { "company": <company_id> }
     */
    public function resume()
    {
        if ($this->getMethod() !== 'POST') {
            $this->error('Method not allowed', 405);
            return;
        }

        $user = $this->requireAuth();
        if (!$user) {
            return;
        }
        if (!isset($user['role']) || (int)$user['role'] !== 1) {
            $this->error('You are not authorized to resume companies.', 401);
            return;
        }

        $data = $this->getRequestBody();
        $id = isset($data['company']) ? $data['company'] : null;

        if (!$id) {
            $this->error('Company ID is required', 400);
            return;
        }

        $companyModel = $this->model('RevoCompany');
        $updated = $companyModel->update((int)$id, [
            'status' => 1,
            'updated_by' => isset($user['sub']) ? (int)$user['sub'] : null,
            'updated_on' => date('Y-m-d H:i:s'),
        ]);

        if ($updated) {
            $updatedCompany = $companyModel->getById((int)$id);
            $this->success($updatedCompany, 'Company resumed successfully');
        } else {
            $this->error('Failed to resume company', 500);
        }
    }

    /**
     * POST /admin/companyrequests/reject - reject a role request.
     * Sets company status = 4 and the related user role requests' status = 4.
     * Admin-only (role == 1).
     * Expected JSON body: { "company": <company_id> }
     */
    public function rejectRoleRequest()
    {
        if ($this->getMethod() !== 'POST') {
            $this->error('Method not allowed', 405);
            return;
        }

        $user = $this->requireAuth();
        if (!$user) {
            return;
        }
        if (!isset($user['role']) || (int)$user['role'] !== 1) {
            $this->error('You are not authorized to reject role requests.', 401);
            return;
        }

        $data = $this->getRequestBody();
        $id = isset($data['company']) ? $data['company'] : null;

        if (!$id) {
            $this->error('Company ID is required', 400);
            return;
        }

        $companyModel = $this->model('RevoCompany');
        $company = $companyModel->getById((int)$id);
        if (!$company || !isset($company['revo_user_id'])) {
            $this->error('Company not found', 404);
            return;
        }

        // 1) Update company status to 4
        $updatedCompanyStatus = $companyModel->update((int)$id, [
            'status' => 4,
            'updated_by' => isset($user['sub']) ? (int)$user['sub'] : null,
            'updated_on' => date('Y-m-d H:i:s'),
        ]);

        // 2) Update all role requests for this user to status = 4
        $userRoleRequestModel = $this->model('RevoUserRoleRequest');
        $updatedRequests = $userRoleRequestModel->updateStatusByUserId((int)$company['revo_user_id'], 4);

        if ($updatedCompanyStatus && $updatedRequests) {
            $updatedCompany = $companyModel->getById((int)$id);
            $this->success($updatedCompany, 'Role request rejected and company status updated successfully');
        } else {
            $this->error('Failed to reject role request', 500);
        }
    }

    public function searchbyalias()
    {
        $alias = $this->params['alias'] ?? null;
        if (!$alias) {
            $this->error('Company alias is required', 400);
            return;
        }
        $user = $this->requireAuth();
        if (!$user) {
            $this->error('Authentication required. Please provide a valid token.', 401);
            return;
        }

        $companyModel = $this->model('RevoCompany');
        $companies = $companyModel->searchByAlias($alias, $user);
        $this->success($companies, 'Companies retrieved successfully');
    }
}

