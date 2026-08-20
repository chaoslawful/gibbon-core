<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

namespace Gibbon\Module\API\Services;

use Gibbon\Domain\Departments\DepartmentGateway;
use Gibbon\Domain\FormGroups\FormGroupGateway;
use Gibbon\Domain\School\FacilityGateway;
use Gibbon\Domain\School\HouseGateway;
use Gibbon\Domain\School\SchoolYearGateway;
use Gibbon\Domain\School\SchoolYearSpecialDayGateway;
use Gibbon\Domain\School\SchoolYearTermGateway;
use Gibbon\Domain\School\YearGroupGateway;
use Gibbon\Module\API\Auth\PermissionMapper;
use Gibbon\Module\API\Http\ApiException;
use Gibbon\Module\API\Support\RestTable;

class SchoolStructureService
{
    public function __construct(
        protected PermissionMapper $permissions,
        protected YearGroupGateway $yearGroups,
        protected DepartmentGateway $departments,
        protected HouseGateway $houses,
        protected FormGroupGateway $formGroups,
        protected FacilityGateway $spaces,
        protected SchoolYearGateway $schoolYears,
        protected SchoolYearTermGateway $terms,
        protected SchoolYearSpecialDayGateway $specialDays
    ) {
    }

    public function listYearGroups(): array
    {
        $this->permissions->assertAllows('School Admin', 'yearGroup_manage', 'You do not have permission to manage year groups.');
        $criteria = $this->yearGroups->newQueryCriteria()->sortBy('sequenceNumber')->pageSize(0);
        return $this->yearGroups->queryYearGroups($criteria)->toArray();
    }

    public function createYearGroup(array $body): array
    {
        $this->permissions->assertAllows('School Admin', 'yearGroup_manage', 'You do not have permission to manage year groups.');
        $data = RestTable::pick($body, ['name', 'nameShort', 'sequenceNumber', 'gibbonPersonIDHOY']);
        RestTable::requireFields($data, ['name', 'nameShort', 'sequenceNumber']);
        $data = RestTable::emptyToNull($data, ['gibbonPersonIDHOY']);
        return RestTable::create($this->yearGroups, $data);
    }

    public function updateYearGroup(string $id, array $body): array
    {
        $this->permissions->assertAllows('School Admin', 'yearGroup_manage', 'You do not have permission to manage year groups.');
        $data = RestTable::pick($body, ['name', 'nameShort', 'sequenceNumber', 'gibbonPersonIDHOY']);
        $data = RestTable::emptyToNull($data, ['gibbonPersonIDHOY']);
        return RestTable::update($this->yearGroups, $id, $data, 'Year group not found.');
    }

    public function deleteYearGroup(string $id): void
    {
        $this->permissions->assertAllows('School Admin', 'yearGroup_manage', 'You do not have permission to manage year groups.');
        RestTable::delete($this->yearGroups, $id, 'Year group not found.');
    }

    public function listDepartments(): array
    {
        $this->permissions->assertAllows('School Admin', 'department_manage', 'You do not have permission to manage departments.');
        $criteria = $this->departments->newQueryCriteria()->sortBy('name')->pageSize(0);
        return $this->departments->queryDepartments($criteria)->toArray();
    }

    public function createDepartment(array $body): array
    {
        $this->permissions->assertAllows('School Admin', 'department_manage', 'You do not have permission to manage departments.');
        $data = RestTable::pick($body, ['name', 'nameShort', 'type', 'subjectListing', 'blurb']);
        RestTable::requireFields($data, ['name', 'nameShort']);
        $data = RestTable::defaults($data, [
            'type' => 'Learning Area',
            'subjectListing' => '',
            'blurb' => '',
            'logo' => '',
        ]);
        return RestTable::create($this->departments, $data);
    }

    public function updateDepartment(string $id, array $body): array
    {
        $this->permissions->assertAllows('School Admin', 'department_manage', 'You do not have permission to manage departments.');
        $data = RestTable::pick($body, ['name', 'nameShort', 'type', 'subjectListing', 'blurb']);
        return RestTable::update($this->departments, $id, $data, 'Department not found.');
    }

