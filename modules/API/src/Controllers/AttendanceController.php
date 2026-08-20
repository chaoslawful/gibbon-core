<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

namespace Gibbon\Module\API\Controllers;

use Gibbon\Module\API\Http\Json;
use Gibbon\Module\API\Services\AttendanceService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AttendanceController
{
    public function __construct(protected AttendanceService $service)
    {
    }

    public function codes(Request $request, Response $response): Response
    {
        return Json::write($response, ['data' => $this->service->listCodes()]);
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

    protected function body(Request $request): array
    {
        $body = $request->getParsedBody();
        return is_array($body) ? $body : [];
    }
}
