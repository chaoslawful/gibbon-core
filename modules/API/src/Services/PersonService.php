<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

namespace Gibbon\Module\API\Services;

use Gibbon\Contracts\Services\Session;
use Gibbon\Data\PasswordPolicy;
use Gibbon\Domain\Staff\StaffGateway;
use Gibbon\Domain\Students\StudentGateway;
use Gibbon\Domain\Timetable\CourseEnrolmentGateway;
use Gibbon\Domain\User\RoleGateway;
use Gibbon\Domain\User\UserGateway;
use Gibbon\Domain\User\UserStatusLogGateway;
use Gibbon\Module\API\Auth\PermissionMapper;
use Gibbon\Module\API\Http\ApiException;
use Gibbon\Module\API\Support\RestTable;

class PersonService
{
    protected array $personFields = [
        'title', 'surname', 'firstName', 'preferredName', 'officialName', 'nameInCharacters',
        'gender', 'username', 'status', 'canLogin', 'passwordForceReset', 'gibbonRoleIDPrimary',
        'gibbonRoleIDAll', 'dob', 'email', 'emailAlternate', 'address1', 'address1District',
        'address1Country', 'address2', 'address2District', 'address2Country', 'phone1Type',
        'phone1CountryCode', 'phone1', 'phone2Type', 'phone2CountryCode', 'phone2', 'phone3Type',
        'phone3CountryCode', 'phone3', 'phone4Type', 'phone4CountryCode', 'phone4', 'website',
        'languageFirst', 'languageSecond', 'languageThird', 'countryOfBirth', 'ethnicity',
        'religion', 'profession', 'employer', 'jobTitle', 'gibbonHouseID', 'studentID',
        'dateStart', 'dateEnd', 'gibbonSchoolYearIDClassOf', 'lastSchool', 'nextSchool',
        'departureReason', 'transport', 'transportNotes', 'lockerNumber', 'vehicleRegistration',
        'privacy', 'studentAgreements', 'dayType', 'emergency1Name', 'emergency1Number1',
        'emergency1Number2', 'emergency1Relationship', 'emergency2Name', 'emergency2Number1',
        'emergency2Number2', 'emergency2Relationship',
    ];

    public function __construct(
        protected PermissionMapper $permissions,
        protected UserGateway $users,
        protected RoleGateway $roles,
        protected StaffGateway $staff,
        protected StudentGateway $enrolment,
        protected CourseEnrolmentGateway $courseEnrolment,
        protected UserStatusLogGateway $statusLog,
        protected PasswordPolicy $passwordPolicy,
        protected Session $session
    ) {
    }

    public function list(array $query): array
    {
        $this->permissions->assertCanManageUsers();
        $criteria = $this->users->newQueryCriteria()
            ->searchBy($this->users->getSearchableColumns(), $query['q'] ?? '')
            ->pageSize((int) ($query['limit'] ?? 50));
        $rows = $this->users->queryAllUsers($criteria)->toArray();
        return array_map([$this, 'publicPerson'], $rows);
    }

    public function get(string $id): array
    {
        $this->permissions->assertCanManageUsers();
        return $this->publicPerson(RestTable::requireRow($this->users, $id, 'Person not found.'));
    }

