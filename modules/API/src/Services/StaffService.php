<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

namespace Gibbon\Module\API\Services;

use Gibbon\Domain\Staff\StaffGateway;
use Gibbon\Domain\User\RoleGateway;
use Gibbon\Domain\User\UserGateway;
use Gibbon\Module\API\Auth\PermissionMapper;
use Gibbon\Module\API\Http\ApiException;
use Gibbon\Module\API\Support\RestTable;

class StaffService
{
    protected array $staffFields = [
        'type', 'initials', 'jobTitle', 'firstAidQualified', 'firstAidQualification',
        'firstAidExpiry', 'countryOfOrigin', 'qualifications', 'biographicalGrouping',
        'biographicalGroupingPriority', 'biography', 'coverageExclude', 'coveragePriority',
    ];

    protected array $personPublicFields = [
        'title', 'surname', 'preferredName', 'firstName', 'officialName', 'username',
        'status', 'email', 'emailAlternate', 'website', 'image_240', 'phone1', 'phone1Type',
        'phone1CountryCode', 'phone2', 'phone2Type', 'phone2CountryCode', 'dateStart', 'dateEnd',
    ];

    public function __construct(
        protected PermissionMapper $permissions,
        protected StaffGateway $staff,
        protected UserGateway $users,
        protected RoleGateway $roles
    ) {
    }

    public function list(array $query): array
    {
        $this->permissions->assertCanViewStaff();

        if (!empty($query['gibbonPersonID'])) {
            $row = $this->staff->selectBy(['gibbonPersonID' => $query['gibbonPersonID']])->fetch();
            if (empty($row)) {
                return [];
            }

            try {
                return [$this->get($row['gibbonStaffID'])];
            } catch (ApiException $e) {
                if ((int) $e->getCode() === 404) {
                    return [];
                }
                throw $e;
            }
        }

        $criteria = $this->staff->newQueryCriteria()
            ->searchBy($this->staff->getSearchableColumns(), $query['q'] ?? '')
            ->sortBy(['surname', 'preferredName'])
            ->pageSize((int) ($query['limit'] ?? 50));

        if ($this->permissions->canViewFullStaffDirectory() && (($query['all'] ?? 'N') === 'Y')) {
            $criteria->filterBy('all', 'Y');
        }
        if (!empty($query['type'])) {
            $criteria->filterBy('type', $query['type']);
        }
        if (!empty($query['status']) && $this->permissions->canViewFullStaffDirectory()) {
            $criteria->filterBy('status', $query['status']);
        }

        return $this->staff->queryAllStaff($criteria)->toArray();
    }

    public function get(string $id): array
    {
        $this->permissions->assertCanViewStaff();
        $row = $this->publicStaff($id);

        if (!$this->permissions->canViewFullStaffDirectory() && ($row['status'] ?? '') !== 'Full') {
            throw new ApiException('Staff record not found.', 404);
        }

        return $row;
    }

    public function create(array $body): array
    {
        $this->permissions->assertCanManageStaff();
        if (empty($body['type']) && !empty($body['staffType'])) {
            $body['type'] = $body['staffType'];
        }

        $data = RestTable::pick($body, array_merge(['gibbonPersonID'], $this->staffFields));
        RestTable::requireFields($data, ['gibbonPersonID', 'type']);
        $data['type'] = $this->normalizeType($data['type']);
        $person = $this->requireEligiblePerson($data['gibbonPersonID']);
        $this->assertUniquePerson($data['gibbonPersonID']);
        $this->assertUniqueInitials($data['initials'] ?? null);

        $data = RestTable::emptyToNull($data, ['initials', 'firstAidQualification', 'firstAidExpiry']);
        $data = RestTable::defaults($data, [
            'jobTitle' => $body['jobTitle'] ?? ($person['jobTitle'] ?? ''),
            'firstAidQualified' => '',
            'countryOfOrigin' => '',
            'qualifications' => '',
            'biographicalGrouping' => '',
            'biographicalGroupingPriority' => 0,
            'biography' => '',
            'coverageExclude' => 'N',
            'coveragePriority' => 0,
            'fields' => '',
        ]);
        $this->normalizeFirstAid($data);

        $row = RestTable::create($this->staff, $data);
        $this->syncPersonDates($row['gibbonPersonID'], $body);

        return $this->publicStaff($row['gibbonStaffID']);
    }