    public function deleteDepartment(string $id): void
    {
        $this->permissions->assertAllows('School Admin', 'department_manage', 'You do not have permission to manage departments.');
        RestTable::delete($this->departments, $id, 'Department not found.');
    }

    public function listHouses(): array
    {
        $this->permissions->assertAllows('School Admin', 'house_manage', 'You do not have permission to manage houses.');
        $criteria = $this->houses->newQueryCriteria()->sortBy('name')->pageSize(0);
        return $this->houses->queryHouses($criteria)->toArray();
    }

    public function createHouse(array $body): array
    {
        $this->permissions->assertAllows('School Admin', 'house_manage', 'You do not have permission to manage houses.');
        $data = RestTable::pick($body, ['name', 'nameShort', 'logo']);
        RestTable::requireFields($data, ['name', 'nameShort']);
        $data = RestTable::defaults($data, ['logo' => '']);
        return RestTable::create($this->houses, $data);
    }

    public function updateHouse(string $id, array $body): array
    {
        $this->permissions->assertAllows('School Admin', 'house_manage', 'You do not have permission to manage houses.');
        $data = RestTable::pick($body, ['name', 'nameShort', 'logo']);
        return RestTable::update($this->houses, $id, $data, 'House not found.');
    }

    public function deleteHouse(string $id): void
    {
        $this->permissions->assertAllows('School Admin', 'house_manage', 'You do not have permission to manage houses.');
        RestTable::delete($this->houses, $id, 'House not found.');
    }

    public function listFormGroups(?string $yearID): array
    {
        $this->permissions->assertAllows('School Admin', 'formGroup_manage', 'You do not have permission to manage form groups.');
        if (empty($yearID)) {
            throw new \Gibbon\Module\API\Http\ApiException('gibbonSchoolYearID is required.', 422);
        }
        return $this->formGroups->selectBy(['gibbonSchoolYearID' => $yearID])->fetchAll();
    }

    public function createFormGroup(array $body): array
    {
        $this->permissions->assertAllows('School Admin', 'formGroup_manage', 'You do not have permission to manage form groups.');
        $data = RestTable::pick($body, [
            'gibbonSchoolYearID', 'name', 'nameShort', 'gibbonPersonIDTutor', 'gibbonPersonIDTutor2',
            'gibbonPersonIDTutor3', 'gibbonPersonIDEA', 'gibbonPersonIDEA2', 'gibbonPersonIDEA3',
            'gibbonSpaceID', 'website', 'attendance',
        ]);
        RestTable::requireFields($data, ['gibbonSchoolYearID', 'name', 'nameShort']);
        $data = RestTable::defaults($data, ['website' => '', 'attendance' => 'Y']);
        $data = RestTable::emptyToNull($data, [
            'gibbonPersonIDTutor', 'gibbonPersonIDTutor2', 'gibbonPersonIDTutor3',
            'gibbonPersonIDEA', 'gibbonPersonIDEA2', 'gibbonPersonIDEA3', 'gibbonSpaceID',
        ]);
        return RestTable::create($this->formGroups, $data);
    }

    public function updateFormGroup(string $id, array $body): array
    {
        $this->permissions->assertAllows('School Admin', 'formGroup_manage', 'You do not have permission to manage form groups.');
        $data = RestTable::pick($body, [
            'gibbonSchoolYearID', 'name', 'nameShort', 'gibbonPersonIDTutor', 'gibbonPersonIDTutor2',
            'gibbonPersonIDTutor3', 'gibbonPersonIDEA', 'gibbonPersonIDEA2', 'gibbonPersonIDEA3',
            'gibbonSpaceID', 'website', 'attendance',
        ]);
        $data = RestTable::emptyToNull($data, [
            'gibbonPersonIDTutor', 'gibbonPersonIDTutor2', 'gibbonPersonIDTutor3',
            'gibbonPersonIDEA', 'gibbonPersonIDEA2', 'gibbonPersonIDEA3', 'gibbonSpaceID',
        ]);
        return RestTable::update($this->formGroups, $id, $data, 'Form group not found.');
    }

    public function deleteFormGroup(string $id): void
    {
        $this->permissions->assertAllows('School Admin', 'formGroup_manage', 'You do not have permission to manage form groups.');
        RestTable::delete($this->formGroups, $id, 'Form group not found.');
    }

