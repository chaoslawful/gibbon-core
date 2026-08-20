<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

namespace Gibbon\Module\API\Controllers;

use Gibbon\Contracts\Services\Session;
use Gibbon\Domain\School\FacilityGateway;
use Gibbon\Domain\Timetable\CourseGateway;
use Gibbon\Module\API\Auth\PermissionMapper;
use Gibbon\Module\API\Http\Json;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class CourseController
{
    protected Session $session;
    protected CourseGateway $courseGateway;
    protected FacilityGateway $facilityGateway;
    protected PermissionMapper $permissions;

    public function __construct(
        Session $session,
        CourseGateway $courseGateway,
        FacilityGateway $facilityGateway,
        PermissionMapper $permissions
    ) {
        $this->session = $session;
        $this->courseGateway = $courseGateway;
        $this->facilityGateway = $facilityGateway;
        $this->permissions = $permissions;
    }

    public function schoolYear(Request $request, Response $response): Response
    {
        return Json::write($response, [
            'gibbonSchoolYearID' => $this->session->get('gibbonSchoolYearID'),
            'gibbonSchoolYearName' => $this->session->get('gibbonSchoolYearName'),
            'firstDay' => $this->session->get('gibbonSchoolYearFirstDay'),
            'lastDay' => $this->session->get('gibbonSchoolYearLastDay'),
        ]);
    }

    public function courses(Request $request, Response $response): Response
    {
        $this->assertDiscovery();
        $yearID = $request->getQueryParams()['gibbonSchoolYearID'] ?? $this->session->get('gibbonSchoolYearID');
        $personID = $this->session->get('gibbonPersonID');

        if ($this->permissions->canViewAllPlannerClasses() || $this->permissions->canManageTimetables()) {
            $rows = $this->courseGateway->selectCourseListBySchoolYear($yearID)->fetchAll();
        } else {
            $rows = $this->courseGateway->selectCourseListBySchoolYearAndPerson($yearID, $personID)->fetchAll();
        }

        return Json::write($response, ['data' => $this->mapValueName($rows)]);
    }

    public function classes(Request $request, Response $response): Response
    {
        $this->assertDiscovery();
        $yearID = $request->getQueryParams()['gibbonSchoolYearID'] ?? $this->session->get('gibbonSchoolYearID');
        $personID = $this->session->get('gibbonPersonID');

        if ($this->permissions->canViewAllPlannerClasses() || $this->permissions->canManageTimetables()) {
            $rows = $this->courseGateway->selectClassListBySchoolYear($yearID)->fetchAll();
        } else {
            $rows = $this->courseGateway->selectClassListBySchoolYearAndPerson($yearID, $personID)->fetchAll();
        }

        return Json::write($response, ['data' => $this->mapValueName($rows)]);
    }

    public function spaces(Request $request, Response $response): Response
    {
        $this->permissions->assertCanManageTimetables();
        $criteria = $this->facilityGateway->newQueryCriteria()->sortBy('name')->pageSize(0);
        $rows = $this->facilityGateway->queryFacilities($criteria)->toArray();

        $data = array_map(function ($row) {
            return [
                'gibbonSpaceID' => $row['gibbonSpaceID'] ?? null,
                'name' => $row['name'] ?? null,
                'type' => $row['type'] ?? null,
            ];
        }, $rows);

        return Json::write($response, ['data' => $data]);
    }

    protected function assertDiscovery(): void
    {
        if (!$this->permissions->canViewPlanner() && !$this->permissions->canViewTimetable()) {
            throw new \Gibbon\Module\API\Http\ApiException('You do not have permission to list courses or classes.', 403);
        }
    }

    protected function mapValueName(array $rows): array
    {
        return array_map(function ($row) {
            return [
                'id' => $row['value'] ?? $row['gibbonCourseID'] ?? $row['gibbonCourseClassID'] ?? null,
                'name' => $row['name'] ?? null,
            ];
        }, $rows);
    }
}
