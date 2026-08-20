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

class PlannerLessonController
{
    protected PlannerLessonService $service;

    public function __construct(PlannerLessonService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request, Response $response): Response
    {
        return Json::write($response, ['data' => $this->service->list($request->getQueryParams())]);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->service->get($args['id']));
    }

    public function store(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody() ?? [];
        return Json::write($response, $this->service->create(is_array($body) ? $body : []), 201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $body = $request->getParsedBody() ?? [];
        return Json::write($response, $this->service->update($args['id'], is_array($body) ? $body : []));
    }

    public function destroy(Request $request, Response $response, array $args): Response
    {
        $this->service->delete($args['id']);
        return Json::empty($response, 204);
    }

    public function units(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams();
        return Json::write($response, ['data' => $this->service->units($query['gibbonCourseID'] ?? null)]);
    }
}