    public function listSpaces(): array
    {
        $this->permissions->assertAllows('School Admin', 'space_manage', 'You do not have permission to manage facilities.');
        $criteria = $this->spaces->newQueryCriteria()->sortBy('name')->pageSize(0);
        return $this->spaces->queryFacilities($criteria)->toArray();
    }

    public function createSpace(array $body): array
    {
        $this->permissions->assertAllows('School Admin', 'space_manage', 'You do not have permission to manage facilities.');
        $data = RestTable::pick($body, ['name', 'type', 'active', 'bookable', 'capacity', 'computer', 'computerStudent', 'projector', 'tv', 'dvd', 'hifi', 'speakers', 'iwb', 'phoneInternal', 'phoneExternal', 'comment']);
        RestTable::requireFields($data, ['name']);
        $data['type'] = $data['type'] ?? 'Classroom';
        $data['active'] = $data['active'] ?? 'Y';
        $data['bookable'] = $data['bookable'] ?? 'N';
        foreach (['computer', 'computerStudent', 'projector', 'tv', 'dvd', 'hifi', 'speakers', 'iwb'] as $flag) {
            $data[$flag] = $data[$flag] ?? 'N';
        }
        return RestTable::create($this->spaces, $data);
    }

    public function updateSpace(string $id, array $body): array
    {
        $this->permissions->assertAllows('School Admin', 'space_manage', 'You do not have permission to manage facilities.');
        $data = RestTable::pick($body, ['name', 'type', 'active', 'bookable', 'capacity', 'computer', 'computerStudent', 'projector', 'tv', 'dvd', 'hifi', 'speakers', 'iwb', 'phoneInternal', 'phoneExternal', 'comment']);
        return RestTable::update($this->spaces, $id, $data, 'Facility not found.');
    }

    public function deleteSpace(string $id): void
    {
        $this->permissions->assertAllows('School Admin', 'space_manage', 'You do not have permission to manage facilities.');
        RestTable::delete($this->spaces, $id, 'Facility not found.');
    }

    public function listSchoolYears(): array
    {
        $this->permissions->assertAllows('School Admin', 'schoolYear_manage', 'You do not have permission to manage school years.');
        return $this->schoolYears->selectBy([])->fetchAll();
    }

    public function createSchoolYear(array $body): array
    {
        $this->permissions->assertAllows('School Admin', 'schoolYear_manage', 'You do not have permission to manage school years.');
        $data = RestTable::pick($body, ['name', 'status', 'sequenceNumber', 'firstDay', 'lastDay']);
        RestTable::requireFields($data, ['name', 'status', 'sequenceNumber', 'firstDay', 'lastDay']);
        return RestTable::create($this->schoolYears, $data);
    }

    public function updateSchoolYear(string $id, array $body): array
    {
        $this->permissions->assertAllows('School Admin', 'schoolYear_manage', 'You do not have permission to manage school years.');
        $data = RestTable::pick($body, ['name', 'status', 'sequenceNumber', 'firstDay', 'lastDay']);
        return RestTable::update($this->schoolYears, $id, $data, 'School year not found.');
    }

    public function deleteSchoolYear(string $id): void
    {
        $this->permissions->assertAllows('School Admin', 'schoolYear_manage', 'You do not have permission to manage school years.');
        RestTable::delete($this->schoolYears, $id, 'School year not found.');
    }

    public function listTerms(?string $gibbonSchoolYearID): array
    {
        $this->permissions->assertAllows('School Admin', 'schoolYearTerm_manage', 'You do not have permission to manage terms.');
        if (empty($gibbonSchoolYearID)) {
            throw new ApiException('gibbonSchoolYearID is required.', 422);
        }
        RestTable::requireRow($this->schoolYears, $gibbonSchoolYearID, 'School year not found.');
        $criteria = $this->terms->newQueryCriteria()
            ->filterBy('schoolYear', $gibbonSchoolYearID)
            ->sortBy('sequenceNumber')
            ->pageSize(0);

        return $this->terms->querySchoolYearTerms($criteria)->toArray();
    }

