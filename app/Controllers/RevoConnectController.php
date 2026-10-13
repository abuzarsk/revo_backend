<?php

namespace App\Controllers;

use App\Core\Controller;

class RevoConnectController extends Controller
{
    /**
     * GET /revo-connects - list all connects
     */
    public function index()
    {
        $model = $this->model('RevoConnect');
        $rows = $model->getAll();

        $this->success($rows, 'Connects retrieved successfully');
    }

    /**
     * GET /revo-connects/{id} - get a single connect
     */
    public function show($id = null)
    {
        $id = $id ?? ($this->params['id'] ?? null);

        if (!$id) {
            $this->error('Connect ID is required', 400);
            return;
        }

        $model = $this->model('RevoConnect');
        $row = $model->getById((int)$id);

        if (!$row) {
            $this->notFound('Connect not found');
            return;
        }

        $this->success($row, 'Connect retrieved successfully');
    }

    /**
     * POST /revo-connects - create new connect
     *
     * Expected JSON body fields:
     * - company_id
     * - mediator_id
     * - user_id
     * - status
     * - note
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
            $this->error('Authentication required. Please provide a valid token.', 401);
            return;
        }

        if($user['role'] == 3 || $user['role'] == 4){

            // if($user['role'] != 2 && $user['role'] != 3 && $user['role'] != 4){
            //     $this->error('You are not authorized to create a connect.', 401);
            //     return;
            // }
            $data = $this->getRequestBody();

            if($user['role'] == 3){
                $mediatorModel = $this->model('RevoMediator');
                $mediator = $mediatorModel->getByUserId((int)$user['sub']);
                if (!$mediator) {
                    $this->error('Mediator not found', 404);
                    return;
                }

                $data['company_id'] = (int)$data['company'];
                $data['mediator_id'] = (int)$mediator['id'];
                $data['user_id'] = -1;//(int)$data['user'];
                $data['status'] = 0;//(int)$data['status'];
                $data['note'] = isset($data['note']) ? $data['note'] : '';
                $data['created_by'] = (int)$user['sub'];
                $data['updated_by'] = (int)$user['sub'];
                $data['updated_on'] = date('Y-m-d H:i:s');
                $data['created_on'] = date('Y-m-d H:i:s');

                //check if connect already exists
                $model = $this->model('RevoConnect');
                $connect = $model->getbycompanyormediatoragainstuser((int)$data['company_id'], (int)$data['mediator_id'], -1);
                
                $active = 0;

                if(count($connect) > 0){

                  for($i = 0; $i < count($connect); $i++){
                        if($connect[$i]['status'] == 1){
                            $active = 1;
                            break;
                        }                
                    }
                }
                if($active == 1){
                    $this->error('Connect already exists', 409);
                    return;
                }
                

            }else if($user['role'] == 4){

                $data['company_id'] = (int)$data['company'];
                $data['mediator_id'] = (int)$data['mediator'];
                $data['user_id'] = (int)$user['sub'];
                $data['status'] = 1;//(int)$data['status'];
                $data['note'] = isset($data['note']) ? $data['note'] : '';
                $data['created_by'] = (int)$user['sub'];
                $data['updated_by'] = (int)$user['sub'];
                $data['updated_on'] = date('Y-m-d H:i:s');
                $data['created_on'] = date('Y-m-d H:i:s');


                //check if connect already exists
                $model = $this->model('RevoConnect');
                $connect = $model->getbycompanyormediatoragainstuser((int)$data['company_id'], (int)$data['mediator_id'], (int)$user['sub']);
               
                $active = 0;

                if(count($connect) > 0){

                  for($i = 0; $i < count($connect); $i++){
                        if($connect[$i]['status'] == 1){
                            $active = 1;
                            break;
                        }                
                    }
                }
                if($active == 1){
                    $this->error('Connect already exists', 409);
                    return;
                }
                

            }

            

            $model = $this->model('RevoConnect');
            $result = $model->create($data);

            if ($result['status'] && $result['error'] === 201) {
                $response = [
                    'id' => $result['id'],
                    'company_id' => (int)$data['company_id'],
                    'mediator_id' => (int)$data['mediator_id'],
                    'user_id' => (int)$data['user_id'],
                    'status' => (int)$data['status'],
                    'note' => $data['note'],
                    'created_by' => (int)$data['created_by'],
                ];
                $this->success($response, 'Connect created successfully', 201);
            } elseif ($result['error'] === 400) {
                $this->error($result['message'] ?? 'Invalid data for connect', 400);
            } elseif ($result['error'] === 409) {
                $this->error('Connect already exists', 409);
            } else {
                $this->error($result['message'] ?? 'Failed to create connect', 500);
            }
        }else{
            $this->error('You are not authorized to create a connect.', 401);
            return;
        }
    }

    /**
     * PUT/PATCH /revo-connects/{id} - update connect
     *
     * Body may include:
     * - company_id
     * - mediator_id
     * - user_id
     * - status
     * - note
     * - updated_by
     */
    public function update()
    {
        if (!in_array($this->getMethod(), ['PUT', 'PATCH','POST'], true)) {
            $this->error('Method not allowed', 405);
            return;
        }

        $user = $this->requireAuth();
        if (!$user) {
            $this->error('Authentication required. Please provide a valid token.', 401);
            return;
        }
        if($user['role'] != 4){
            $this->error('You are not authorized to update a connect.', 401);
            return;
        }
        // $id = $this->params['id'] ?? null;
        // if (!$id) {
        //     $this->error('Connect ID is required', 400);
        //     return;
        // }


        $data = $this->getRequestBody();
        $id = (int)$data['id'];
        $data['updated_by'] = (int)$user['sub'];
        $data['updated_on'] = date('Y-m-d H:i:s');


        $model = $this->model('RevoConnect');
        $updated = $model->update((int)$id, $data);

        if ($updated) {
            $updatedRow = $model->getById((int)$id);
            $this->success($updatedRow, 'Connect updated successfully');
        } else {
            $this->error('Failed to update connect', 500);
        }
    }

