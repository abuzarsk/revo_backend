<?php

namespace App\Controllers;

use App\Core\Controller;

class RevoFormSubmissionController extends Controller
{
    /**
     * GET /form-submissions/form?form_id={id} - list submissions by form
     */
    public function byForm()
    {
        $user = $this->requireAuth();
        if (!$user) {
            return;
        }

        $formId = isset($_GET['form_id']) ? (int)$_GET['form_id'] : 0;
        if (!$formId) {
            $this->error('Form ID is required', 400);
            return;
        }

        $formModel = $this->model('RevoForm');
        $form = $formModel->getById($formId);
        if (!$form) {
            $this->error('Form not found', 404);
            return;
        }

        // Company users can read submissions for their own forms
        if (isset($user['role']) && (int)$user['role'] === 2) {
            $companyModel = $this->model('RevoCompany');
            $company = $companyModel->getByUserId((int)$user['sub']);
            if (!$company) {
                $this->error('Company not found', 404);
                return;
            }

            $productModel = $this->model('RevoProduct');
            $product = $productModel->getById((int)$form['product_id']);
            if (!$product || (int)$product['company_id'] !== (int)$company['company']) {
                $this->error('You are not authorized to get submissions for this form.', 401);
                return;
            }
        } elseif (isset($user['role']) && (int)$user['role'] !== 1) {
            // Non-admin/non-company users are not allowed on byForm endpoint
            $this->error('You are not authorized to get submissions for this form.', 401);
            return;
        }

        $submissionModel = $this->model('RevoFormSubmission');
        $rows = $submissionModel->getAllByForm($formId);

        $this->success($rows, 'Form submissions retrieved successfully');
    }

    /**
     * GET /form-submissions/user - list submissions by logged-in user
     */
    public function byUser()
    {
        $user = $this->requireAuth();
        if (!$user) {
            return;
        }

        $submissionModel = $this->model('RevoFormSubmission');
        $rows = $submissionModel->getAllByUser((int)$user['sub']);

        $this->success($rows, 'User submissions retrieved successfully');
    }

    /**
     * POST /form-submissions - create new submission
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

        $data = $this->getRequestBody();
        $data['form_id'] = isset($data['form_id']) ? (int)$data['form_id'] : 0;
        $data['submission'] = isset($data['submission']) ? (string)$data['submission'] : '';
        $data['promote_id'] = isset($data['promote_id']) ? (int)$data['promote_id'] : 0;
        $data['user_id'] = (int)$user['sub'];
        $data['created_by'] = (int)$user['sub'];
        $data['updated_by'] = (int)$user['sub'];

        $formModel = $this->model('RevoForm');
        $form = $formModel->getById((int)$data['form_id'],(int)$user['sub']);
        if (!$form) {
            $this->error('Form not found', 404);
            return;
        }

        $submissionModel = $this->model('RevoFormSubmission');
        $result = $submissionModel->create($data);

        if ($result['status'] && $result['error'] === 201) {
            $response = [
                'id' => $result['id'],
                'form_id' => (int)$data['form_id'],
                'promote_id' => (int)$data['promote_id'],
                'submission' => (string)$data['submission'],
                'user_id' => (int)$data['user_id'],
                'created_by' => (int)$data['created_by'],
            ];
            $this->success($response, 'Form submission created successfully', 201);
        } elseif ($result['error'] === 400) {
            $this->error($result['message'] ?? 'Invalid data for form submission', 400);
        } elseif ($result['error'] === 409) {
            $this->error('Form submission already exists', 409);
        } else {
            $this->error($result['message'] ?? 'Failed to create form submission', 500);
        }
    }

    /**
     * POST /form-submissions/update - update submission
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
            $this->error('Submission ID is required', 400);
            return;
        }

        $submissionModel = $this->model('RevoFormSubmission');
        $existing = $submissionModel->getById($id);
        if (!$existing) {
            $this->error('Submission not found', 404);
            return;
        }

        // Only submission owner or admin can update
        if ((int)$user['role'] !== 1 && (int)$existing['user_id'] !== (int)$user['sub']) {
            $this->error('You are not authorized to update this submission.', 401);
            return;
        }

        if (isset($data['form_id'])) {
            $formModel = $this->model('RevoForm');
            $form = $formModel->getById((int)$data['form_id']);
            if (!$form) {
                $this->error('Form not found', 404);
                return;
            }
            $data['form_id'] = (int)$data['form_id'];
        }

        if (isset($data['submission'])) {
            $data['submission'] = (string)$data['submission'];
        }
        $data['updated_by'] = (int)$user['sub'];

        $updated = $submissionModel->update($id, $data);
        if ($updated) {
            $updatedRow = $submissionModel->getById($id);
            $this->success($updatedRow, 'Form submission updated successfully');
        } else {
            $this->error('Failed to update form submission', 500);
        }
    }

    /**
     * DELETE /form-submissions/{id} - delete submission
     */
    public function delete()
    {
        if ($this->getMethod() !== 'DELETE') {
            $this->error('Method not allowed', 405);
            return;
        }

        $id = $this->params['id'] ?? null;
        if (!$id) {
            $this->error('Submission ID is required', 400);
            return;
        }

        $user = $this->requireAuth();
        if (!$user) {
            return;
        }

        $submissionModel = $this->model('RevoFormSubmission');
        $existing = $submissionModel->getById((int)$id);
        if (!$existing) {
            $this->error('Submission not found', 404);
            return;
        }

        // Only submission owner or admin can delete
        if ((int)$user['role'] !== 1 && (int)$existing['user_id'] !== (int)$user['sub']) {
            $this->error('You are not authorized to delete this submission.', 401);
            return;
        }

        $deleted = $submissionModel->delete((int)$id);
        if ($deleted) {
            $this->success(null, 'Form submission deleted successfully');
        } else {
            $this->error('Failed to delete form submission', 500);
        }
    }

