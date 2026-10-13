<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Jwt;
use Google\Client as GoogleClient;
use Google\Service\Oauth2;

class AuthController extends Controller
{

    public function signup()
    {
        if ($this->getMethod() !== 'POST') {
            $this->error('Method not allowed', 405);
            return;
        }

        $data = $_POST;//$this->getRequestBody();

        // print_r($data);
        // print_r($_POST);
        // print_r($_FILES);

        if((isset($data['role']) && $data['role'] == 2)){
            if (empty($data['name']) || empty($data['email']) || empty($data['phone']) || empty($data['password']) || empty($data['alias']) || empty($data['size']) || empty($data['group_link']) || empty($data['slot_in_a_month'])) {
                $this->error('Name, email, phone, password, alias, size, group_link and slot_in_a_month are required', 400);
                return;
            }
        }else if( (isset($data['role']) &&$data['role'] == 3)){
            if (empty($data['name']) || empty($data['email']) || empty($data['phone']) || empty($data['password']) || empty($data['alias']) || empty($data['size']) || empty($data['group_link']) || empty($data['slot_in_a_month']) ) {
                $this->error('Name, email, phone, password, alias, size, group_link and slot_in_a_monthare required', 400);
                return;
            }
        }else if( (isset($data['role']) &&$data['role'] == 4)){
            if (empty($data['name']) || empty($data['email']) || empty($data['phone']) || empty($data['password'])) {
                $this->error('Name, email, phone and password are required', 400);
                return;
            }
        }


        $userModel = $this->model('RevoUser');
        $checkifUserExists = $userModel->checkifUserExists($data['email'], $data['phone']);
        if ($checkifUserExists) {
            $this->error('User already exists', 400);
            return;
        }
        $sendData = [];
        $sendData['revo_user_name'] = $data['name'];
        $sendData['revo_user_email'] = $data['email'];
        $sendData['revo_user_phone'] = $data['phone'];
        $sendData['revo_user_password'] = $data['password'];
        $sendData['revo_user_default_role'] = $data['role'] ?? 4;
        $sendData['revo_user_created_source'] = 'web';
        $sendData['created_by'] = date('Y');//$user['id'];
        $sendData['updated_by'] = date('Y');
        $user = $userModel->create($sendData);
        //print_r($user);
        if ($user['status'] && $user['error'] === 201) {

            $role = 4;
            $roleModel = $this->model('RevoUserRole');
            $result =$roleModel->create([
                'revo_user_id' => $user['id'],
                'revo_role_id' => $role,
                'created_by' => $user['id'],
            ]);


            if((isset($data['role']) && $data['role'] == 2)){
                //Company
                $role = 2;
                $roleRequestModel = $this->model('RevoUserRoleRequest');
                $result = $roleRequestModel->create([
                    'revo_user_id' => $user['id'],
                    'revo_role_id' => $role,
                    'created_by' => $user['id'],
                ]);
                

                $photoFilename = '';
                if (!empty($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                    // Handle multipart/form-data
                    $data['name'] = $_POST['name'] ?? '';
                    
                    // Handle photo upload
                    $uploadResult = handlePhotoUpload($_FILES['logo'],MEDIATOR_UPLOAD_DIR);
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
                    if (!empty($data['logo']) && $data['logo']!='null' && is_string($data['logo'])) {
                        $uploadResult = handleBase64Photo($data['logo'], MEDIATOR_UPLOAD_DIR);
                        if ($uploadResult['success']) {
                            $photoFilename = $uploadResult['filename'];
                        } else {
                            $this->error($uploadResult['message'], $uploadResult['code'] ?? 400);
                            return;
                        }
                    }
                }

                //print_r($data);
                
                $companyModel = $this->model('RevoCompany');
                $result = $companyModel->create([
                    'revo_user_name' => $data['name'],
                    'revo_user_id' => $user['id'],
                    'logo' => $photoFilename,
                    'alias' => $data['alias'],
                    'company_size' => $data['size'],
                    'group_link' => $data['group_link'],
                    'status' => 2,
                    'slot_in_a_month' => $data['slot_in_a_month'],
                    'description' => $data['description'],
                    'created_by' => $user['id'],
                    'updated_by' => $user['id'],
                ]);

                // $roleModel->create([
                //     'revo_user_id' => $user['id'],
                //     'revo_role_id' => $role,
                //     'created_by' => $user['id'],
                // ]);
    
            }else if( (isset($data['role']) &&$data['role'] == 3)){
                //Mediator
                $role = 3;
                $result =$roleModel->create([
                    'revo_user_id' => $user['id'],
                    'revo_role_id' => $role,
                    'created_by' => $user['id'],
                ]);

                $photoFilename = '';
                if (!empty($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                    // Handle multipart/form-data
                    $data['name'] = $_POST['name'] ?? '';
                    
                    // Handle photo upload
                    $uploadResult = handlePhotoUpload($_FILES['profile_picture'],MEDIATOR_UPLOAD_DIR);
                    if ($uploadResult['success']) {
                        $photoFilename = $uploadResult['filename'];
                    } else {
                        $this->error($uploadResult['message'], $uploadResult['code'] ?? 400);
                        return;
                    }
                } else {
                    // Handle JSON request
                    //$data = $this->getRequestBody();
                    
                    // If photo is sent as base64 in JSON
                    if (!empty($data['profile_picture']) && $data['profile_picture']!='null' && is_string($data['profile_picture'])) {
                        $uploadResult = handleBase64Photo($data['profile_picture'], MEDIATOR_UPLOAD_DIR);
                        if ($uploadResult['success']) {
                            $photoFilename = $uploadResult['filename'];
                        } else {
                            $this->error($uploadResult['message'], $uploadResult['code'] ?? 400);
                            return;
                        }
                    }
                }    

                $mediatorModel = $this->model('RevoMediator');
                $result = $mediatorModel->create([
                    'mediator_name' => $data['name'],
                    'revo_user_id' => $user['id'],
                    'profile_pic' => $photoFilename,//$data['profile_picture'],
                    'alias' => $data['alias'],
                    'size' => $data['size'],
                    'group_link' => $data['group_link'],
                    'slot_in_a_month' => $data['slot_in_a_month'],
                    'description' => $data['description'],
                    'created_by' => $user['id'],
                    'updated_by' => $user['id'],
                ]);
            }

            $response = [
                'user_id' => $user['id'],
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
            ];

            if($result['status'] && $result['error'] === 201){  
                $this->success($response, 'User created successfully', 201);
            }else{
                $this->error($result['message'] ?? 'Failed to create user', 500);
            }
        }else{
            $this->error($user['message'] ?? 'Failed to create user', 500);
        }
    }

    public function login()
    {
        if ($this->getMethod() !== 'POST') {
            $this->error('Method not allowed', 405);
            return;
        }
        
        $data = $this->getRequestBody();

        // if (empty($data['email']) || empty($data['phone']) || empty($data['password'])) {
        //     $this->error('Email, phone and password are required', 400);
        //     return;
        // }

        $userModel = $this->model('RevoUser');
        $user = $userModel->verifyCredentials($data['password'],$data['emailormobile'],$data['value']);

        if (!$user) {
            $this->error('Invalid credentials', 401);
            return;
        }

        $roleModel = $this->model('RevoUserRole');
        $roles = $roleModel->getRoles($user['revo_user_id']);


        $claims = [
            'sub' => $user['revo_user_id'],
            'email' => $user['revo_user_email'],
            'name' => $user['revo_user_name'],
            'phone' => $user['revo_user_phone'],
            'role' => $user['revo_user_default_role'],
        ];
        $token = Jwt::encode($claims);

        $response = [
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => JWT_EXPIRES_IN,
            'user' => [
                'id' => $user['revo_user_id'],
                'name' => $user['revo_user_name'],
                'email' => $user['revo_user_email'],
                'phone' => $user['revo_user_phone'],
                'roles' => $roles,
                'default_role' => $claims['role'],
            ],
        ];

        $this->success($response, 'Login successful', 200);
        
    }

    public function  changeRole(){
        if($this->getMethod() !== 'POST'){
            $this->error('Method not allowed', 405);
            return;
        }
        $data = $this->getRequestBody();
        $user = $this->requireAuth();
        if (!$user) {
            return;
        }
        $role = $data['role'] ?? null;
        if (!$role) {
            $this->error('Role is required', 400);
            return;
        }
        //print_r($user);
        $roleModel = $this->model('RevoUserRole');
        $roles = $roleModel->getRoles($user['sub']);

        $userModel = $this->model('RevoUser');
        $userDetails = $userModel->getById($user['sub']);
        if (!$userDetails) {
            $this->error('User not found', 404);
            return;
        }
        //print_r($userDetails);

        $claims = [
            'sub' => $user['sub'],
            'email' => $userDetails['revo_user_email'],
            'name' => $userDetails['revo_user_name'],
            'phone' => $userDetails['revo_user_phone'],
            'role' => $role,//user['revo_user_default_role'],
        ];
        $token = Jwt::encode($claims);

        $response = [
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => JWT_EXPIRES_IN,
            'user' => [
                'id' => $user['sub'],
                'name' => $userDetails['revo_user_name'],
                'email' => $userDetails['revo_user_email'],
                'phone' => $userDetails['revo_user_phone'],
                'roles' => $roles,
                'default_role' => $claims['role'],
            ],
        ];

        $this->success($response, 'Login successful', 200);

        
    }


    public function googleCallback()
    {
        if ($this->getMethod() !== 'GET') {
            $this->error('Method not allowed', 405);
            return;
        }
        
        $token = $this->getAuthToken();

        if (!$token) {
            $this->error('Authorization token missing in headers', 401);
            return;
        }

        $client = new GoogleClient([
            'client_id' => GOOGLE_CLIENT_ID
        ]);
        $payload = $client->verifyIdToken($token);
        if (!$payload || !is_array($payload)) {
            $this->error('Invalid Google token', 401);
            return;
        }

        $email = $payload['email'] ?? '';
        $name = $payload['name'] ?? '';
        $picture = $payload['picture'] ?? '';
        $sub = $payload['sub'] ?? '';

        if (!$email) {
            $this->error('Unable to retrieve email from Google account', 400);
            return;
        }


        $userModel = $this->model('RevoUser');
        $user = $userModel->getByEmail($email);
        //print_r($user);
        if (empty($user)) {
            $timestamp = time();

            //SIGNUP WITH OUT PASSWORD AND SOURCE WILL BE GOOGLE
            $userModel = $this->model('RevoUser');
            $user = $userModel->create([
                'revo_user_name' => $name,
                'revo_user_email' => $email,
                'revo_user_phone' => '',
                'revo_user_password' => $timestamp,
                'revo_user_created_source' => 'google',
                'revo_user_default_role' => 4,
                'created_by' => date('Y'),
                'updated_by' => date('Y'),
            ]);
            //print_r($user);

            $claims = [
                'sub' => $user['id'],
                'email' => $email,
                'name' => $name,
                'phone' => '',
                'role' => 4,
            ];
            $token = Jwt::encode($claims);
    
            $response = [
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => JWT_EXPIRES_IN,
                'user' => [
                    'id' => $user['id'],
                    'name' => $name,
                    'email' => $email,
                    'phone' => '',
                    'roles' => [$claims['role']],
                    'default_role' => $claims['role'],
                ],
            ];
            $this->success($response, 'Login successful', 200);
            return;
    
            //return $user;
            
        }else{
            $claims = [
                'sub' => $user['revo_user_id'],
                'email' => $user['revo_user_email'],
                'name' => $user['revo_user_name'],
                'phone' => $user['revo_user_phone'],
                'role' => $user['revo_user_default_role'],
            ];
            $token = Jwt::encode($claims);
    
            $response = [
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => JWT_EXPIRES_IN,
                'user' => [
                    'id' => $user['revo_user_id'],
                    'name' => $user['revo_user_name'],
                    'email' => $user['revo_user_email'],
                    'phone' => $user['revo_user_phone'],
                    'roles' => [$claims['role']],
                    'default_role' => $claims['role'],
                ],
            ];
    
            $this->success($response, 'Login successful', 200);
            return;
        }
        
        
        
    }


    public function checkEmail()
    {
        if($this->getMethod() !== 'POST'){
            $this->error('Method not allowed', 405);
            return;
        }
        $data = $this->getRequestBody();
        if($data==null){
            $data = json_decode(file_get_contents('php://input'), true);
        }
        $email = $data['email'] ?? null;
        $userModel = $this->model('RevoUser');
        $user = $userModel->getByEmail($email);
        $json = [];
        if($user){
            $json['status'] = 1;
            $json['message'] = 'Email already exists';
            $this->success($json, 'Email already exists', 200);
            return;
        }
        $json['status'] = 2;
        $json['message'] = 'Email already exists';
        $this->success($json, 'Email o', 200);
        return;
    }



    public function productForm()
    {
        if ($this->getMethod() !== 'POST') {
            $this->error('Method not allowed', 405);
            return;
        }

        $data = $this->getRequestBody();
        if ($data == null) {
            $data = json_decode(file_get_contents('php://input'), true);
        }

        if (!is_array($data)) {
            $this->error('Invalid request body', 400);
            return;
        }

        $loginDetails = $data['login_details'] ?? null;
        if (!is_array($loginDetails) || empty($loginDetails['auth_type'])) {
            $this->error('login_details are required', 400);
            return;
        }

        if (empty($data['form_id']) || empty($data['promote_id']) || !isset($data['submission'])) {
            $this->error('form_id, promote_id and submission are required', 400);
            return;
        }

        $userModel = $this->model('RevoUser');
        $user_id = null;
        $authType = (string)$loginDetails['auth_type'];

        if ($authType === 'login') {
            $user = $userModel->verifyCredentials(
                (string)($loginDetails['password'] ?? ''),
                (string)($loginDetails['emailormobile'] ?? 'email'),
                (string)($loginDetails['value'] ?? '')
            );

            if (!$user) {
                $this->error('Invalid credentials', 401);
                return;
            }

            $user_id = (int)$user['revo_user_id'];
        } else if($authType !== 'already_logged_in') {
            $userData = [
                'revo_user_name' => $loginDetails['name'] ?? null,
                'revo_user_email' => $loginDetails['email'] ?? null,
                'revo_user_phone' => $loginDetails['phone'] ?? null,
                'revo_user_password' => $loginDetails['password'] ?? null,
                'revo_user_default_role' => (int)($loginDetails['role'] ?? 4),
                'revo_user_created_source' => 'product_form',
                'created_by' => -1,
                'updated_by' => -1,
            ];

            $user = $userModel->create($userData);
            if (!($user['status'] ?? false) || (int)($user['error'] ?? 0) !== 201) {
                $this->error($user['message'] ?? 'Failed to create user', 500);
                return;
            }

            $user_id = (int)$user['id'];

            $roleModel = $this->model('RevoUserRole');
            $roleModel->create([
                'revo_user_id' => $user_id,
                'revo_role_id' => 4,
                'created_by' => $user_id,
            ]);
        } else if($authType === 'already_logged_in') {

            $user = $this->requireAuth();
            if (!$user) {
                $this->error('User not found', 404);
                return;
            }
            $user_id = (int)$user['sub'];
        }

        $productPromoteModel = $this->model('RevoProductPromote');
        $productPromote = $productPromoteModel->getById((int)$data['promote_id']);
        if (!$productPromote) {
            $this->error('Promoter not found', 404);
            return;
        }

        $commonIdValue = $data['common_id'] ?? ($productPromote['common_id'] ?? null);
        if ($commonIdValue === null || $commonIdValue === '') {
            $this->error('Common ID is required', 400);
            return;
        }

        $commonIdModel = $this->model('RevoCommonId');
        $commonId = is_numeric($commonIdValue)
            ? ($commonIdModel->getByUniqueId((int)$commonIdValue) ?: $commonIdModel->getById((int)$commonIdValue))
            : null;

        if (!$commonId) {
            $this->error('Common ID not found', 404);
            return;
        }

        $userConnectedModel = $this->model('RevoConnect');
        if ((int)$commonId['company_id'] !== -1) {
            $alreadyConnected = $userConnectedModel->checkifConnected($user_id, 2, (int)$commonId['company_id']);
            if (!$alreadyConnected) {
                $userConnectedModel->create([
                    'user_id' => $user_id,
                    'company_id' => (int)$commonId['company_id'],
                    'mediator_id' => -1,
                    'status' => 1,
                    'note' => 'Connected from product form',
                    'created_by' => $user_id,
                    'updated_by' => $user_id,
                ]);
            }
        } elseif ((int)$commonId['mediator_id'] !== -1) {
            $alreadyConnected = $userConnectedModel->checkifConnected($user_id, 3, (int)$commonId['mediator_id']);
            if (!$alreadyConnected) {
                $userConnectedModel->create([
                    'user_id' => $user_id,
                    'company_id' => -1,
                    'mediator_id' => (int)$commonId['mediator_id'],
                    'status' => 1,
                    'note' => 'Connected from product form',
                    'created_by' => $user_id,
                    'updated_by' => $user_id,
                ]);

               
            }
        }

        $formData = [
            'form_id' => (int)$data['form_id'],
            'promote_id' => (int)$data['promote_id'],
            'submission' => is_string($data['submission']) ? $data['submission'] : json_encode($data['submission']),
            'user_id' => $user_id,
            'created_by' => $user_id,
            'updated_by' => $user_id,
        ];

        $formModel = $this->model('RevoForm');
        $form = $formModel->getById((int)$data['form_id'], $user_id);
        if (!$form) {
            $this->error('Form not found', 404);
            return;
        }

        $product = is_array($data['product'] ?? null) ? $data['product'] : [];
        $maxSubmissions = (int)(
            $product['product_max_submissions']
            ?? $product['max_submission_by_one']
            ?? $form['max_submission_by_one']
            ?? 1
        );
        if ($maxSubmissions < 1) {
            $maxSubmissions = 1;
        }

        $submissionModel = $this->model('RevoFormSubmission');
        $submissions = $submissionModel->getByFormIdAndUserId((int)$data['form_id'], $user_id) ?: [];
        
        if (count($submissions) >= $maxSubmissions) {
            $this->error('You have reached the maximum number of submissions', 400);
            return;
        }

        $result = $submissionModel->create($formData);
        if (!($result['status'] ?? false)) {
            $this->error($result['message'] ?? 'Failed to create submission', (int)($result['error'] ?? 500));
            return;
        }

        $this->success($result, 'Successfully Submitted', 200);
    }

    function photoUpload($file, $uploadDir)
    {
                        // Check if request is multipart/form-data (file upload)
                        if (!empty($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                            // Handle multipart/form-data
                            $data['name'] = $_POST['name'] ?? '';
                            
                            // Handle photo upload
                            $uploadResult = handlePhotoUpload($_FILES['photo'],$uploadDir);
                            if ($uploadResult['success']) {
                                $photoFilename = $uploadResult['filename'];
                            } else {
                                $this->error($uploadResult['message'], $uploadResult['code'] ?? 400);
                                return;
                            }
                        } else {
                            // Handle JSON request
                            $data = $this->getRequestBody();
                            
                            // If photo is sent as base64 in JSON
                            if (!empty($data['photo']) && is_string($data['photo'])) {
                                $uploadResult = handleBase64Photo($data['photo'],$uploadDir);
                                if ($uploadResult['success']) {
                                    $photoFilename = $uploadResult['filename'];
                                } else {
                                    $this->error($uploadResult['message'], $uploadResult['code'] ?? 400);
                                    return;
                                }
                            }
                        }                    
                return $photoFilename;
    }

    /*public function login()
    {
        if ($this->getMethod() !== 'POST') {
            $this->error('Method not allowed', 405);
            return;
        }

        $data = $this->getRequestBody();

        if (empty($data['val']) || empty($data['password'])) {
            $this->error('Email or Phone and password are required', 400);
            return;
        }

        $userModel = $this->model('User');
        $user = $userModel->verifyCredentials($data['password'],$data['emailormobile'],$data['val']);
        
        $isCompany=false;
        $isMediator=false;
        $isAdmin=false;
        if (!$user) {
            
            $companyModel = $this->model('Company');
            $user = $companyModel->verifyCredentials($data['password'],$data['emailormobile'],$data['val']);
            
            if (!$user) {
                $isMediator=false;
                if (!$user) {
                    
                    $mediatorModel = $this->model('Mediator');
                    $user = $mediatorModel->verifyCredentials($data['password'],$data['emailormobile'],$data['val']);
                    
                    if (!$user) {
                        
                        $adminModel = $this->model('Admin');
                        $user = $adminModel->verifyCredentials($data['password'],$data['emailormobile'],$data['val']);
                        
                        if (!$user) {
                            $this->error('Invalid credentials', 401);
                            return;
    
                        }else{
                            $isAdmin=true;
                        }
                    }else{
                        $isMediator=true;
                    }
                }
            }else{
                $isCompany=true;
            }
        }

       

        $role=3;
        if($isCompany==true){
           $role= 1;
        }else if($isMediator==true){
           $role= 2;
        }else if($isAdmin==true){
           $role= 0;
        }


        $claims = [
            'sub' => ($isCompany==true) ? $user['company_id'] : (($isMediator==true) ? $user['mediator_id'] : (($isAdmin==true) ? $user['id'] : $user['user_id'])),
            'email' => $user['email'],
            'name' => $user['name'] ?? null,
            'role' => $role
        ];

        $token = Jwt::encode($claims);

        $response = [
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => JWT_EXPIRES_IN,
            'user' => [
                'id' => ($isCompany==true) ?  $user['company_id'] : (($isMediator==true) ? $user['mediator_id'] : (($isAdmin==true) ? $user['id'] : $user['user_id'])) ,
                'name' => $user['name'] ?? null,
                'email' => $user['email'],
                'role' => $role,
            ],
        ];

        $this->success($response, 'Login successful');
    }

    public function googleCallback()
    {

        if ($this->getMethod() !== 'GET') {
            $this->error('Method not allowed', 405);
            return;
        }

        $token = $this->getAuthToken();

        if (!$token) {
            $this->error('Authorization token missing in headers', 401);
            return;
        }

        // $decoded = Jwt::decode($token);

        // if (!$decoded) {
        //     $this->error('Invalid token', 401);
        //     return;
        // }
        $client = new GoogleClient([
            'client_id' => GOOGLE_CLIENT_ID
        ]);
        $payload = $client->verifyIdToken($token);
        $email = $payload['email'];
        $name = $payload['name'];
        $picture = $payload['picture'];
        $sub = $payload['sub'];
        //print_r($payload);
        //exit;

        try {
        
            if (!$email) {
                $this->error('Unable to retrieve email from Google account', 400);
                return;
            }

            $userModel = $this->model('User');
            $user = $userModel->getByEmail($email);

            $isCompany = false;
            $isMediator = false;
            $isAdmin = false;

            if (!$user) {
                $companyModel = $this->model('Company');
                $user = $companyModel->getByEmail($email);

                if (!$user) {
                    $mediatorModel = $this->model('Mediator');
                    $user = $mediatorModel->getByEmail($email);

                    if (!$user) {
                        $adminModel = $this->model('Admin');
                        $user = $adminModel->getByEmail($email);

                        if (!$user) {
                            //create Signup logic which stores in temp table
                            $tempUserModel = $this->model('TempUser');
                            $tempUser = $tempUserModel->create([
                                'email' => $email,
                                'name' => $name,
                                'profile_pic' => $picture,
                                'token' => $sub,
                                'created_by' => 0
                            ]);
                            //$this->error('No local account is linked with this Google email', 404);
                            $this->success($tempUser, 'Signup successful');
                            return;
                        } else {
                            $isAdmin = true;
                        }
                    } else {
                        $isMediator = true;
                    }
                } else {
                    $isCompany = true;
                }
            }

            $role = 3;
            if ($isCompany === true) {
                $role = 1;
            } elseif ($isMediator === true) {
                $role = 2;
            } elseif ($isAdmin === true) {
                $role = 0;
            }

            $claims = [
                'sub' => ($isCompany === true) ? $user['company_id'] : (($isMediator === true) ? $user['mediator_id'] : (($isAdmin === true) ? $user['id'] : $user['user_id'])),
                'email' => $email,
                'name' => $user['name'] ?? $name ?? null,
                'role' => $role
            ];

            $jwtToken = Jwt::encode($claims);

            $response = [
                'token' => $jwtToken,
                'token_type' => 'Bearer',
                'expires_in' => JWT_EXPIRES_IN,
                'user' => [
                    'id' => ($isCompany === true) ? $user['company_id'] : (($isMediator === true) ? $user['mediator_id'] : (($isAdmin === true) ? $user['id'] : $user['user_id'])),
                    'name' => $user['name'] ?? $name ?? null,
                    'email' => $email,
                    'role' => $role,
                    'google_id' => $sub,
                    'google_picture' => $picture,
                ],
                'google' => [
                    'id' => $sub,
                    'email' => $email,
                    'name' => $name,
                    'picture' => $picture,
                    //'locale' => $locale,
                ],
            ];

            $this->success($response, 'Login with Google successful');
        } catch (\Throwable $e) {
            $this->error('Google login failed: ' . $e->getMessage(), 500);
        }
    }*/
    
    }





