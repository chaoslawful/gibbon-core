<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

namespace Gibbon\Module\API\Services;

use Gibbon\Contracts\Services\Session;
use Gibbon\Domain\Timetable\CourseClassGateway;
use Gibbon\Domain\Timetable\CourseEnrolmentGateway;
use Gibbon\Domain\Timetable\CourseGateway;
use Gibbon\Module\API\Auth\PermissionMapper;
use Gibbon\Module\API\Support\RestTable;

class CourseAdminService
{
    public function __construct(
        protected PermissionMapper $permissions,
        protected CourseGateway $courses,
        protected CourseClassGateway $classes,
        protected CourseEnrolmentGateway $enrolment,
        protected Session $session
    ) {
    }

    public function getCourse(string $id): array
    {
        $this->permissions->assertCanManageCourses();
        return RestTable::requireRow($this->courses, $id, 'Course not found.');
    }

    public function createCourse(array $body): array
    {
        $this->permissions->assertCanManageCourses();
        $data = RestTable::pick($body, [
            'gibbonSchoolYearID', 'gibbonDepartmentID', 'name', 'nameShort', 'description',
            'gibbonYearGroupIDList', 'orderBy', 'map',
        ]);
        if (empty($data['gibbonSchoolYearID'])) {
            $data['gibbonSchoolYearID'] = $this->session->get('gibbonSchoolYearID');
        }
        RestTable::requireFields($data, ['name', 'nameShort']);
        $data['description'] = $data['description'] ?? '';
        $data['gibbonYearGroupIDList'] = $data['gibbonYearGroupIDList'] ?? '';
        $data['orderBy'] = $data['orderBy'] ?? 0;
        $data['map'] = $data['map'] ?? 'Y';
        $data = RestTable::emptyToNull($data, ['gibbonDepartmentID']);
        return RestTable::create($this->courses, $data);
    }

    public function updateCourse(string $id, array $body): array
    {
        $this->permissions->assertCanManageCourses();
        $data = RestTable::pick($body, [
            'gibbonSchoolYearID', 'gibbonDepartmentID', 'name', 'nameShort', 'description',
            'gibbonYearGroupIDList', 'orderBy', 'map',
        ]);
        $data = RestTable::emptyToNull($data, ['gibbonDepartmentID']);
        return RestTable::update($this->courses, $id, $data, 'Course not found.');
    }

    public function deleteCourse(string $id): void
    {
        $this->permissions->assertCanManageCourses();
        RestTable::delete($this->courses, $id, 'Course not found.');
    }

    public function listClasses(string $courseId): array
    {
        $this->permissions->assertCanManageCourses();
        RestTable::requireRow($this->courses, $courseId, 'Course not found.');
        return $this->classes->selectBy(['gibbonCourseID' => $courseId])->fetchAll();
    }

    public function createClass(string $courseId, array $body): array
    {
        $this->permissions->assertCanManageCourses();
        RestTable::requireRow($this->courses, $courseId, 'Course not found.');
        $data = RestTable::pick($body, ['name', 'nameShort', 'reportable', 'attendance', 'enrolmentMin', 'enrolmentMax']);
        RestTable::requireFields($data, ['name', 'nameShort']);
        $data['gibbonCourseID'] = $courseId;
        $data['reportable'] = $data['reportable'] ?? 'Y';
        $data['attendance'] = $data['attendance'] ?? 'Y';
        return RestTable::create($this->classes, $data);
    }

    public function updateClass(string $id, array $body): array
    {
        $this->permissions->assertCanManageCourses();
        $data = RestTable::pick($body, ['name', 'nameShort', 'reportable', 'attendance', 'enrolmentMin', 'enrolmentMax', 'gibbonCourseID']);
        return RestTable::update($this->classes, $id, $data, 'Class not found.');
    }

    public function deleteClass(string $id): void
    {
        $this->permissions->assertCanManageCourses();
        RestTable::delete($this->classes, $id, 'Class not found.');
    }

    public function listEnrolment(string $classId): array
    {
        $this->permissions->assertCanManageEnrolment();
        RestTable::requireRow($this->classes, $classId, 'Class not found.');
        $yearID = $this->session->get('gibbonSchoolYearID');
        $criteria = $this->enrolment->newQueryCriteria()->pageSize(0);
        return $this->enrolment->queryCourseEnrolmentByClass($criteria, $yearID, $classId, false, true)->toArray();
    }

    public function createEnrolment(string $classId, array $body): array
    {
        $this->permissions->assertCanManageEnrolment();
        RestTable::requireRow($this->classes, $classId, 'Class not found.');
        $data = RestTable::pick($body, ['gibbonPersonID', 'role', 'reportable']);
        RestTable::requireFields($data, ['gibbonPersonID']);
        $data['gibbonCourseClassID'] = $classId;
        $data['role'] = $data['role'] ?? 'Student';
        $data['reportable'] = $data['reportable'] ?? 'Y';
        return RestTable::create($this->enrolment, $data);
    }

    public function updateEnrolment(string $id, array $body): array
    {
        $this->permissions->assertCanManageEnrolment();
        $data = RestTable::pick($body, ['role', 'reportable', 'gibbonPersonID', 'gibbonCourseClassID']);
        return RestTable::update($this->enrolment, $id, $data, 'Enrolment record not found.');
    }

    public function deleteEnrolment(string $id): void
    {
        $this->permissions->assertCanManageEnrolment();
        RestTable::delete($this->enrolment, $id, 'Enrolment record not found.');
    }
}