    /**
     * GET /company/form-submissions/{promote_id}
     * GET /mediator/form-submissions/{promote_id}
     *
     * Query parameters:
     * - page (int, default 1)
     * - per_page (int, default 1)
     */
    public function getAllByFormSubmissions()
    {
        if ($this->getMethod() !== 'GET') {
            $this->error('Method not allowed', 405);
            return;
        }

        $product_id = isset($this->params['id']) ? (int)$this->params['id'] : 0;
        if (!$product_id) {
            $this->error('Promote ID is required', 400);
            return;
        }

        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 1;
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $user = $this->requireAuth();
        if (!$user) {
            return;
        }

        if((int)$user['role'] !== 2 && (int)$user['role'] !== 3){
            $this->error('You are not authorized to get form submissions.', 401);
            return;
        }

        $userRole = (int)$user['role'];
        $userRoleModel = $this->model('RevoUserRole');
        $userRole = $userRoleModel->getByUserIdAndRoleId((int)$user['sub'],$userRole,1);
        if(!$userRole){
            $this->error('User role not found', 404);
            return;
        }

        $mediator_id = -1;
        $company_id = -1;

        //print_r($userRole);
        if($userRole['revo_role_id']==2){
            $companyModel = $this->model('RevoCompany');
            $company = $companyModel->getByUserId((int)$user['sub']);
            //print_r($company);
            if(!$company){
                $this->error('Company not found', 404);
                return;
            }
            $company_id = $company['company'];
        }else if($userRole['revo_role_id']==3){
            $mediatorModel = $this->model('RevoMediator');
            $mediator = $mediatorModel->getByUserId((int)$user['sub']);
            if(!$mediator){
                $this->error('Mediator not found', 404);
                return;
            }
            $mediator_id = $mediator['id'];
            //print_r($mediator);
        }
        


        $submissionModel = $this->model('RevoFormSubmission');
        $result = $submissionModel->getAllByFormSubmissionsPaginated(
            (int)$user['sub'],
            $company_id,
            $mediator_id,
            $product_id,
            $page,
            $perPage
        );

        $this->success($result, 'Form submissions retrieved successfully');
    }

    /**
     * GET /company/submissions
     *
     * Query parameters:
     * - page (int, default 1)
     * - per_page (int, default 10, max 100)
     * - product_id (int, optional)
     * - status (all|action|submitted|under_review|approved|rejected|paid)
     * - q (string, optional search)
     */
    public function companyList()
    {
        if ($this->getMethod() !== 'GET') {
            $this->error('Method not allowed', 405);
            return;
        }

        $user = $this->requireAuth();
        if (!$user) {
            return;
        }
        // Company accounts only. Mediators, members, and admins cannot use this list.
        if (!isset($user['role']) || (int)$user['role'] !== 2) {
            $this->error('This submissions API is only available to companies.', 401);
            return;
        }

        $userRoleModel = $this->model('RevoUserRole');
        $userRole = $userRoleModel->getByUserIdAndRoleId((int)$user['sub'], 2, 1);
        if (!$userRole) {
            $this->error('User role not found', 404);
            return;
        }

        $companyModel = $this->model('RevoCompany');
        $company = $companyModel->getByUserId((int)$user['sub']);
        if (!$company) {
            $this->error('Company not found', 404);
            return;
        }

        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
        $productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
        $status = isset($_GET['status']) ? (string)$_GET['status'] : 'all';
        $search = isset($_GET['q']) ? (string)$_GET['q'] : '';

        $submissionModel = $this->model('RevoFormSubmission');
        $result = $submissionModel->getCompanySubmissionsPaginated(
            (int)$company['company'],
            $page,
            $perPage,
            $productId,
            $status,
            $search
        );

        $this->success($result, 'Company submissions retrieved successfully');
    }