    public function createTerm(array $body): array
    {
        $this->permissions->assertAllows('School Admin', 'schoolYearTerm_manage', 'You do not have permission to manage terms.');
        $data = RestTable::pick($body, ['gibbonSchoolYearID', 'name', 'nameShort', 'sequenceNumber', 'firstDay', 'lastDay']);
        RestTable::requireFields($data, ['gibbonSchoolYearID', 'name', 'nameShort', 'sequenceNumber', 'firstDay', 'lastDay']);
        if (!is_numeric($data['sequenceNumber'])) {
            throw new ApiException('sequenceNumber must be numeric.', 422);
        }
        RestTable::requireRow($this->schoolYears, $data['gibbonSchoolYearID'], 'School year not found.');
        $this->assertTermDates($data);
        if (!$this->terms->unique($data, ['sequenceNumber'])) {
            throw new ApiException('sequenceNumber must be unique across all terms.', 422);
        }

        return RestTable::create($this->terms, $data);
    }

    public function updateTerm(string $id, array $body): array
    {
        $this->permissions->assertAllows('School Admin', 'schoolYearTerm_manage', 'You do not have permission to manage terms.');
        $existing = RestTable::requireRow($this->terms, $id, 'Term not found.');
        $data = RestTable::pick($body, ['gibbonSchoolYearID', 'name', 'nameShort', 'sequenceNumber', 'firstDay', 'lastDay']);
        if (isset($data['sequenceNumber']) && !is_numeric($data['sequenceNumber'])) {
            throw new ApiException('sequenceNumber must be numeric.', 422);
        }
        if (isset($data['gibbonSchoolYearID'])) {
            RestTable::requireRow($this->schoolYears, $data['gibbonSchoolYearID'], 'School year not found.');
        }
        $merged = array_merge($existing, $data);
        $this->assertTermDates($merged);
        if (isset($data['sequenceNumber']) && !$this->terms->unique($merged, ['sequenceNumber'], $id)) {
            throw new ApiException('sequenceNumber must be unique across all terms.', 422);
        }

        return RestTable::update($this->terms, $id, $data, 'Term not found.');
    }

    public function deleteTerm(string $id): void
    {
        $this->permissions->assertAllows('School Admin', 'schoolYearTerm_manage', 'You do not have permission to manage terms.');
        RestTable::delete($this->terms, $id, 'Term not found.');
    }

    public function listSpecialDays(array $query): array
    {
        $this->permissions->assertAllows('School Admin', 'schoolYearSpecialDay_manage', 'You do not have permission to manage special days.');
        $termId = $query['gibbonSchoolYearTermID'] ?? '';
        $yearId = $query['gibbonSchoolYearID'] ?? '';
        $from = $query['from'] ?? '';
        $to = $query['to'] ?? '';

        if ($termId !== '') {
            RestTable::requireRow($this->terms, $termId, 'Term not found.');
            return $this->specialDays->selectBy(['gibbonSchoolYearTermID' => $termId])->fetchAll();
        }
        if ($from !== '' && $to !== '') {
            return $this->specialDays->selectSpecialDaysByDateRange($from, $to)->fetchAll();
        }
        if ($yearId === '') {
            throw new ApiException('Provide gibbonSchoolYearID, gibbonSchoolYearTermID, or from and to.', 422);
        }
        RestTable::requireRow($this->schoolYears, $yearId, 'School year not found.');

        $out = [];
        foreach ($this->terms->selectBy(['gibbonSchoolYearID' => $yearId])->fetchAll() as $term) {
            $out = array_merge($out, $this->specialDays->selectBy([
                'gibbonSchoolYearTermID' => $term['gibbonSchoolYearTermID'],
            ])->fetchAll());
        }
        usort($out, fn ($a, $b) => strcmp((string) $a['date'], (string) $b['date']));

        return $out;
    }

    public function createSpecialDay(array $body): array
    {
        $this->permissions->assertAllows('School Admin', 'schoolYearSpecialDay_manage', 'You do not have permission to manage special days.');
        $data = $this->specialDayData($body, true);
        if (!$this->specialDays->unique($data, ['date'])) {
            throw new ApiException('A special day already exists on this date.', 422);
        }

        return RestTable::create($this->specialDays, $data);
    }

