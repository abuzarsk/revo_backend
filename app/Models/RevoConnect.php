<?php

namespace App\Models;

use App\Core\Model;
use PDO;
use PDOException;

class RevoConnect extends Model
{
    private string $table = 'revo_connects';

    public function getAll(): array
    {
        $sql = "SELECT 
                    id,
                    company_id,
                    mediator_id,
                    user_id,
                    status,
                    note,
                    updated_by,
                    updated_on,
                    created_by,
                    created_on
                FROM {$this->table}
                ORDER BY id ASC";

        $stmt = $this->getDb()->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getById(int $id): ?array
    {
        $sql = "SELECT 
                    id,
                    company_id,
                    mediator_id,
                    user_id,
                    status,
                    note,
                    updated_by,
                    updated_on,
                    created_by,
                    created_on
                FROM {$this->table}
                WHERE id = :id
                LIMIT 1";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }
    

    public function getbycompanyormediatoragainstuser(int $companyId, int $mediatorId, int $userId, int $status=-1): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE company_id = :companyId AND mediator_id = :mediatorId AND user_id = :userId";
        if($status != -1){
            $sql .= " AND status = :status";
        }
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':companyId', $companyId, PDO::PARAM_INT);
        $stmt->bindValue(':mediatorId', $mediatorId, PDO::PARAM_INT);
        $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    

    public function create(array $data): array
    {
        $required = [
            'company_id',
            'mediator_id',
            'user_id',
            'status',
            'created_by',
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                return [
                    'status' => false,
                    'error' => 400,
                    'message' => "Field {$field} is required",
                ];
            }
        }

        $now = date('Y-m-d H:i:s');
        $updatedBy = $data['updated_by'] ?? $data['created_by'];

        $sql = "INSERT INTO {$this->table} (
                    company_id,
                    mediator_id,
                    user_id,
                    status,
                    note,
                    updated_by,
                    updated_on,
                    created_by,
                    created_on
                ) VALUES (
                    :company_id,
                    :mediator_id,
                    :user_id,
                    :status,
                    :note,
                    :updated_by,
                    :updated_on,
                    :created_by,
                    :created_on
                )";