    public function update(string $id, array $body): array
    {
        $this->permissions->assertCanManageStaff();
        RestTable::requireRow($this->staff, $id, 'Staff record not found.');
        if (empty($body['type']) && !empty($body['staffType'])) {
            $body['type'] = $body['staffType'];
        }

        $data = RestTable::pick($body, $this->staffFields);
        if (array_key_exists('type', $data)) {
            $data['type'] = $this->normalizeType($data['type']);
        }
        $data = RestTable::emptyToNull($data, ['initials', 'firstAidQualification', 'firstAidExpiry']);
        if (array_key_exists('initials', $data)) {
            $this->assertUniqueInitials($data['initials'], $id);
        }
        $this->normalizeFirstAid($data);

        $row = RestTable::update($this->staff, $id, $data, 'Staff record not found.');
        $this->syncPersonDates($row['gibbonPersonID'], $body);

        return $this->publicStaff($id);
    }

    public function delete(string $id): void
    {
        $this->permissions->assertCanManageStaff();
        RestTable::delete($this->staff, $id, 'Staff record not found.');
    }

    protected function publicStaff(string $id): array
    {
        $row = $this->staff->selectStaffByStaffID($id)->fetch();
        if (empty($row)) {
            throw new ApiException('Staff record not found.', 404);
        }

        $person = $this->users->getByID($row['gibbonPersonID']) ?: [];
        foreach ($this->personPublicFields as $key) {
            if (array_key_exists($key, $person)) {
                $row[$key] = $person[$key];
            }
        }
        unset($row['password'], $row['passwordStrong'], $row['passwordStrongSalt']);

        return $row;
    }

    protected function requireEligiblePerson(string $gibbonPersonID): array
    {
        $person = $this->users->getByID($gibbonPersonID);
        if (empty($person)) {
            throw new ApiException('Person not found.', 422);
        }
        if (!$this->personHasStaffRole($person)) {
            throw new ApiException('This person must have a Staff role before a staff record can be created.', 422);
        }

        return $person;
    }

    protected function personHasStaffRole(array $person): bool
    {
        $ids = array_filter(explode(',', (string) ($person['gibbonRoleIDAll'] ?? $person['gibbonRoleIDPrimary'] ?? '')));
        foreach ($ids as $roleId) {
            $role = $this->roles->getByID($roleId);
            if (!empty($role) && ($role['category'] ?? '') === 'Staff') {
                return true;
            }
        }

        return false;
    }

    protected function assertUniquePerson(string $gibbonPersonID): void
    {
        $existing = $this->staff->selectBy(['gibbonPersonID' => $gibbonPersonID])->fetch();
        if (!empty($existing)) {
            throw new ApiException('This person already has a staff record.', 422);
        }
    }

    protected function assertUniqueInitials(?string $initials, ?string $excludeId = null): void
    {
        if ($initials === null || $initials === '') {
            return;
        }

        $taken = $this->staff->selectBy(['initials' => $initials])->fetch();
        if (!empty($taken) && (string) $taken['gibbonStaffID'] !== (string) $excludeId) {
            throw new ApiException('Staff initials are already in use.', 422);
        }
    }

    protected function normalizeType(string $type): string
    {
        $normalized = ucfirst(strtolower(trim($type)));
        if (!in_array($normalized, ['Teaching', 'Support'], true)) {
            throw new ApiException('Staff type must be Teaching or Support.', 422);
        }

        return $normalized;
    }

    protected function normalizeFirstAid(array &$data): void
    {
        if (!array_key_exists('firstAidQualified', $data) || $data['firstAidQualified'] === 'Y') {
            return;
        }

        $data['firstAidQualification'] = null;
        $data['firstAidExpiry'] = null;
    }

    protected function syncPersonDates(string $gibbonPersonID, array $body): void
    {
        $dates = RestTable::pick($body, ['dateStart', 'dateEnd']);
        if ($dates === []) {
            return;
        }

        $dates = RestTable::emptyToNull($dates, ['dateStart', 'dateEnd']);
        $this->users->update($gibbonPersonID, $dates);
    }
}
