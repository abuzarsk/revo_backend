<?php

namespace App\Controllers;

use App\Core\Controller;

class RevoFormController extends Controller
{
    /**
     * GET /forms/product - list forms by product_id (query/body)
     */
    public function byProduct()
    {
        $user = $this->requireAuth();
        if (!$user) {
            return;
        }

        if (!isset($user['role']) || (int)$user['role'] !== 2) {
            $this->error('You are not authorized to get forms.', 401);
            return;
        }

        $companyModel = $this->model('RevoCompany');
        $company = $companyModel->getByUserId((int)$user['sub']);
        if (!$company) {
            $this->error('Company not found', 404);
            return;
        }

        $productId = null;
        if (isset($_GET['product_id'])) {
            $productId = (int)$_GET['product_id'];
        } else {
            $data = $this->getRequestBody();
            $productId = isset($data['product_id']) ? (int)$data['product_id'] : null;
        }

        if (!$productId) {
            $this->error('Product ID is required', 400);
            return;
        }

        $productModel = $this->model('RevoProduct');
        $product = $productModel->getById($productId,(int)$user['sub']);
        if (!$product || (int)$product['company_id'] !== (int)$company['company']) {
            $this->error('Product not found', 404);
            return;
        }

        $formModel = $this->model('RevoForm');
        $forms = $formModel->getAllByProduct($productId);

        $this->success($forms, 'Forms retrieved successfully');
    }

    /**
     * GET /forms/{id} - get a single form
     */
    public function show($id = null, $type = null)
    {
        $id = $id ?? ($this->params['id'] ?? null);
        // $type = $type ?? ($this->params['type'] ?? null);
        // $typeKey = strtolower((string)$type);
        // $formType = FORM_TYPES[$typeKey] ?? null;
        // if ($formType === null) {
        //     $this->error('Invalid form type. Allowed: ' . implode(', ', array_keys(FORM_TYPES)), 400);
        //     return;
        // }
        if (!$id) {
            $this->error('Form ID is required', 400);
            return;
        }

        $user = $this->requireAuth();
        if (!$user) {
            return;
        }

        $formModel = $this->model('RevoForm');
        $form = $formModel->getById((int)$id, (int)$user['sub']);
        if (!$form) {
            $this->notFound('Form not found');
            return;
        }

        $this->success($form, 'Form retrieved successfully');
    }


    