    /**
     * DELETE /revo-connects/{id} - delete connect
     */
    public function delete()
    {
        if ($this->getMethod() !== 'DELETE') {
            $this->error('Method not allowed', 405);
            return;
        }

        $id = $this->params['id'] ?? null;
        if (!$id) {
            $this->error('Connect ID is required', 400);
            return;
        }

        $model = $this->model('RevoConnect');
        $deleted = $model->delete((int)$id);

        if ($deleted) {
            $this->success(null, 'Connect deleted successfully');
        } else {
            $this->error('Failed to delete connect', 500);
        }
    }

    public function mediatorconnectedcompanies()
    {
        $user = $this->requireAuth();
        if (!$user) {
            $this->error('Authentication required. Please provide a valid token.', 401);
            return;
        }
        if($user['role'] != 3){
            $this->error('You are not authorized to get connected companies.', 401);
            return;
        }

        $mediatorModel = $this->model('RevoMediator');
        $mediator = $mediatorModel->getByUserId((int)$user['sub']);
        if (!$mediator) {
            $this->error('Mediator not found', 404);
            return;
        }

        $model = $this->model('RevoConnect');
        $connects = $model->getMediatorConnectedCompanies((int)$mediator['id']);
        if (!$connects) {
            $this->error('Connects not found', 404);
            return;
        }
        $this->success($connects, 'Connects retrieved successfully');
    }

    public function mediatorrequestedcompanies()
    {
        $user = $this->requireAuth();
        if (!$user) {
            $this->error('Authentication required. Please provide a valid token.', 401);
            return;
        }
        if($user['role'] != 3){
            $this->error('You are not authorized to get requested companies.', 401);
            return;
        }
        
        $mediatorModel = $this->model('RevoMediator');
        $mediator = $mediatorModel->getByUserId((int)$user['sub']);
        if (!$mediator) {
            $this->error('Mediator not found', 404);
            return;
        }

        $model = $this->model('RevoConnect');
        $connects = $model->getMediatorConnectedCompanies((int)$mediator['id'],0);
        if (!$connects) {
            $this->error('Connects not found', 404);
            return;
        }
        $this->success($connects, 'Connects retrieved successfully');
    }

    public function mediatorrejectedcompany()
    {
        $user = $this->requireAuth();
        if (!$user) {
            $this->error('Authentication required. Please provide a valid token.', 401);
            return;
        }
        if($user['role'] != 3){
            $this->error('You are not authorized to get rejected companies.', 401);
            return;
        }

        $mediatorModel = $this->model('RevoMediator');
        $mediator = $mediatorModel->getByUserId((int)$user['sub']);
        if (!$mediator) {
            $this->error('Mediator not found', 404);
            return;
        }


        $model = $this->model('RevoConnect');
        $connects = $model->getMediatorConnectedCompanies((int)$mediator['id'],2);
        if (!$connects) {
            $this->error('Connects not found', 404);
            return;
        }
        $this->success($connects, 'Connects retrieved successfully');
    }

