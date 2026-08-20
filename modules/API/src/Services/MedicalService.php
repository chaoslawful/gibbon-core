<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

namespace Gibbon\Module\API\Services;

use Gibbon\Contracts\Database\Connection;
use Gibbon\Domain\School\MedicalConditionGateway;
use Gibbon\Domain\Students\MedicalGateway;
use Gibbon\Module\API\Auth\PermissionMapper;
use Gibbon\Module\API\Http\ApiException;
use Gibbon\Module\API\Support\RestTable;

class MedicalService
{
    public function __construct(
        protected PermissionMapper $permissions,
        protected MedicalConditionGateway $conditions,
        protected MedicalGateway $personMedical,
        protected Connection $db
    ) {
    }

    public function listConditions(): array
    {
        $this->permissions->assertAllows('School Admin', 'medicalConditions_manage', 'You do not have permission to manage medical conditions.');
        return $this->conditions->selectBy([])->fetchAll();
    }

    public function createCondition(array $body): array
    {
        $this->permissions->assertAllows('School Admin', 'medicalConditions_manage', 'You do not have permission to manage medical conditions.');
        $data = RestTable::pick($body, ['name', 'description']);
        RestTable::requireFields($data, ['name']);
        return RestTable::create($this->conditions, $data);
    }

    public function updateCondition(string $id, array $body): array
    {
        $this->permissions->assertAllows('School Admin', 'medicalConditions_manage', 'You do not have permission to manage medical conditions.');
        $data = RestTable::pick($body, ['name', 'description']);
        return RestTable::update($this->conditions, $id, $data, 'Medical condition not found.');
    }

    public function deleteCondition(string $id): void
    {
        $this->permissions->assertAllows('School Admin', 'medicalConditions_manage', 'You do not have permission to manage medical conditions.');
        RestTable::delete($this->conditions, $id, 'Medical condition not found.');
    }

    public function getPersonMedical(string $personId): array
    {
        $this->permissions->assertAllows('Students', 'medicalForm_manage', 'You do not have permission to manage medical forms.');
        $row = $this->personMedical->selectBy(['gibbonPersonID' => $personId])->fetch();
        if (empty($row)) {
            return ['gibbonPersonID' => $personId, 'conditions' => []];
        }
        $row['conditions'] = $this->db->select(
            'SELECT * FROM gibbonPersonMedicalCondition WHERE gibbonPersonMedicalID=:id',
            ['id' => $row['gibbonPersonMedicalID']]
        )->fetchAll();
        return $row;
    }

    public function upsertPersonMedical(string $personId, array $body): array
    {
        $this->permissions->assertAllows('Students', 'medicalForm_manage', 'You do not have permission to manage medical forms.');
        $data = RestTable::pick($body, ['longTermMedication', 'longTermMedicationDetails', 'comment']);
        $data = RestTable::defaults($data, [
            'longTermMedication' => 'N',
            'longTermMedicationDetails' => '',
            'comment' => '',
        ]);
        $existing = $this->personMedical->selectBy(['gibbonPersonID' => $personId])->fetch();
        if (empty($existing)) {
            $data['gibbonPersonID'] = $personId;
            RestTable::create($this->personMedical, $data);
        } else {
            $this->personMedical->update($existing['gibbonPersonMedicalID'], $data);
        }
        return $this->getPersonMedical($personId);
    }

    public function addPersonCondition(string $personId, array $body): array
    {
        $this->permissions->assertAllows('Students', 'medicalForm_manage', 'You do not have permission to manage medical forms.');
        $medical = $this->personMedical->selectBy(['gibbonPersonID' => $personId])->fetch();
        if (empty($medical)) {
            throw new ApiException('Create the person medical form first.', 422);
        }
        $name = $body['name'] ?? '';
        $risk = $body['gibbonAlertLevelID'] ?? $body['risk'] ?? '';
        if ($name === '') {
            throw new ApiException('name is required.', 422);
        }
        $id = $this->db->insert(
            'INSERT INTO gibbonPersonMedicalCondition SET gibbonPersonMedicalID=:mid, name=:name, gibbonAlertLevelID=:risk, triggers=:triggers, reaction=:reaction, response=:response, medication=:medication, lastEpisode=:lastEpisode, lastEpisodeTreatment=:lastEpisodeTreatment, comment=:comment',
            [
                'mid' => $medical['gibbonPersonMedicalID'],
                'name' => $name,
                'risk' => $risk ?: null,
                'triggers' => $body['triggers'] ?? '',
                'reaction' => $body['reaction'] ?? '',
                'response' => $body['response'] ?? '',
                'medication' => $body['medication'] ?? '',
                'lastEpisode' => $body['lastEpisode'] ?? null,
                'lastEpisodeTreatment' => $body['lastEpisodeTreatment'] ?? '',
                'comment' => $body['comment'] ?? '',
            ]
        );
        return $this->db->selectOne('SELECT * FROM gibbonPersonMedicalCondition WHERE gibbonPersonMedicalConditionID=:id', ['id' => $id]);
    }

    public function deletePersonCondition(string $id): void
    {
        $this->permissions->assertAllows('Students', 'medicalForm_manage', 'You do not have permission to manage medical forms.');
        $row = $this->db->selectOne('SELECT gibbonPersonMedicalConditionID FROM gibbonPersonMedicalCondition WHERE gibbonPersonMedicalConditionID=:id', ['id' => $id]);
        if (empty($row)) {
            throw new ApiException('Medical condition record not found.', 404);
        }
        $this->db->delete('DELETE FROM gibbonPersonMedicalCondition WHERE gibbonPersonMedicalConditionID=:id', ['id' => $id]);
    }
}