        try {
            $stmt = $this->getDb()->prepare($sql);
            $stmt->bindValue(':company_id', (int)$data['company_id'], PDO::PARAM_INT);
            $stmt->bindValue(':mediator_id', (int)$data['mediator_id'], PDO::PARAM_INT);
            $stmt->bindValue(':user_id', (int)$data['user_id'], PDO::PARAM_INT);
            $stmt->bindValue(':status', (int)$data['status'], PDO::PARAM_INT);
            $stmt->bindValue(':note', $data['note'], PDO::PARAM_STR);
            $stmt->bindValue(':updated_by', (int)$updatedBy, PDO::PARAM_INT);
            $stmt->bindValue(':updated_on', $now, PDO::PARAM_STR);
            $stmt->bindValue(':created_by', (int)$data['created_by'], PDO::PARAM_INT);
            $stmt->bindValue(':created_on', $now, PDO::PARAM_STR);

            $stmt->execute();

            return [
                'status' => true,
                'error' => 201,
                'id' => (int)$this->getDb()->lastInsertId(),
            ];
        } catch (PDOException $e) {
            $code = $e->getCode() === '23000' ? 409 : 500;

            return [
                'status' => false,
                'error' => $code,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function getMediatorCompanyConnects(int $companyId, int $mediatorId): array
    {
        $sql = "SELECT 
                    company_id,
                    mediator_id,
                    user_id,
                    status,
                    note,
                    updated_by,
                    updated_on,
                    created_by,
                    created_on  
        FROM {$this->table} WHERE company_id = :companyId AND mediator_id = :mediatorId";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':companyId', $companyId, PDO::PARAM_INT);
        $stmt->bindValue(':mediatorId', $mediatorId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function getMediatorConnectedCompanies(int $mediatorId,int $status=1): array
    {
    $sql = "SELECT 
                    revo_company.id as company_id,
                    revo_company.alias as company_alias,
                    revo_company.logo as company_logo,
                    revo_company.company_size as company_size,
                    revo_company.group_link as company_group_link,
                    revo_company.slot_in_a_month as company_slot_in_a_month,
                    revo_company.description as company_description,
                    note,
                    {$this->table}.updated_by as updated_by,
                    {$this->table}.updated_on as updated_on,
                    {$this->table}.created_by as created_by,
                    {$this->table}.created_on as created_on
                    
        FROM {$this->table} LEFT JOIN revo_company ON {$this->table}.company_id = revo_company.id WHERE {$this->table}.mediator_id = :mediatorId AND {$this->table}.status = :status AND {$this->table}.user_id = -1";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':mediatorId', $mediatorId, PDO::PARAM_INT);
        $stmt->bindValue(':status', $status, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [':id' => $id];

        $updatable = [
            'company_id',
            'mediator_id',
            'user_id',
            'status',
            'note',
            'updated_by',
        ];

        foreach ($updatable as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $fields[] = "updated_on = :updated_on";
        $params[':updated_on'] = date('Y-m-d H:i:s');

        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = :id";

        $stmt = $this->getDb()->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function getCompaniesConnectedMediator(int $companyId): array
    {
        $sql = "SELECT 
        revo_mediator.id as mediator_id,
        revo_mediator.alias as mediator_alias,
        revo_mediator.profile_pic as mediator_profile_pic,
        revo_mediator.size as mediator_size,
        revo_mediator.group_link as mediator_group_link,
        revo_mediator.slot_in_a_month as mediator_slot_in_a_month,
        revo_mediator.description as mediator_description,
        note,
        {$this->table}.updated_by as updated_by,
        {$this->table}.updated_on as updated_on

        FROM {$this->table}  INNER JOIN revo_mediator ON {$this->table}.mediator_id = revo_mediator.id WHERE company_id = :companyId AND status = 1";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':companyId', $companyId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    
    public function getCompaniesRequestedMediator(int $companyId): array
    {
        $sql = "SELECT         
        revo_mediator.id as mediator_id,
        revo_mediator.alias as mediator_alias,
        revo_mediator.profile_pic as mediator_profile_pic,
        revo_mediator.size as mediator_size,
        revo_mediator.group_link as mediator_group_link,
        revo_mediator.slot_in_a_month as mediator_slot_in_a_month,
        revo_mediator.description as mediator_description,
        note,
        {$this->table}.updated_by as updated_by,
        {$this->table}.updated_on as updated_on
        
        FROM {$this->table}  INNER JOIN revo_mediator ON {$this->table}.mediator_id = revo_mediator.id WHERE company_id = :companyId AND status = 0";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':companyId', $companyId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getCompaniesRejectedMediator(int $companyId): array
    {
        $sql = "SELECT         
        revo_mediator.id as mediator_id,
        revo_mediator.alias as mediator_alias,
        revo_mediator.profile_pic as mediator_profile_pic,
        revo_mediator.size as mediator_size,
        revo_mediator.group_link as mediator_group_link,
        revo_mediator.slot_in_a_month as mediator_slot_in_a_month,
        revo_mediator.description as mediator_description,
        note,
        {$this->table}.updated_by as updated_by,
        {$this->table}.updated_on as updated_on
        
        FROM {$this->table}  INNER JOIN revo_mediator ON {$this->table}.mediator_id = revo_mediator.id WHERE company_id = :companyId AND status = 2";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':companyId', $companyId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function verifyMediator(int $companyId, array $data): bool
    {

        if(!isset($data['mediator_id']) || !isset($data['status']) || !isset($data['note']) || !isset($companyId)){
            return false;
        }


        $mediatorId = $data['mediator_id'];
        $status = $data['status'];
        $note = $data['note'];
        $updatedBy = $companyId;//data['updated_by'];
        $updatedOn = date('Y-m-d H:i:s');
        
        $sql = "UPDATE {$this->table} SET status = :status, note = :note, updated_by = :updatedBy, updated_on = :updatedOn WHERE company_id = :companyId AND mediator_id = :mediatorId";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':status', $data['status'], PDO::PARAM_INT);
        $stmt->bindValue(':companyId', $companyId, PDO::PARAM_INT);
        $stmt->bindValue(':mediatorId', $mediatorId, PDO::PARAM_INT);
        $stmt->bindValue(':note', $note, PDO::PARAM_STR);
        $stmt->bindValue(':updatedBy', $updatedBy, PDO::PARAM_INT);
        $stmt->bindValue(':updatedOn', $updatedOn, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->execute();//$stmt->rowCount() > 0;
    }

    public function getAllConnections(int $userId): array
    {

        $sql = "SELECT * FROM {$this->table} WHERE user_id = :userId and status = 1";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $connectedData = [];
        for ($i = 0, $count = count($data); $i < $count; $i++) {
            $role = 2;

            if ($data[$i]['company_id'] != -1) {

                $model = new RevoCompany($this->getDb());
                $data[$i]['company'] = $model->getById((int) $data[$i]['company_id']);
                $data[$i]['mediator'] = []; 
                $connectedData[$i] = $data[$i]['company'];
                $connectedData[$i]['revo_connect_id'] = $data[$i]['id'];
                $connectedData[$i]['company_id'] = $data[$i]['company_id'];
                $connectedData[$i]['mediator_id'] = $data[$i]['mediator_id'];
                $connectedData[$i]['user_id'] = $data[$i]['user_id'];
                $connectedData[$i]['revo_connect_status'] = $data[$i]['status'];
                $connectedData[$i]['revo_connect_note'] = $data[$i]['note'];
            } else {

                $model = new RevoMediator($this->getDb());
                $data[$i]['company'] = [];
                $data[$i]['mediator'] = $model->getById((int) $data[$i]['mediator_id']);
                $role = 3;
                $connectedData[$i] = $data[$i]['mediator'];
                $connectedData[$i]['revo_connect_id'] = $data[$i]['id'];
                $connectedData[$i]['company_id'] = $data[$i]['company_id'];
                $connectedData[$i]['mediator_id'] = $data[$i]['mediator_id'];
                $connectedData[$i]['user_id'] = $data[$i]['user_id'];
                $connectedData[$i]['revo_connect_status'] = $data[$i]['status'];
                $connectedData[$i]['revo_connect_note'] = $data[$i]['note'];

            }

            $connectedData[$i]['role'] = $role;
        }

        return $connectedData;
    }


    public function getUserConnections(int $userId): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE user_id = :userId and status = 1";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function checkifConnected(int $userId, int $role, int $id): bool
    {
        if($role == 2){
            $sql = "SELECT * FROM {$this->table} WHERE user_id = :userId AND company_id = :id";
            $stmt = $this->getDb()->prepare($sql);
            $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return count($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) > 0;
        
        }else if($role == 3){
            $sql = "SELECT * FROM {$this->table} WHERE user_id = :userId AND mediator_id = :id";
            $stmt = $this->getDb()->prepare($sql);
            $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return count($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) > 0;
        }
        return false;
    }
}