    public function create(array $body): array
    {
        $this->permissions->assertCanManageUsers();
        $data = RestTable::pick($body, $this->personFields);
        RestTable::requireFields($data, ['surname', 'firstName', 'preferredName', 'officialName', 'gender', 'username', 'gibbonRoleIDPrimary']);
        $data['status'] = $data['status'] ?? 'Full';
        $data['canLogin'] = $data['canLogin'] ?? 'Y';
        $data['passwordForceReset'] = $data['passwordForceReset'] ?? 'N';
        $data['gibbonRoleIDAll'] = $data['gibbonRoleIDAll'] ?? $data['gibbonRoleIDPrimary'];
        $data = RestTable::emptyToNull($data, ['gibbonHouseID', 'gibbonSchoolYearIDClassOf', 'dob', 'dateStart', 'dateEnd']);
        $data = RestTable::defaults($data, [
            'title' => '', 'nameInCharacters' => '', 'address1' => '', 'address1District' => '',
            'address1Country' => '', 'address2' => '', 'address2District' => '', 'address2Country' => '',
            'phone1CountryCode' => '', 'phone1' => '', 'phone2CountryCode' => '', 'phone2' => '',
            'phone3CountryCode' => '', 'phone3' => '', 'phone4CountryCode' => '', 'phone4' => '',
            'website' => '', 'languageFirst' => '', 'languageSecond' => '', 'languageThird' => '',
            'countryOfBirth' => '', 'ethnicity' => '', 'religion' => '', 'profession' => '',
            'employer' => '', 'jobTitle' => '', 'studentID' => '', 'lastSchool' => '', 'nextSchool' => '',
            'departureReason' => '', 'transport' => '', 'transportNotes' => '', 'lockerNumber' => '',
            'vehicleRegistration' => '', 'emergency1Name' => '', 'emergency1Number1' => '',
            'emergency1Number2' => '', 'emergency1Relationship' => '', 'emergency2Name' => '',
            'emergency2Number1' => '', 'emergency2Number2' => '', 'emergency2Relationship' => '',
            'googleAPIRefreshToken' => '', 'microsoftAPIRefreshToken' => '', 'genericAPIRefreshToken' => '',
            'personalBackground' => '', 'calendarFeedPersonal' => '', 'birthCertificateScan' => '',
            'fields' => '',
        ]);

        $taken = $this->users->selectBy(['username' => $data['username']])->fetch();
        if (!empty($taken)) {
            throw new ApiException('Username is already in use.', 422);
        }

        $plain = $body['password'] ?? '';
        $generated = false;
        if ($plain === '') {
            $plain = bin2hex(random_bytes(8));
            $generated = true;
        }
        $this->assertPassword($plain);
        $salt = getSalt();
        $data['passwordStrongSalt'] = $salt;
        $data['passwordStrong'] = hash('sha256', $salt.$plain);

        $person = RestTable::create($this->users, $data);
        $this->statusLog->insert([
            'gibbonPersonID' => $person['gibbonPersonID'],
            'statusOld' => $data['status'],
            'statusNew' => $data['status'],
            'reason' => 'Created',
            'gibbonPersonIDModified' => $this->session->get('gibbonPersonID'),
        ]);

        if (($body['staffRecord'] ?? 'N') === 'Y') {
            $this->staff->insert([
                'gibbonPersonID' => $person['gibbonPersonID'],
                'jobTitle' => $body['jobTitle'] ?? ($data['jobTitle'] ?? ''),
                'type' => $body['staffType'] ?? 'Teaching',
                'countryOfOrigin' => '',
                'qualifications' => '',
                'biography' => '',
                'biographicalGrouping' => '',
                'biographicalGroupingPriority' => 0,
            ]);
        }

        if (($body['studentRecord'] ?? 'N') === 'Y') {
            $this->insertEnrolment($person['gibbonPersonID'], $body);
        }

        $out = $this->publicPerson($this->users->getByID($person['gibbonPersonID']));
        if ($generated) {
            $out['generatedPassword'] = $plain;
        }
        return $out;
    }

    public function update(string $id, array $body): array
    {
        $this->permissions->assertCanManageUsers();
        $data = RestTable::pick($body, $this->personFields);
        unset($data['passwordStrong'], $data['passwordStrongSalt']);
        $data = RestTable::emptyToNull($data, ['gibbonHouseID', 'gibbonSchoolYearIDClassOf', 'dob', 'dateStart', 'dateEnd']);
        $person = RestTable::update($this->users, $id, $data, 'Person not found.');
        return $this->publicPerson($person);
    }

    public function delete(string $id): void
    {
        $this->permissions->assertCanManageUsers();
        RestTable::delete($this->users, $id, 'Person not found.');
    }

    public function resetPassword(string $id, array $body): array
    {
        $this->permissions->assertCanManageUsers();
        RestTable::requireRow($this->users, $id, 'Person not found.');
        $plain = $body['password'] ?? '';
        $generated = false;
        if ($plain === '') {
            $plain = bin2hex(random_bytes(8));
            $generated = true;
        }
        $this->assertPassword($plain);
        $salt = getSalt();
        $this->users->update($id, [
            'passwordStrongSalt' => $salt,
            'passwordStrong' => hash('sha256', $salt.$plain),
            'passwordForceReset' => $body['passwordForceReset'] ?? 'N',
            'failCount' => 0,
        ]);
        $out = ['gibbonPersonID' => $id, 'reset' => true];
        if ($generated) {
            $out['generatedPassword'] = $plain;
        }
        return $out;
    }

    public function listRoles(): array
    {
        $this->permissions->assertAllows('User Admin', 'role_manage', 'You do not have permission to manage roles.');
        $criteria = $this->roles->newQueryCriteria()->sortBy('name')->pageSize(0);
        return $this->roles->queryRoles($criteria)->toArray();
    }

    public function createRole(array $body): array
    {
        $this->permissions->assertAllows('User Admin', 'role_manage', 'You do not have permission to manage roles.');
        $data = RestTable::pick($body, ['category', 'name', 'nameShort', 'description', 'type', 'canLoginRole', 'pastYearsLogin', 'futureYearsLogin']);
        RestTable::requireFields($data, ['category', 'name', 'nameShort']);
        $data = RestTable::defaults($data, [
            'description' => '',
            'type' => 'Additional',
            'canLoginRole' => 'Y',
            'pastYearsLogin' => 'Y',
            'futureYearsLogin' => 'Y',
        ]);
        return RestTable::create($this->roles, $data);
    }

