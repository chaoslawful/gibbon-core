<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

namespace Gibbon\Module\API\Controllers;

use Gibbon\Module\API\Http\Json;
use Gibbon\Module\API\Services\AttendanceReportService;
use Gibbon\Module\API\Services\AttendanceService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AttendanceController
{
    public function __construct(
        protected AttendanceService $service,
        protected AttendanceReportService $reports
    ) {
    }

    public function codes(Request $request, Response $response): Response
    {
        return Json::write($response, ['data' => $this->service->listCodes()]);
    }

    public function showCode(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->service->getCode($args['id']));
    }

    public function storeCode(Request $request, Response $response): Response
    {
        return Json::write($response, $this->service->createCode($this->body($request)), 201);
    }

    public function updateCode(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->service->updateCode($args['id'], $this->body($request)));
    }

    public function destroyCode(Request $request, Response $response, array $args): Response
    {
        $this->service->deleteCode($args['id']);

        return Json::empty($response);
    }

    public function classSheet(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->service->getClassSheet($args['id'], $request->getQueryParams()));
    }

    public function takeClass(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->service->takeClassAttendance($args['id'], $this->body($request)));
    }

    public function formGroupSheet(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->service->getFormGroupSheet($args['id'], $request->getQueryParams()));
    }

    public function takeFormGroup(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->service->takeFormGroupAttendance($args['id'], $this->body($request)));
    }

    public function personSheet(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->service->getPersonSheet($args['id'], $request->getQueryParams()));
    }

    public function takePerson(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->service->takePersonAttendance($args['id'], $this->body($request)));
    }

    public function studentHistory(Request $request, Response $response): Response
    {
        return Json::write($response, $this->reports->studentHistory($request->getQueryParams()));
    }

    public function consecutiveAbsences(Request $request, Response $response): Response
    {
        return Json::write($response, $this->reports->consecutiveAbsences($request->getQueryParams()));
    }

    public function notPresent(Request $request, Response $response): Response
    {
        return Json::write($response, $this->reports->notPresent($request->getQueryParams()));
    }

    public function notOnsite(Request $request, Response $response): Response
    {
        return Json::write($response, $this->reports->notOnsite($request->getQueryParams()));
    }

    public function notInClass(Request $request, Response $response): Response
    {
        return Json::write($response, $this->reports->notInClass($request->getQueryParams()));
    }

    public function formGroupsNotRegistered(Request $request, Response $response): Response
    {
        return Json::write($response, $this->reports->formGroupsNotRegistered($request->getQueryParams()));
    }

    public function classesNotRegistered(Request $request, Response $response): Response
    {
        return Json::write($response, $this->reports->classesNotRegistered($request->getQueryParams()));
    }

    public function trends(Request $request, Response $response): Response
    {
        return Json::write($response, $this->reports->trends($request->getQueryParams()));
    }

    protected function body(Request $request): array
    {
        $body = $request->getParsedBody();
        return is_array($body) ? $body : [];
    }
}
