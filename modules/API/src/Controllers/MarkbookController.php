<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

namespace Gibbon\Module\API\Controllers;

use Gibbon\Module\API\Http\Json;
use Gibbon\Module\API\Services\MarkbookService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class MarkbookController
{
    public function __construct(protected MarkbookService $service)
    {
    }

    public function scales(Request $request, Response $response): Response
    {
        return Json::write($response, ['data' => $this->service->listScales()]);
    }

    public function showScale(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->service->getScale($args['id']));
    }

    public function columns(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->service->listColumns($args['classId']));
    }

    public function storeColumn(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->service->createColumn($args['classId'], $this->body($request)), 201);
    }

    public function showColumn(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->service->getColumn($args['id']));
    }

    public function updateColumn(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->service->updateColumn($args['id'], $this->body($request)));
    }

    public function destroyColumn(Request $request, Response $response, array $args): Response
    {
        $this->service->deleteColumn($args['id']);
        return Json::empty($response);
    }

    public function entries(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->service->listEntries($args['id']));
    }

    public function saveEntries(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->service->saveEntries($args['id'], $this->body($request)));
    }

    public function uploadResponse(Request $request, Response $response, array $args): Response
    {
        $files = $request->getUploadedFiles();

        return Json::write($response, $this->service->uploadResponse($args['id'], $args['studentId'], $files['file'] ?? null));
    }

    public function downloadResponse(Request $request, Response $response, array $args): Response
    {
        $file = $this->service->downloadResponse($args['id'], $args['studentId']);

        return Json::file($response, $file['absolutePath'], $file['downloadName'], $file['contentType'], (int) $file['size']);
    }

    public function destroyResponse(Request $request, Response $response, array $args): Response
    {
        $this->service->deleteResponse($args['id'], $args['studentId']);
        return Json::empty($response);
    }

    protected function body(Request $request): array
    {
        $body = $request->getParsedBody();
        return is_array($body) ? $body : [];
    }
}
