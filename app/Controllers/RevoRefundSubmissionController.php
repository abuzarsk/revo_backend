<?php

namespace App\Controllers;

use App\Core\Controller;

class RevoRefundSubmissionController extends Controller
{
    /**
     * POST /user/refund-submissions - create refund submission
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
            $this->error('You are not authorized to create a refund submission.', 401);
            return;
        }

        $data = $this->getRequestBody();
        $data['submission_id'] = isset($data['submission_id']) ? (int)$data['submission_id'] : 0;
        $data['submission'] = isset($data['submission']) ? (string)$data['submission'] : '';
        $data['user_id'] = (int)$user['sub'];
        $data['created_by'] = (int)$user['sub'];
        $data['updated_by'] = (int)$user['sub'];

        if (!$data['submission_id']) {
            $this->error('Submission ID is required', 400);
            return;
        }

        if ($data['submission'] === '') {
            $this->error('Submission text is required', 400);
            return;
        }

        $formSubmissionModel = $this->model('RevoFormSubmission');
        $formSubmission = $formSubmissionModel->getById($data['submission_id']);
        if (!$formSubmission) {
            $this->error('Form submission not found', 404);
            return;
        }

        if ((int)$formSubmission['user_id'] !== (int)$user['sub']) {
            $this->error('You are not authorized to add a refund for this submission.', 401);
            return;
        }

        $refundModel = $this->model('RevoRefundSubmission');
        $result = $refundModel->create($data);

        if ($result['status'] && $result['error'] === 201) {
            $response = [
                'id' => $result['id'],
                'submission_id' => $data['submission_id'],
                'submission' => $data['submission'],
                'user_id' => $data['user_id'],
                'accept_status' => 0,
                'accept_reject_remark' => '',
                'accept_reject_screenshot' => '',
                'created_by' => $data['created_by'],
            ];
            $this->success($response, 'Refund submission created successfully', 201);
        } elseif ($result['error'] === 400) {
            $this->error($result['message'] ?? 'Invalid data for refund submission', 400);
        } elseif ($result['error'] === 409) {
            $this->error('Refund submission already exists', 409);
        } else {
            $this->error($result['message'] ?? 'Failed to create refund submission', 500);
        }
    }

    /**
     * POST /user/refund-submissions/update - update refund submission
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
            $this->error('You are not authorized to update a refund submission.', 401);
            return;
        }

        $data = $this->getRequestBody();
        $id = isset($data['id']) ? (int)$data['id'] : 0;
        if (!$id) {
            $this->error('Refund submission ID is required', 400);
            return;
        }

        $refundModel = $this->model('RevoRefundSubmission');
        $existing = $refundModel->getById($id);
        if (!$existing) {
            $this->error('Refund submission not found', 404);
            return;
        }

        if ((int)$user['role'] !== 1 && (int)$existing['user_id'] !== (int)$user['sub']) {
            $this->error('You are not authorized to update this refund submission.', 401);
            return;
        }

        if (isset($data['submission_id'])) {
            $submissionId = (int)$data['submission_id'];
            $formSubmissionModel = $this->model('RevoFormSubmission');
            $formSubmission = $formSubmissionModel->getById($submissionId);
            if (!$formSubmission || (int)$formSubmission['user_id'] !== (int)$user['sub']) {
                $this->error('Form submission not found', 404);
                return;
            }
            $data['submission_id'] = $submissionId;
        }

        if (isset($data['submission_text'])) {
            $data['submission_text'] = (string)$data['submission_text'];
        } elseif (isset($data['submission'])) {
            $data['submission_text'] = (string)$data['submission'];
        }
        $data['updated_by'] = (int)$user['sub'];

        unset($data['accept_status'], $data['accept_reject_remark'], $data['accept_reject_screenshot']);

        $updated = $refundModel->update($id, $data);
        if ($updated) {
            $updatedRow = $refundModel->getById($id);
            $this->success($updatedRow, 'Refund submission updated successfully');
        } else {
            $this->error('Failed to update refund submission', 500);
        }
    }


    /**
     * POST /company/refundForm-submissions/update
     * POST /mediator/refundForm-submissions/update
     */
    public function updateAcceptOrRejectStatus()
    {
        if (!in_array($this->getMethod(), ['PUT', 'PATCH', 'POST'], true)) {
            $this->error('Method not allowed', 405);
            return;
        }

        $user = $this->requireAuth();
        if (!$user) {
            return;
        }

        if ((int)$user['role'] !== 2 && (int)$user['role'] !== 3) {
            $this->error('You are not authorized to update refund submission status.', 401);
            return;
        }

        $data = $this->getRequestBody();

        if($data==null){
            $data = $_POST;
        }

        $id = isset($data['id']) ? (int)$data['id'] : 0;
        if (!$id) {
            $this->error('Refund submission ID is required', 400);
            return;
        }

        $data['accept_status'] = isset($data['status']) ? (int)$data['status'] : 0;
        if (!isset($data['accept_status']) || $data['accept_status'] === '') {
            $this->error('Accept status is required', 400);
            return; 
        }

        $photoFilename = '';
        if (!empty($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            // Handle multipart/form-data
            $data['name'] = $_POST['name'] ?? '';
                        
            // Handle photo upload
            $uploadResult = handlePhotoUpload($_FILES['image'],REFUND_UPLOAD_DIR);
            if ($uploadResult['success']) {
                $photoFilename = $uploadResult['filename'];
            } else {
                $this->error($uploadResult['message'], $uploadResult['code'] ?? 400);
                return;
            }
        }else if($data['accept_status'] === 1){
            $this->error('Screenshot is required', 400);
            return; 
        }else if($data['accept_status'] === 2){
            $photoFilename = "";
        }



        $refundModel = $this->model('RevoRefundSubmission');
        $existing = $refundModel->getAllBySubmissionId($id);
        if (!$existing) {
            $this->error('Refund submission not found', 404);
            return;
        }

        $updateData = [
            'accept_status' => (int)$data['accept_status'],
            'accept_reject_remark' => isset($data['remark']) ? (string)$data['remark'] : '',
            'accept_reject_screenshot' => $photoFilename,//isset($data['accept_reject_screenshot']) ? (string)$data['accept_reject_screenshot'] : '',
            'updated_by' => (int)$user['sub'],
        ];


        $updated = $refundModel->update($id, $updateData);
        if ($updated) {
            $updatedRow = $refundModel->getAllBySubmissionId($id);
            $this->success($updatedRow, 'Refund submission status updated successfully');
        } else {
            $this->error('Failed to update refund submission status', 500);
        }
    }

    /**
     * GET /company/refunds
     *
     * Query parameters:
     * - page (int, default 1)
     * - per_page (int, default 10, max 100)
     * - status (all|under_review|paid|rejected)
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
        if (!isset($user['role']) || (int)$user['role'] !== 2) {
            $this->error('This refunds API is only available to companies.', 401);
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
        if (!$company || empty($company['company'])) {
            $this->error('Company not found', 404);
            return;
        }

        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
        $status = isset($_GET['status']) ? (string)$_GET['status'] : 'all';
        $search = isset($_GET['q']) ? (string)$_GET['q'] : '';

        $refundModel = $this->model('RevoRefundSubmission');
        $result = $refundModel->getCompanyRefundsPaginated(
            (int)$company['company'],
            $page,
            $perPage,
            $status,
            $search
        );

        $this->success($result, 'Refunds retrieved successfully');
    }

    /**
     * GET /company/refundForm-submissions/{submission_id}
     * GET /mediator/refundForm-submissions/{submission_id}
     *
     * Query parameters:
     * - page (int, default 1)
     * - per_page (int, default 1)
     */
    public function getForCompanyAndMediatorBySubmissionId()
    {
        if ($this->getMethod() !== 'GET') {
            $this->error('Method not allowed', 405);
            return;
        }

        $submissionId = isset($this->params['id']) ? (int)$this->params['id'] : 0;
        if (!$submissionId) {
            $this->error('Submission ID is required', 400);
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

        if ((int)$user['role'] !== 2 && (int)$user['role'] !== 3) {
            $this->error('You are not authorized to get refund submissions.', 401);
            return;
        }

        $formSubmissionModel = $this->model('RevoFormSubmission');
        $formSubmission = $formSubmissionModel->getById($submissionId);
        if (!$formSubmission) {
            $this->error('Form submission not found', 404);
            return;
        }

        $refundModel = $this->model('RevoRefundSubmission');
        $result = $refundModel->getAllBySubmissionIdForCompanyAndMediatorPaginated(
            $submissionId,
            $page,
            $perPage
        );

        $this->success($result, 'Refund submissions retrieved successfully');
    }

    /**
     * GET /user/refundForm-submissions/{submission_id} - list refund submissions (paginated)
     *
     * Query parameters:
     * - page (int, default 1)
     * - per_page (int, default 1)
     */
    public function getBySubmissionId()
    {
        if ($this->getMethod() !== 'GET') {
            $this->error('Method not allowed', 405);
            return;
        }

        $submissionId = isset($this->params['id']) ? (int)$this->params['id'] : 0;
        if (!$submissionId) {
            $this->error('Submission ID is required', 400);
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
            $this->error('You are not authorized to get refund submissions.', 401);
            return;
        }

        $formSubmissionModel = $this->model('RevoFormSubmission');
        $formSubmission = $formSubmissionModel->getById($submissionId);
        if (!$formSubmission) {
            $this->error('Form submission not found', 404);
            return;
        }

        if ((int)$formSubmission['user_id'] !== (int)$user['sub']) {
            $this->error('You are not authorized to get refund submissions for this submission.', 401);
            return;
        }

        $refundModel = $this->model('RevoRefundSubmission');
        $result = $refundModel->getAllBySubmissionIdForUserPaginated(
            $submissionId,
            (int)$user['sub'],
            $page,
            $perPage
        );

        $this->success($result, 'Refund submissions retrieved successfully');
    }

    /**
     * GET /user/refund-submissions/user - list all refund submissions for logged-in user
     */
    public function byUser()
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
            $this->error('You are not authorized to get refund submissions.', 401);
            return;
        }

        $refundModel = $this->model('RevoRefundSubmission');
        $rows = $refundModel->getAllByUser((int)$user['sub']);

        $this->success($rows, 'Refund submissions retrieved successfully');
    }

    /**
     * DELETE - refund submission by id (admin or owner)
     */
    public function delete()
    {
        if ($this->getMethod() !== 'DELETE') {
            $this->error('Method not allowed', 405);
            return;
        }

        $id = $this->params['id'] ?? null;
        if (!$id) {
            $this->error('Refund submission ID is required', 400);
            return;
        }

        $user = $this->requireAuth();
        if (!$user) {
            return;
        }

        $refundModel = $this->model('RevoRefundSubmission');
        $existing = $refundModel->getById((int)$id);
        if (!$existing) {
            $this->error('Refund submission not found', 404);
            return;
        }

        if ((int)$user['role'] !== 1 && (int)$existing['user_id'] !== (int)$user['sub']) {
            $this->error('You are not authorized to delete this refund submission.', 401);
            return;
        }

        $deleted = $refundModel->delete((int)$id);
        if ($deleted) {
            $this->success(null, 'Refund submission deleted successfully');
        } else {
            $this->error('Failed to delete refund submission', 500);
        }
    }
}