    public function updateRole(string $id, array $body): array
    {
        $this->permissions->assertAllows('User Admin', 'role_manage', 'You do not have permission to manage roles.');
        $data = RestTable::pick($body, ['category', 'name', 'nameShort', 'description', 'type', 'canLoginRole', 'pastYearsLogin', 'futureYearsLogin']);
        return RestTable::update($this->roles, $id, $data, 'Role not found.');
    }

    public function deleteRole(string $id): void
    {
        $this->permissions->assertAllows('User Admin', 'role_manage', 'You do not have permission to manage roles.');
        RestTable::delete($this->roles, $id, 'Role not found.');
    }

    public function listEnrolment(array $query): array
    {
        $this->permissions->assertAllows('Admissions', 'studentEnrolment_manage', 'You do not have permission to manage student enrolment.');
        $yearID = $query['gibbonSchoolYearID'] ?? $this->session->get('gibbonSchoolYearID');
        $criteria = $this->enrolment->newQueryCriteria()->pageSize(0);
        if (!empty($query['q'])) {
            $criteria->searchBy($this->enrolment->getSearchableColumns(), $query['q']);
        }
        $rows = $this->enrolment->queryStudentsBySchoolYear($criteria, $yearID)->toArray();
        if (!empty($query['gibbonFormGroupID'])) {
            $rows = array_values(array_filter($rows, fn ($row) => (string) ($row['gibbonFormGroupID'] ?? '') === (string) $query['gibbonFormGroupID']));
        }
        return $rows;
    }

    public function listPersonEnrolment(string $personId): array
    {
        $this->permissions->assertAllows('Admissions', 'studentEnrolment_manage', 'You do not have permission to manage student enrolment.');
        RestTable::requireRow($this->users, $personId, 'Person not found.');
        return $this->enrolment->selectBy(['gibbonPersonID' => $personId])->fetchAll();
    }

    public function createEnrolment(string $personId, array $body): array
    {
        $this->permissions->assertAllows('Admissions', 'studentEnrolment_manage', 'You do not have permission to manage student enrolment.');
        RestTable::requireRow($this->users, $personId, 'Person not found.');
        return $this->insertEnrolment($personId, $body);
    }

    protected function insertEnrolment(string $personId, array $body): array
    {
        $data = RestTable::pick($body, ['gibbonSchoolYearID', 'gibbonYearGroupID', 'gibbonFormGroupID', 'rollOrder']);
        if (empty($data['gibbonSchoolYearID'])) {
            $data['gibbonSchoolYearID'] = $this->session->get('gibbonSchoolYearID');
        }
        RestTable::requireFields($data, ['gibbonYearGroupID', 'gibbonFormGroupID']);
        $data['gibbonPersonID'] = $personId;
        $data = RestTable::emptyToNull($data, ['rollOrder']);

        $existing = $this->enrolment->selectBy([
            'gibbonPersonID' => $personId,
            'gibbonSchoolYearID' => $data['gibbonSchoolYearID'],
        ])->fetch();
        if (!empty($existing)) {
            throw new ApiException('This person is already enrolled in that school year.', 422);
        }

        $row = RestTable::create($this->enrolment, $data);
        if (($body['autoEnrolStudent'] ?? 'N') === 'Y') {
            $this->courseEnrolment->insertAutomaticCourseEnrolments($data['gibbonFormGroupID'], $personId);
        }
        return $row;
    }

    public function updateEnrolment(string $id, array $body): array
    {
        $this->permissions->assertAllows('Admissions', 'studentEnrolment_manage', 'You do not have permission to manage student enrolment.');
        $data = RestTable::pick($body, ['gibbonSchoolYearID', 'gibbonYearGroupID', 'gibbonFormGroupID', 'rollOrder']);
        $data = RestTable::emptyToNull($data, ['rollOrder']);
        return RestTable::update($this->enrolment, $id, $data, 'Student enrolment not found.');
    }

    public function deleteEnrolment(string $id): void
    {
        $this->permissions->assertAllows('Admissions', 'studentEnrolment_manage', 'You do not have permission to manage student enrolment.');
        RestTable::delete($this->enrolment, $id, 'Student enrolment not found.');
    }

    protected function assertPassword(string $password): void
    {
        if (!$this->passwordPolicy->validate($password)) {
            throw new ApiException('Password does not meet school password policy.', 422);
        }
    }

    protected function publicPerson(array $row): array
    {
        unset($row['password'], $row['passwordStrong'], $row['passwordStrongSalt']);
        return $row;
    }
}