    /**
     * POST /forms - create a new form
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
            $this->error('You are not authorized to create a form.', 401);
            return;
        }

        $companyModel = $this->model('RevoCompany');
        $company = $companyModel->getByUserId((int)$user['sub']);
        if (!$company) {
            $this->error('Company not found', 404);
            return;
        }

        $data = $this->getRequestBody();
        $data['product_id'] = isset($data['product_id']) ? (int)$data['product_id'] : 0;
        $data['status'] = isset($data['status']) ? (int)$data['status'] : 0;
        $data['created_by'] = (int)$user['sub'];
        $data['updated_by'] = (int)$user['sub'];

        $productModel = $this->model('RevoProduct');
        $product = $productModel->getById((int)$data['product_id'],(int)$user['sub']);
        if (!$product || (int)$product['company_id'] !== (int)$company['company']) {
            $this->error('Product not found', 404);
            return;
        }

        $formModel = $this->model('RevoForm');
        $result = $formModel->create($data);

        if ($result['status'] && $result['error'] === 201) {
            $response = [
                'id' => $result['id'],
                'product_id' => (int)$data['product_id'],
                'form_name' => (string)$data['form_name'],
                'form_json' => (string)$data['form_json'],
                'status' => (int)$data['status'],
                'created_by' => (int)$data['created_by'],
            ];
            $this->success($response, 'Form created successfully', 201);
        } elseif ($result['error'] === 400) {
            $this->error($result['message'] ?? 'Invalid data for form', 400);
        } elseif ($result['error'] === 409) {
            $this->error('Form already exists', 409);
        } else {
            $this->error($result['message'] ?? 'Failed to create form', 500);
        }
    }

    /**
     * POST /forms/update - update form
     */
    public function update()
    {
        if (!in_array($this->getMethod(), ['PUT', 'PATCH', 'POST'], true)) {
            $this->error('Method not allowed', 405);
            return;
        }

        $user = $this->requireAuth();
        if(!$user){
            $this->error('aAuthentication required. Please provide a valid token.', 401);
            return;
        }

        if (!isset($user['role']) || (int)$user['role'] !== 2) {
            $this->error('You are not authorized to update a form.', 401);
            return;
        }

        $companyModel = $this->model('RevoCompany');
        $company = $companyModel->getByUserId((int)$user['sub']);
        if (!$company) {
            $this->error('Company not found', 404);
            return;
        }

        $data = $this->getRequestBody();
        $id = isset($data['form_id']) ? (int)$data['form_id'] : 0;
        if (!$id) {
            $this->error('Form ID is required', 400);
            return;
        }

        $formModel = $this->model('RevoForm');
        $existing = $formModel->getById($id,(int)$user['sub']);
        if (!$existing) {
            $this->error('Form not found', 404);
            return;
        }

        // $targetProductId = isset($data['product_id']) ? (int)$data['product_id'] : (int)$existing['product_id'];
        // $productModel = $this->model('RevoProduct');
        // $product = $productModel->getById($targetProductId);
        // if (!$product || (int)$product['company_id'] !== (int)$company['company']) {
        //     $this->error('Product not found', 404);
        //     return;
        // }

        // if (isset($data['product_id'])) {
        //     $data['product_id'] = (int)$data['product_id'];
        // }
        if (isset($data['status'])) {
            $data['status'] = (int)$data['status'];
        }
        $data['updated_by'] = (int)$user['sub'];

        $updated = $formModel->update($id, $data);
        if ($updated) {
            $updatedForm = $formModel->getById($id,(int)$user['sub']);
            $this->success($updatedForm, 'Form updated successfully');
        } else {
            $this->error('Failed to update form', 500);
        }
    }

    /**
     * DELETE /forms/{id} - delete form
     */
    public function delete()
    {
        if ($this->getMethod() !== 'DELETE') {
            $this->error('Method not allowed', 405);
            return;
        }

        $id = $this->params['id'] ?? null;
        if (!$id) {
            $this->error('Form ID is required', 400);
            return;
        }

        $formModel = $this->model('RevoForm');
        $deleted = $formModel->delete((int)$id);
        if ($deleted) {
            $this->success(null, 'Form deleted successfully');
        } else {
            $this->error('Failed to delete form', 500);
        }
    }

    public function getFormByPromoteId()
    {
        $id = $this->params['id'] ?? null;
        if (!$id) {
            $this->error('Form ID is required', 400);
            return;
        }


       

        $token = $this->getAuthToken();        
        if($token!="undefined"){

            $user = $this->requireAuth();
            if (!$user || !isset($user['role']) || (int)$user['role'] !== 4) {
                return;
            }

            $formModel = $this->model('RevoForm');
            $form = $formModel->getByPromoteId((int)$id, (int)$user['sub']);
            if (!$form) {
                $this->error('Form not found', 404);
                return;
            }

            $this->success($form, 'Form retrieved successfully');
        }else{
            $formModel = $this->model('RevoForm');
            $form = $formModel->getByPromoteId((int)$id);
            if (!$form) {
                $this->error('Form not found', 404);
                return;
            }

            $this->success($form, 'Form retrieved successfully');

        }
    }

    // public function getRefundFormByPromoteId()
    // {
    //     $id = $this->params['id'] ?? null;
    //     if (!$id) {
    //         $this->error('Form ID is required', 400);
    //         return;
    //     }

    //     $user = $this->requireAuth();
    //     // if (!$user || !isset($user['role']) || (int)$user['role'] !== 4) {
    //     //     return;
    //     // }

    //     $formModel = $this->model('RevoForm');
    //     $form = $formModel->getb((int)$id,(int)$user['sub'],2);
    //     if (!$form) {
    //         $this->error('Form not found', 404);
    //         return;
    //     }

    //     $this->success($form, 'Form retrieved successfully');
    // }
}
