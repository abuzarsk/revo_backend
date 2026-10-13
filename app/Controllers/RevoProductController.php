<?php

namespace App\Controllers;

use App\Core\Controller;

class RevoProductController extends Controller
{
    /**
     * GET /products/company - list products for logged-in company (paginated)
     *
     * Query parameters:
     * - page (int, default 1)
     * - per_page (int, default 1) — one product per page by default
     */
    public function byCompany()
    {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 1;

        $brand_id = isset($_GET['brand_id']) ? (int)$_GET['brand_id'] : null;
        $has_submission = isset($_GET['has_submission']) ? (int)$_GET['has_submission'] : null;
        $has_refund = isset($_GET['has_refund']) ? (int)$_GET['has_refund'] : null;

        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $user = $this->requireAuth();
        if (!$user) {
            return;
        }
        if (!isset($user['role']) || (int)$user['role'] !== 2) {
            $this->error('You are not authorized to get a products list.', 401);
            return;
        }
        $companyModel = $this->model('RevoCompany');
        $company = $companyModel->getByUserId((int)$user['sub']);
        if (!$company) {
            $this->error('Company not found', 404);
            return;
        }
        $companyId = $company['company'];

        if (!$companyId) {
            $this->error('Company ID is required', 400);
            return;
        }

        $productModel = $this->model('RevoProduct');
        $result = $productModel->getAllByCompanyPaginated((int)$companyId, $page, $perPage, $brand_id, $has_submission, $has_refund);

        $this->success($result, 'Products retrieved successfully');
    }



    /**
     * GET /revo-products/{id} - get a single product
     */
    public function show($id = null)
    {
        $id = $id ?? ($this->params['id'] ?? null);

        if (!$id) {
            $this->error('Product ID is required', 400);
            return;
        }

        $productModel = $this->model('RevoProduct');
        $product = $productModel->getById((int)$id);

        if (!$product) {
            $this->notFound('Product not found');
            return;
        }

        $this->success($product, 'Product retrieved successfully');
    }