    /**
     * GET /user/form-submissions/{promote_id} - list user submissions for a promote (paginated)
     *
     * Query parameters:
     * - page (int, default 1)
     * - per_page (int, default 1)
     */
    public function getUserSubmissions()
    {
        if ($this->getMethod() !== 'GET') {
            $this->error('Method not allowed', 405);
            return;
        }

        $promoteId = isset($this->params['id']) ? (int)$this->params['id'] : 0;
        if (!$promoteId) {
            $this->error('Promote ID is required', 400);
            return;
        }

        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 1;
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $user = $this->requireAuth();
        if (!$user) {
            return;
        }
        if ((int)$user['role'] !== 4) {
            $this->error('You are not authorized to get user form submissions.', 401);
            return;
        }

        $submissionModel = $this->model('RevoFormSubmission');
        $result = $submissionModel->getAllByUserSubmissionsPaginated(
            (int)$user['sub'],
            $promoteId,
            1,
            $page,
            $perPage
        );
        $this->success($result, 'User submissions retrieved successfully');
    }

    /**
     * GET /user/refundForm-submissions/{promote_id} - refund form submissions (form_type=2, paginated)
     */
    public function getUserRefundSubmissions()
    {
        if ($this->getMethod() !== 'GET') {
            $this->error('Method not allowed', 405);
            return;
        }

        $promoteId = isset($this->params['id']) ? (int)$this->params['id'] : 0;
        if (!$promoteId) {
            $this->error('Promote ID is required', 400);
            return;
        }

        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 1;
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $user = $this->requireAuth();
        if (!$user) {
            return;
        }
        if ((int)$user['role'] !== 4) {
            $this->error('You are not authorized to get user form submissions.', 401);
            return;
        }

        $submissionModel = $this->model('RevoFormSubmission');
        $result = $submissionModel->getAllByUserSubmissionsPaginated(
            (int)$user['sub'],
            $promoteId,
            2,
            $page,
            $perPage
        );
        $this->success($result, 'User submissions retrieved successfully');
    }

    public function getRefundFormBySubmissionId(){
        if($this->getMethod() !== 'GET'){
            $this->error('Method not allowed', 405);
            return;
        }
        $id = $this->params['id'] ?? null;
        if (!$id) {
            $this->error('Submission ID is required', 400);
            return;
        }
        $user = $this->requireAuth();
        if (!$user) {
            return;
        }
        if($user['role'] != 4){
            $this->error('You are not authorized to get user form submissions.', 401);
            return;
        }

        $submissionModel = $this->model('RevoFormSubmission');
        $rows = $submissionModel->getById((int)$id);

        $refundSubmissionModel = $this->model('RevoRefundSubmission');
        $refundSubmission = $refundSubmissionModel->getSubmissionCountBySubmissionId((int)$id);

        if($refundSubmission > 0){
            $this->error('You have already submitted a refund for this form.', 400);
            return;
        }
        $form = null;
//        print_r($rows);
        if($rows!=null){
            $frow = $rows;
            $formModel = $this->model('RevoForm');
            $form = $formModel->getByPromoteId((int)$frow['promote_id'],(int)$user['sub'],2);
            if($form){
                $this->success($form, 'Form retrieved successfully');
            }else{
                $this->error('Form not found', 404);
                return;
            }
        }


        $this->success($form, 'Submission retrieved successfully');
    }

    public function getExcel()
    {
        if ($this->getMethod() !== 'GET') {
            $this->error('Method not allowed', 405);
            return;
        }

        $id = $this->params['id'] ?? null;
        if (!$id) {
            $this->error('Product ID is required', 400);
            return;
        }

        $user = $this->requireAuth();
        if (!$user) {
            return;
        }
        if($user['role'] > 3){
            $this->error('You are not authorized to get user form submissions.', 401);
            return;
        }

        $productModel = $this->model('RevoProduct');
        $product = $productModel->getById((int)$id);
        if (!$product) {
            $this->error('Product not found', 404);
            return;
        }

        $formId = isset($product['form_id']) ? (int)$product['form_id'] : 0;
        if (!$formId) {
            $this->error('Form not found for this product', 404);
            return;
        }

        $submissionModel = $this->model('RevoFormSubmission');
        $result = $submissionModel->getExcel($formId);
        if (!($result['status'] ?? false)) {
            $this->error($result['message'] ?? 'Failed to generate CSV', 500);
            return;
        }

        $this->success($result, 'CSV generated successfully');
    }
}