    public function companiesconnectedmediator()
    {
        $user = $this->requireAuth();
        if (!$user) {
            $this->error('Authentication required. Please provide a valid token.', 401);
            return;
        }
        if($user['role'] != 2){
            $this->error('You are not authorized to get connected mediators.', 401);
            return;
        }

        $user_id = (int)$user['sub'];
        $companyModel = $this->model('RevoCompany');
        $company = $companyModel->getByUserId($user_id);
        
        $model = $this->model('RevoConnect');
        $connects = $model->getCompaniesConnectedMediator((int)$company['company']);
        if (!$connects) {
            $this->error('Connects not found', 404);
            return;
        }
        $this->success($connects, 'Connects retrieved successfully');
    }

    public function companiesrequestedmediator()
    {
        $user = $this->requireAuth();
        if (!$user) {
            $this->error('Authentication required. Please provide a valid token.', 401);
            return;
        }
        if($user['role'] != 2){
            $this->error('You are not authorized to get requested mediators.', 401);
            return;
        }


        $user_id = (int)$user['sub'];
        $companyModel = $this->model('RevoCompany');
        $company = $companyModel->getByUserId($user_id);


        $model = $this->model('RevoConnect');
        $connects = $model->getCompaniesRequestedMediator((int)$company['company']);
        if (!$connects) {
            $this->error('Connects not found', 404);
            return;
        }
        $this->success($connects, 'Connects retrieved successfully');
    }

    public function companyrejectedmediator()
    {
        $user = $this->requireAuth();
        if (!$user) {
            $this->error('Authentication required. Please provide a valid token.', 401);
            return;
        }
        if($user['role'] != 2){
            $this->error('You are not authorized to get rejected mediators.', 401);
            return;
        }   

        $user_id = (int)$user['sub'];
        $companyModel = $this->model('RevoCompany');
        $company = $companyModel->getByUserId($user_id);
        

        $model = $this->model('RevoConnect');
        $connects = $model->getCompaniesRejectedMediator((int)$company['company']);
        if (!$connects) {
            $this->error('Connects not found', 404);
            return;
        }
        $this->success($connects, 'Connects retrieved successfully');
    }

    public function verifymediator()
    {
        $user = $this->requireAuth();
        if (!$user) {
            $this->error('Authentication required. Please provide a valid token.', 401);
            return;
        }
    
        if($user['role'] != 2){
            $this->error('You are not authorized to verify mediators.', 401);
            return;
        }

        $data = $this->getRequestBody();

        $user_id = (int)$user['sub'];
        $companyModel = $this->model('RevoCompany');
        $company = $companyModel->getByUserId($user_id);

        $model = $this->model('RevoConnect');
        $connect = $model->verifyMediator((int)$company['company'], $data);
        if (!$connect) {
            $this->error('Verification Error', 404);
            return;
        }
        $this->success($connect, 'Connect verified successfully');
    }


    public function getbycompanyormediatoragainstuser()
    {
        $user = $this->requireAuth();
        if (!$user) {
            $this->error('Authentication required. Please provide a valid token.', 401);
            return;
        }
        if($user['role'] != 4){
            $this->error('You are not authorized to get connect by company or mediator against user.', 401);
            return;
        }
        $data = $this->getRequestBody();
        $companyId = (int)$data['company'];
        $mediatorId = (int)$data['mediator'];
        $userId = (int)$user['sub'];
        $model = $this->model('RevoConnect');
        $connect = $model->getbycompanyormediatoragainstuser($companyId, $mediatorId, $userId);
        if (!$connect) {
            $this->error('Connect not found', 404);
            return;
        }
        $this->success($connect, 'Connect retrieved successfully');
    }

    public function getallconnections()
    {
        $user = $this->requireAuth();
        if (!$user) {
            $this->error('Authentication required. Please provide a valid token.', 401);
            return;
        }
        if($user['role'] != 4){
            $this->error('You are not authorized to get all connections.', 401);
            return;
        }
        $model = $this->model('RevoConnect');
        $connects = $model->getAllConnections((int)$user['sub']);
        $this->success($connects, 'Connections retrieved successfully');
    }


    
}