    /**
     * POST /revo-products - create new product
     *
     * Expected JSON body fields:
     * - company_id
     * - brand_id
     * - name
     * - photo
     * - order_limit
     * - is_multiple_submission_by_one
     * - max_submission_by_one
     * - default_completed_order
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
            $this->error('You are not authorized to create a product.', 401);
            return;
        }
        $companyModel = $this->model('RevoCompany');
        $company = $companyModel->getByUserId((int)$user['sub']);
        if (!$company) {
            $this->error('Company not found', 404);
            return;
        }


        $data = $_POST;//$this->getRequestBody();
        $data['company_id'] = (int)$company['company'];
        $data['created_by'] = (int)$user['sub'];

        //print_r($data);
        //exit();
        //echo PRODUCT_UPLOAD_DIR;
        //Product Image
        $photoFilename = '';
        if (!empty($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                        // Handle multipart/form-data
            $data['name'] = $_POST['name'] ?? '';
                        
            // Handle photo upload
            $uploadResult = handlePhotoUpload($_FILES['photo'],PRODUCT_UPLOAD_DIR);
            if ($uploadResult['success']) {
                $photoFilename = $uploadResult['filename'];
            } else {
                $this->error($uploadResult['message'], $uploadResult['code'] ?? 400);
                return;
            }
        } else {
                        // Handle JSON request
                       // $data = $this->getRequestBody();
                        
                        // If photo is sent as base64 in JSON
            if (!empty($data['photo']) && $data['photo']!='null' && is_string($data['photo'])) {
                $uploadResult = handleBase64Photo($data['photo'], PRODUCT_UPLOAD_DIR);
                if ($uploadResult['success']) {
                    $photoFilename = $uploadResult['filename'];
                } else {
                    $this->error($uploadResult['message'], $uploadResult['code'] ?? 400);
                    return;
                }
            }
        }
        /////////////

        $data['photo'] = $photoFilename;
        $data['company_id'] = (int)$data['company_id'];
        $data['brand_id'] = (int)$data['brand_id'];
        $data['name'] = $data['name'];
        $data['order_limit'] = (int)$data['order_limit'];
        $data['is_multiple_submission_by_one'] = (int)$data['is_multiple_submission_by_one'];

        $data['max_submission_by_one'] = ($data['is_multiple_submission_by_one']==1) ? (int)$data['max_submission_by_one'] : 1;
        $data['default_completed_order'] = (isset($data['default_completed_order'])) ? (int)$data['default_completed_order'] : 0;
        $data['googlesheet_url'] = isset($data['googlesheet_url']) ? trim((string)$data['googlesheet_url']) : '';
        $data['updated_by'] = (int)$user['sub'];
        $data['created_by'] = (int)$user['sub'];
        $data['updated_on'] = date('Y-m-d H:i:s');
        $data['created_on'] = date('Y-m-d H:i:s');

        // print_r($data);
        // print_r($photoFilename);
        // print_r($_FILES);
        // exit();
        $productModel = $this->model('RevoProduct');
        $result = $productModel->create($data);

        if ($result['status'] && $result['error'] === 201) {



            $response = [
                'id' => $result['id'],
                'company_id' => (int)$data['company_id'],
                'brand_id' => (int)$data['brand_id'],
                'name' => $data['name'],
                'photo' => $photoFilename,//data['photo'],
                'order_limit' => (int)$data['order_limit'],
                'is_multiple_submission_by_one' => (int)$data['is_multiple_submission_by_one'],
                'max_submission_by_one' => (int)$data['max_submission_by_one'],
                'default_completed_order' => (int)$data['default_completed_order'],
                'created_by' => (int)$data['created_by'],
            ];
            $this->success($response, 'Product created successfully', 201);
        } elseif ($result['error'] === 400) {
            $this->error($result['message'] ?? 'Invalid data for product', 400);
        } elseif ($result['error'] === 409) {
            $this->error('Product already exists', 409);
        } else {
            $this->error($result['message'] ?? 'Failed to create product', 500);
        }
    }

    /**
     * PUT/PATCH /revo-products/{id} - update product
     *
     * Body may include any updatable fields:
     * - company_id
     * - brand_id
     * - name
     * - photo
     * - order_limit
     * - is_multiple_submission_by_one
     * - max_submission_by_one
     * - default_completed_order
     * - updated_by
     */
    public function update()
    {

        $user = $this->requireAuth();
        if (!$user) {
            return;
        }
        if (!isset($user['role']) || (int)$user['role'] !== 2) {
            $this->error('You are not authorized to update a product.', 401);
            return;
        }
        $companyModel = $this->model('RevoCompany');
        $company = $companyModel->getByUserId((int)$user['sub']);
        if (!$company) {
            $this->error('Company not found', 404);
            return;
        }
        $companyId = $company['company'];
        if (!$companyId) {
            $this->error('Company ID is required', 400);
            return;
        }

        if (!in_array($this->getMethod(), ['PUT', 'PATCH','POST'], true)) {
            $this->error('Method not allowed', 405);
            return;
        }

        $id = $this->params['id'] ?? null;
        if (!$id) {
            $this->error('Product ID is required', 400);
            return;
        }

        $data = $_POST;//$this->getRequestBody();
        $data['company_id'] = (int)$companyId;
        $data['updated_by'] = (int)$user['sub'];
        $data['updated_on'] = date('Y-m-d H:i:s');


        $photoFilename = '';
        if (!empty($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                        // Handle multipart/form-data
            $data['name'] = $_POST['name'] ?? '';
                        
            // Handle photo upload
            $uploadResult = handlePhotoUpload($_FILES['photo'],PRODUCT_UPLOAD_DIR);
            if ($uploadResult['success']) {
                $photoFilename = $uploadResult['filename'];
            } else {
                $this->error($uploadResult['message'], $uploadResult['code'] ?? 400);
                return;
            }
        } else {
            $photoFilename = $data['photo'];
                        // Handle JSON request
                       // $data = $this->getRequestBody();
                        
                        // If photo is sent as base64 in JSON
            // if (!empty($data['photo']) && $data['photo']!='null' && is_string($data['photo'])) {
            //     $uploadResult = handleBase64Photo($data['photo'], PRODUCT_UPLOAD_DIR);
            //     if ($uploadResult['success']) {
            //         $photoFilename = $uploadResult['filename'];
            //     } else {
            //         $this->error($uploadResult['message'], $uploadResult['code'] ?? 400);
            //         return;
            //     }
            // }
        }

        $data['photo'] = $photoFilename;
        $data['id'] = (int)$id;
        $data['company_id'] = (int)$data['company_id'];
        $data['brand_id'] = (int)$data['brand_id'];
        $data['name'] = $data['name'];
        $data['order_limit'] = (int)$data['order_limit'];
        $data['is_multiple_submission_by_one'] = (int)$data['is_multiple_submission_by_one'];
        $data['max_submission_by_one'] = (int)$data['max_submission_by_one'];
        $data['default_completed_order'] = (int)$data['default_completed_order'];
        $data['updated_by'] = (int)$user['sub'];
        $data['created_by'] = (int)$user['sub'];
        $data['updated_on'] = date('Y-m-d H:i:s');
        $data['created_on'] = date('Y-m-d H:i:s');


        $productModel = $this->model('RevoProduct');
        $updated = $productModel->update((int)$id, $data);

        if ($updated) {
            $updatedProduct = $productModel->getById((int)$id);
            $this->success($updatedProduct, 'Product updated successfully');
        } else {
            $this->error('Failed to update product', 500);
        }
    }

    /**
     * DELETE /revo-products/{id} - delete product
     */
    public function delete()
    {
        if ($this->getMethod() !== 'DELETE') {
            $this->error('Method not allowed', 405);
            return;
        }

        $id = $this->params['id'] ?? null;
        if (!$id) {
            $this->error('Product ID is required', 400);
            return;
        }

        $productModel = $this->model('RevoProduct');
        $deleted = $productModel->delete((int)$id);

        if ($deleted) {
            $this->success(null, 'Product deleted successfully');
        } else {
            $this->error('Failed to delete product', 500);
        }
    }

    public function promoteDemoteProduct()
    {
        
        $user = $this->requireAuth();
        if (!$user) {
            return;
        }
        if (!isset($user['role']) || (int)$user['role'] !== 2) {
            $this->error('You are not authorized to promote a product.', 401);
            return;
        }

        $data = $this->getRequestBody();
        //print_r($data);
        $id = $data['id'] ?? null;
        if (!$id) {
            $this->error('Product ID is required', 400);
            return;
        }
        $status = $data['status'];
        if ($status===null) {
            $this->error('Status is required', 400);
            return;
        }

        $companyModel = $this->model('RevoCompany');
        $company = $companyModel->getByUserId((int)$user['sub']);
        //print_r($company);

        if (!$company) {
            $this->error('Company not found', 404);
            return;
        }
        $companyId = $company['company'];
        if (!$companyId) {
            $this->error('Company ID is required', 400);
        }
        
        $productModel = $this->model('RevoProduct');
        $product = $productModel->promoteDemoteProduct((int)$id,(int)$companyId,(int)$status);
        if (!$product) {
            $this->error('Product not found', 404);
            return;
        }
        $this->success($product, 'Product promoted successfully');
        
    }

    /**
     * GET /mediator/products - list products for logged-in mediator (paginated)
     *
     * Query parameters:
     * - page (int, default 1)
     * - per_page (int, default 1)
     */
    public function getMediatorProducts()
    {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 1;
        $brand_id = isset($_GET['brand_id']) ? (int)$_GET['brand_id'] : null;
        $has_submission = isset($_GET['has_submission']) ? (int)$_GET['has_submission'] : null;
        $has_refund = isset($_GET['has_refund']) ? (int)$_GET['has_refund'] : null;
        
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $user = $this->requireAuth();
        if (!$user) {
            return;
        }
        if (!isset($user['role']) || (int)$user['role'] !== 3) {
            $this->error('You are not authorized to get a products list.', 401);
            return;
        }
        $mediatorModel = $this->model('RevoMediator');
        $mediator = $mediatorModel->getByUserId((int)$user['sub']);
        if (!$mediator) {
            $this->error('Mediator not found', 404);
            return;
        }
        $mediatorId = $mediator['id'];
        if (!$mediatorId) {
            $this->error('Mediator ID is required', 400);
            return;
        }
        $productModel = $this->model('RevoProduct');
        $result = $productModel->getMediatorProductsPaginated((int)$mediatorId, $page, $perPage, $brand_id, $has_submission, $has_refund);
        $this->success($result, 'Products retrieved successfully');
    }

    public function getPromoteIdByProductId()
    {
        $id = $this->params['id'] ?? null;
        if (!$id) {
            $this->error('Product ID is required', 400);
            return;
        }
        $user = $this->requireAuth();
        if (!$user) {
            return;
        }
        $productModel = $this->model('RevoProduct');
        $promoteId = $productModel->getPromoteIdByProductId((int)$id,$user['sub'],$user['role']);
        if (!$promoteId) {
            $this->error('Product not found', 404);
            return;
        }
        $this->success($promoteId, 'Product retrieved successfully');
    }

    public function getMediatorProductById()
    {
       
        $id = $this->params['id'] ?? null;
        if (!$id) {
            $this->error('Product ID is required', 400);
            return;
        }
        $user = $this->requireAuth();
        if (!$user) {
            return;
        }
        if (!isset($user['role']) || (int)$user['role'] !==3) {
            $this->error('You are not authorized to get a product.', 401);
            return;
        }

        $mediatorModel = $this->model('RevoMediator');
        $mediator = $mediatorModel->getByUserId((int)$user['sub']);
        if (!$mediator) {
            $this->error('Mediator not found', 404);
            return;
        }
        $mediatorId = $mediator['id'];
        if (!$mediatorId) {
            $this->error('Mediator ID is required', 400);
            return;
        }

        $productModel = $this->model('RevoProduct');
        $product = $productModel->getMediatorByPromoteId((int)$id,(int)$mediatorId);
        if (!$product) {
            $this->error('Product not found', 404);
            return;
        }
        $this->success($product, 'Product retrieved successfully');
    }

    /**
     * GET /user/products - list products for logged-in user (paginated)
     *
     * Query parameters:
     * - page (int, default 1)
     * - per_page (int, default 1)
     */
    public function getUserProducts()
    {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 1;
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $user = $this->requireAuth();
        if (!$user) {
            return;
        }
        if (!isset($user['role']) || (int)$user['role'] !== 4) {
            $this->error('You are not authorized to get a products list.', 401);
            return;
        }

        $productModel = $this->model('RevoProduct');
        $result = $productModel->getUserProductsPaginated((int)$user['sub'], $page, $perPage);

        if ($result === false) {
            $this->error('Unable to resolve connection for products', 404);
            return;
        }

        $this->success($result, 'Products retrieved successfully');
    }
    

    public function getProductByPromoteId()
    {
        $id = $this->params['id'] ?? null;
        if (!$id) {
            $this->error('Product ID is required', 400);
            return;
        }
        $user = $this->requireAuth();
        if (!$user) {
            return;
        }
        if (!isset($user['role']) || (int)$user['role'] !== 4) {
            $this->error('You are not authorized to get a product.', 401);
            return;
        }
        $productModel = $this->model('RevoProduct');
        $product = $productModel->getByPromoteId((int)$id);
        if (!$product) {
            $this->error('Product not found', 404);
            return;
        }
        $this->success($product, 'Product retrieved successfully');
    }

    public function dashboard()
    {

        $user = $this->requireAuth();
        if (!$user) {
            $this->error('Authentication required. Please provide a valid token.', 401);
            return;
        }

        $userId = $user['sub'];
        $userRole = $user['role'];
        // if ($userRole = 1) {
        //     $this->error('You are not authorized to get a dashboard.', 401);
        //     return;
        // }

        $userRoleModel = $this->model('RevoUserRole');
        $userRoleArr = $userRoleModel->getRoles((int)$userId);
        if (!$userRoleArr) {
            $this->error('User role not found', 404);
            return;
        }

        if (!in_array($userRole, $userRoleArr)) {
            $this->error('You are not authorized to get a dashboard.', 401);
            return;
        }

        $productModel = $this->model('RevoProduct');
        $products = $productModel->getDashboard((int)$userId,$userRole);
        if (!$products) {
            $this->error('Products not found', 404);
            return;
        }
        $this->success($products, 'Products retrieved successfully');
        
        
    }

}