    public function updateSpecialDay(string $id, array $body): array
    {
        $this->permissions->assertAllows('School Admin', 'schoolYearSpecialDay_manage', 'You do not have permission to manage special days.');
        $existing = RestTable::requireRow($this->specialDays, $id, 'Special day not found.');
        $data = $this->specialDayData(array_merge($existing, $body), false);
        $changed = RestTable::pick($body, array_keys($data));
        $merged = array_merge($existing, $changed);
        $this->assertSpecialDayInTerm($merged);
        if (isset($changed['date']) && !$this->specialDays->unique($merged, ['date'], $id)) {
            throw new ApiException('A special day already exists on this date.', 422);
        }

        return RestTable::update($this->specialDays, $id, $changed, 'Special day not found.');
    }

    public function deleteSpecialDay(string $id): void
    {
        $this->permissions->assertAllows('School Admin', 'schoolYearSpecialDay_manage', 'You do not have permission to manage special days.');
        RestTable::delete($this->specialDays, $id, 'Special day not found.');
    }

    protected function assertTermDates(array $data): void
    {
        if (($data['firstDay'] ?? '') > ($data['lastDay'] ?? '')) {
            throw new ApiException('firstDay must be on or before lastDay.', 422);
        }
    }

    protected function specialDayData(array $body, bool $creating): array
    {
        $data = RestTable::pick($body, [
            'date', 'type', 'name', 'description', 'gibbonSchoolYearTermID',
            'schoolOpen', 'schoolStart', 'schoolEnd', 'schoolClose',
            'gibbonYearGroupIDList', 'gibbonFormGroupIDList',
            'cancelActivities', 'cancelDuty', 'cancelBookings', 'cancelClasses', 'context',
        ]);
        if ($creating) {
            RestTable::requireFields($data, ['date', 'type', 'name', 'gibbonSchoolYearTermID']);
        }
        if (isset($data['type'])) {
            $allowed = ['School Closure', 'Timing Change', 'Off Timetable'];
            if (!in_array($data['type'], $allowed, true)) {
                throw new ApiException('type must be School Closure, Timing Change or Off Timetable.', 422);
            }
        }
        foreach (['schoolOpen', 'schoolStart', 'schoolEnd', 'schoolClose'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $this->normalizeTime($data[$field]);
            }
        }
        $data = RestTable::defaults($data, [
            'description' => '',
            'cancelActivities' => 'N',
            'cancelDuty' => 'N',
            'cancelBookings' => 'N',
            'cancelClasses' => 'N',
        ]);
        if ($creating || isset($data['gibbonSchoolYearTermID']) || isset($data['date'])) {
            $this->assertSpecialDayInTerm(array_merge(
                $creating ? $data : $body,
                $data
            ));
        }
        if (($data['type'] ?? '') === 'Off Timetable') {
            if (!empty($data['gibbonYearGroupIDList']) && empty($data['context'])) {
                $data['context'] = 'Year Group';
            } elseif (!empty($data['gibbonFormGroupIDList']) && empty($data['context'])) {
                $data['context'] = 'Form Group';
            }
        }

        return RestTable::emptyToNull($data, [
            'schoolOpen', 'schoolStart', 'schoolEnd', 'schoolClose',
            'gibbonYearGroupIDList', 'gibbonFormGroupIDList', 'context',
        ]);
    }

    protected function assertSpecialDayInTerm(array $data): void
    {
        $termId = $data['gibbonSchoolYearTermID'] ?? '';
        $date = $data['date'] ?? '';
        if ($termId === '' || $date === '') {
            return;
        }
        $term = RestTable::requireRow($this->terms, $termId, 'Term not found.');
        if ($date < $term['firstDay'] || $date > $term['lastDay']) {
            throw new ApiException('date must fall within the term firstDay and lastDay.', 422);
        }
    }

    protected function normalizeTime($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (preg_match('/^\d{2}:\d{2}$/', (string) $value)) {
            return $value.':00';
        }
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', (string) $value)) {
            return (string) $value;
        }
        throw new ApiException('Times must be HH:MM or HH:MM:SS.', 422);
    }
}
