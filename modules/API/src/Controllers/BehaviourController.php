<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

namespace Gibbon\Module\API\Controllers;

use Gibbon\Module\API\Http\Json;
use Gibbon\Module\API\Services\BehaviourService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class BehaviourController
{
    public function __construct(protected BehaviourService $service)
    {
    }

    protected function body(Request $request): array
    {
        $body = $request->getParsedBody();

        return is_array($body) ? $body : [];
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
        return Json::write($response, $this->service->create($this->body($request)), 201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->service->update($args['id'], $this->body($request)));
    }

    public function destroy(Request $request, Response $response, array $args): Response
    {
        $this->service->delete($args['id']);

        return Json::empty($response);
    }

    public function followUp(Request $request, Response $response, array $args): Response
    {
        $body = $this->body($request);

        return Json::write($response, $this->service->addFollowUp($args['id'], (string) ($body['followUp'] ?? '')), 201);
    }
}
