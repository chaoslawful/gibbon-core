<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

namespace Gibbon\Module\API\Controllers;

use Gibbon\Module\API\Http\Json;
use Gibbon\Module\API\Services\PlannerLessonService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class PlannerCoverageController
{
    protected PlannerLessonService $service;

    public function __construct(PlannerLessonService $service)
    {
        $this->service = $service;
    }

    public function slots(Request $request, Response $response, array $args): Response
    {
        $query = $request->getQueryParams();
        return Json::write($response, [
            'data' => $this->service->slots($args['classId'], $query['from'] ?? null, $query['to'] ?? null),
        ]);
    }

    public function coverage(Request $request, Response $response, array $args): Response
    {
        $query = $request->getQueryParams();
        return Json::write($response, $this->service->coverage($args['classId'], $query['from'] ?? null, $query['to'] ?? null));
    }
}
