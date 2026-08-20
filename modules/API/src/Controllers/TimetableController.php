<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

namespace Gibbon\Module\API\Controllers;

use Gibbon\Contracts\Services\Session;
use Gibbon\Domain\Timetable\TimetableDayGateway;
use Gibbon\Domain\Timetable\TimetableGateway;
use Gibbon\Module\API\Auth\PermissionMapper;
use Gibbon\Module\API\Http\ApiException;
use Gibbon\Module\API\Http\Json;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class TimetableController
{
    protected TimetableGateway $timetableGateway;
    protected TimetableDayGateway $dayGateway;
    protected PermissionMapper $permissions;
    protected Session $session;

    public function __construct(
        TimetableGateway $timetableGateway,
        TimetableDayGateway $dayGateway,
        PermissionMapper $permissions,
        Session $session
    ) {
        $this->timetableGateway = $timetableGateway;
        $this->dayGateway = $dayGateway;
        $this->permissions = $permissions;
        $this->session = $session;
    }

    public function index(Request $request, Response $response): Response
    {
        $this->permissions->assertCanViewTimetable();
        $yearID = $request->getQueryParams()['gibbonSchoolYearID'] ?? $this->session->get('gibbonSchoolYearID');
        $rows = $this->timetableGateway->selectTimetablesBySchoolYear($yearID)->fetchAll();

        return Json::write($response, ['data' => $rows]);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $this->permissions->assertCanViewTimetable();
        $tt = $this->timetableGateway->getByID($args['id']);
        if (empty($tt)) {
            throw new ApiException('Timetable not found.', 404);
        }

        return Json::write($response, $tt);
    }

    public function days(Request $request, Response $response, array $args): Response
    {
        $this->permissions->assertCanViewTimetable();
        $this->requireTimetable($args['id']);
        $rows = $this->dayGateway->selectTTDaysByID($args['id'])->fetchAll();

        return Json::write($response, ['data' => $rows]);
    }

    public function rows(Request $request, Response $response, array $args): Response
    {
        $this->permissions->assertCanViewTimetable();
        $day = $this->dayGateway->getTTDayByID($args['dayId']);
        if (empty($day) || intval($day['gibbonTTID']) !== intval($args['id'])) {
            throw new ApiException('Timetable day not found.', 404);
        }

        $rows = $this->dayGateway->selectTTDayRowsByID($args['dayId'])->fetchAll();

        return Json::write($response, ['data' => $rows]);
    }

    public function dates(Request $request, Response $response, array $args): Response
    {
        $this->permissions->assertCanViewTimetable();
        $this->requireTimetable($args['id']);
        $query = $request->getQueryParams();
        $from = $query['from'] ?? $this->session->get('gibbonSchoolYearFirstDay');
        $to = $query['to'] ?? $this->session->get('gibbonSchoolYearLastDay');
        if (empty($from) || empty($to)) {
            throw new ApiException('from and to dates are required (YYYY-MM-DD).', 422);
        }

        $rows = $this->dayGateway->selectTTDaysByDateRange($args['id'], $from, $to)->fetchAll();

        return Json::write($response, ['data' => $rows]);
    }

    protected function requireTimetable(string $id): array
    {
        $tt = $this->timetableGateway->getByID($id);
        if (empty($tt)) {
            throw new ApiException('Timetable not found.', 404);
        }
        return $tt;
    }
}
