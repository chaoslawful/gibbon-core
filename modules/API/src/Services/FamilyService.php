<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

namespace Gibbon\Module\API\Services;

use Gibbon\Domain\User\FamilyAdultGateway;
use Gibbon\Domain\User\FamilyChildGateway;
use Gibbon\Domain\User\FamilyGateway;
use Gibbon\Module\API\Auth\PermissionMapper;
use Gibbon\Module\API\Support\RestTable;

class FamilyService
{
    public function __construct(
        protected PermissionMapper $permissions,
        protected FamilyGateway $families,
        protected FamilyAdultGateway $adults,
        protected FamilyChildGateway $children
    ) {
    }

    public function list(): array
    {
        $this->permissions->assertAllows('User Admin', 'family_manage', 'You do not have permission to manage families.');
        $criteria = $this->families->newQueryCriteria()->sortBy('name')->pageSize(0);
        return $this->families->queryFamilies($criteria)->toArray();
    }

    public function get(string $id): array
    {
        $this->permissions->assertAllows('User Admin', 'family_manage', 'You do not have permission to manage families.');
        $family = RestTable::requireRow($this->families, $id, 'Family not found.');
        $family['adults'] = $this->adults->selectBy(['gibbonFamilyID' => $id])->fetchAll();
        $family['children'] = $this->children->selectBy(['gibbonFamilyID' => $id])->fetchAll();
        return $family;
    }

    public function create(array $body): array
    {
        $this->permissions->assertAllows('User Admin', 'family_manage', 'You do not have permission to manage families.');
        $data = RestTable::pick($body, ['name', 'status', 'nameAddress', 'homeAddress', 'homeAddressDistrict', 'homeAddressCountry', 'languageHomePrimary', 'languageHomeSecondary']);
        RestTable::requireFields($data, ['name']);
        $data = RestTable::defaults($data, [
            'status' => 'Married',
            'nameAddress' => '',
            'homeAddress' => '',
            'homeAddressDistrict' => '',
            'homeAddressCountry' => '',
            'languageHomePrimary' => '',
        ]);
        return RestTable::create($this->families, $data);
    }

    public function update(string $id, array $body): array
    {
        $this->permissions->assertAllows('User Admin', 'family_manage', 'You do not have permission to manage families.');
        $data = RestTable::pick($body, ['name', 'status', 'nameAddress', 'homeAddress', 'homeAddressDistrict', 'homeAddressCountry', 'languageHomePrimary', 'languageHomeSecondary']);
        return RestTable::update($this->families, $id, $data, 'Family not found.');
    }

    public function delete(string $id): void
    {
        $this->permissions->assertAllows('User Admin', 'family_manage', 'You do not have permission to manage families.');
        RestTable::delete($this->families, $id, 'Family not found.');
    }

    public function addAdult(string $familyId, array $body): array
    {
        $this->permissions->assertAllows('User Admin', 'family_manage', 'You do not have permission to manage families.');
        RestTable::requireRow($this->families, $familyId, 'Family not found.');
        $data = RestTable::pick($body, ['gibbonPersonID', 'comment', 'childDataAccess', 'contactPriority', 'contactCall', 'contactSMS', 'contactEmail', 'contactMail']);
        RestTable::requireFields($data, ['gibbonPersonID']);
        $data['gibbonFamilyID'] = $familyId;
        $data = RestTable::defaults($data, [
            'comment' => '',
            'childDataAccess' => 'Y',
            'contactPriority' => 1,
            'contactCall' => 'Y',
            'contactSMS' => 'N',
            'contactEmail' => 'Y',
            'contactMail' => 'N',
        ]);
        return RestTable::create($this->adults, $data);
    }

    public function deleteAdult(string $id): void
    {
        $this->permissions->assertAllows('User Admin', 'family_manage', 'You do not have permission to manage families.');
        RestTable::delete($this->adults, $id, 'Family adult not found.');
    }

    public function addChild(string $familyId, array $body): array
    {
        $this->permissions->assertAllows('User Admin', 'family_manage', 'You do not have permission to manage families.');
        RestTable::requireRow($this->families, $familyId, 'Family not found.');
        $data = RestTable::pick($body, ['gibbonPersonID', 'comment']);
        RestTable::requireFields($data, ['gibbonPersonID']);
        $data['gibbonFamilyID'] = $familyId;
        $data = RestTable::defaults($data, ['comment' => '']);
        return RestTable::create($this->children, $data);
    }

    public function deleteChild(string $id): void
    {
        $this->permissions->assertAllows('User Admin', 'family_manage', 'You do not have permission to manage families.');
        RestTable::delete($this->children, $id, 'Family child not found.');
    }
}
