<?php

namespace App\Controllers;

use App\Core\Controller;

class RevoUserController extends Controller
{
    /**
     * GET /admin/users - list users with pagination (20 per page).
     * Optional query: page (int, default 1).
     */
    public function index()
    {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $page = max(1, $page);

        $userModel = $this->model('RevoUser');
        $result = $userModel->getPaginated($page, 20);

        $roleModel = $this->model('RevoUserRole');
        foreach ($result['data'] as &$user) {
            $user['roles'] = $roleModel->getRoles((int)$user['revo_user_id']);
        }
        unset($user);

        $this->success($result, 'Users retrieved successfully');
    }

    /**
     * GET /revo-users/{id} - get a single user
     */
    public function show($id = null)
    {
        $id = $id ?? ($this->params['id'] ?? null);

        if (!$id) {
            $this->error('User ID is required', 400);
            return;
        }

        $userModel = $this->model('RevoUser');
        $user = $userModel->getById((int)$id);

        if (!$user) {
            $this->notFound('User not found');
            return;
        }

        $this->success($user, 'User retrieved successfully');
    }

    /**
     * GET /revo-users/check?email={email}&phone={phone}
     * Check if a user exists by email and/or phone.
     * At least one of email or phone must be provided via query parameter.
     */
    /**
     * POST /revo-users - create new user
     *
     * Expected JSON body fields (matching DB columns):
     * - revo_user_name
     * - revo_user_email
     * - revo_user_phone
     * - revo_user_password (plain text; will be hashed)
     * - revo_user_default_role
     * - revo_user_created_source
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

        $userModel = $this->model('RevoUser');
        $result = $userModel->create($data);

        if ($result['status'] && $result['error'] === 201) {
            $response = [
                'revo_user_id' => $result['id'],
                'revo_user_name' => $data['revo_user_name'],
                'revo_user_email' => $data['revo_user_email'],
                'revo_user_phone' => $data['revo_user_phone'],
                'revo_user_default_role' => (int)$data['revo_user_default_role'],
                'revo_user_created_source' => $data['revo_user_created_source'],
                'created_by' => (int)$data['created_by'],
            ];
            $this->success($response, 'User created successfully', 201);
        } elseif ($result['error'] === 400) {
            $this->error($result['message'] ?? 'Invalid data for user', 400);
        } elseif ($result['error'] === 409) {
            $this->error('User with given email already exists', 409);
        } else {
            $this->error($result['message'] ?? 'Failed to create user', 500);
        }
    }

    public function getByEmail(string $email): array
    {
        $userModel = $this->model('RevoUser');
        return $userModel->getByEmail($email);
    }

    public function getByPhone(string $phone): array
    {
        $userModel = $this->model('RevoUser');
        return $userModel->getByPhone($phone);
    }

    public function getByEmailOrPhone(string $email, string $phone): array
    {
        $userModel = $this->model('RevoUser');
        return $userModel->getByEmailOrPhone($email, $phone);
    }


    public function verifyCredentials(string $password,string $emailormobile,string $val)
    {
        $userModel = $this->model('RevoUser');
        return $userModel->verifyCredentials($password, $emailormobile, $val);
    }

    /**
     * PUT/PATCH /revo-users/{id} - update user
     *
     * Body may include any updatable fields:
     * - revo_user_name
     * - revo_user_email
     * - revo_user_phone
     * - revo_user_password (plain text; will be hashed)
     * - revo_user_default_role
     * - revo_user_created_source
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
            $this->error('User ID is required', 400);
            return;
        }

        $data = $this->getRequestBody();

        $userModel = $this->model('RevoUser');
        $updated = $userModel->update((int)$id, $data);

        if ($updated) {
            $updatedUser = $userModel->getById((int)$id);
            $this->success($updatedUser, 'User updated successfully');
        } else {
            $this->error('Failed to update user', 500);
        }
    }

    /**
     * DELETE /revo-users/{id} - delete user
     */
    public function delete()
    {
        if ($this->getMethod() !== 'DELETE') {
            $this->error('Method not allowed', 405);
            return;
        }

        $id = $this->params['id'] ?? null;
        if (!$id) {
            $this->error('User ID is required', 400);
            return;
        }

        $userModel = $this->model('RevoUser');
        $deleted = $userModel->delete((int)$id);

        if ($deleted) {
            $this->success(null, 'User deleted successfully');
        } else {
            $this->error('Failed to delete user', 500);
        }
    }

    public function formimageupload()
    {
        if ($this->getMethod() !== 'POST') {
            $this->error('Method not allowed', 405);
            return;
        }

        $data = $this->getRequestBody();
        $user = $this->verifyAuth();
        if ($user) {
         
        
       
        // if (!isset($user['role']) || (int)$user['role'] !== 4) {
        //     $this->error('You are not authorized to update a brand.', 401);
        //     return;
        // }
        

            $userModel = $this->model('RevoUser');

            $user = $userModel->getById($user['sub']);
            if (!$user) {
                $this->error('User not found', 404);
                return;
            }
        }

        if($_FILES['image']) {
        
            $data = $this->getRequestBody();
            $image = $_FILES['image'];
            $image = handlePhotoUpload($image, FORM_IMAGE_UPLOAD_DIR);
            $this->success($image, 'Image uploaded successfully');
        }else{
            $this->error('Image is required', 400);
            return;
        }
    }
}

