<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

namespace Gibbon\Module\API\Controllers;

use Gibbon\Module\API\Http\Json;
use Gibbon\Module\API\Services\SchoolStructureService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SchoolStructureController
{
    public function __construct(protected SchoolStructureService $service)
    {
    }

    public function yearGroups(Request $request, Response $response): Response
    {
        return Json::write($response, ['data' => $this->service->listYearGroups()]);
    }

    public function storeYearGroup(Request $request, Response $response): Response
    {
        return Json::write($response, $this->service->createYearGroup($this->body($request)), 201);
    }

    public function updateYearGroup(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->service->updateYearGroup($args['id'], $this->body($request)));
    }

    public function destroyYearGroup(Request $request, Response $response, array $args): Response
    {
        $this->service->deleteYearGroup($args['id']);
        return Json::empty($response);
    }

    public function departments(Request $request, Response $response): Response
    {
        return Json::write($response, ['data' => $this->service->listDepartments()]);
    }

    public function storeDepartment(Request $request, Response $response): Response
    {
        return Json::write($response, $this->service->createDepartment($this->body($request)), 201);
    }

    public function updateDepartment(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->service->updateDepartment($args['id'], $this->body($request)));
    }

    public function destroyDepartment(Request $request, Response $response, array $args): Response
    {
        $this->service->deleteDepartment($args['id']);
        return Json::empty($response);
    }

    public function houses(Request $request, Response $response): Response
    {
        return Json::write($response, ['data' => $this->service->listHouses()]);
    }

    public function storeHouse(Request $request, Response $response): Response
    {
        return Json::write($response, $this->service->createHouse($this->body($request)), 201);
    }

    public function updateHouse(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->service->updateHouse($args['id'], $this->body($request)));
    }

    public function destroyHouse(Request $request, Response $response, array $args): Response
    {
        $this->service->deleteHouse($args['id']);
        return Json::empty($response);
    }

    public function formGroups(Request $request, Response $response): Response
    {
        $year = $request->getQueryParams()['gibbonSchoolYearID'] ?? null;
        return Json::write($response, ['data' => $this->service->listFormGroups($year)]);
    }

    public function storeFormGroup(Request $request, Response $response): Response
    {
        return Json::write($response, $this->service->createFormGroup($this->body($request)), 201);
    }

    public function updateFormGroup(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->service->updateFormGroup($args['id'], $this->body($request)));
    }

    public function destroyFormGroup(Request $request, Response $response, array $args): Response
    {
        $this->service->deleteFormGroup($args['id']);
        return Json::empty($response);
    }

    public function spacesAdmin(Request $request, Response $response): Response
    {
        return Json::write($response, ['data' => $this->service->listSpaces()]);
    }

    public function storeSpace(Request $request, Response $response): Response
    {
        return Json::write($response, $this->service->createSpace($this->body($request)), 201);
    }

    public function updateSpace(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->service->updateSpace($args['id'], $this->body($request)));
    }

    public function destroySpace(Request $request, Response $response, array $args): Response
    {
        $this->service->deleteSpace($args['id']);
        return Json::empty($response);
    }

    public function schoolYears(Request $request, Response $response): Response
    {
        return Json::write($response, ['data' => $this->service->listSchoolYears()]);
    }

    public function storeSchoolYear(Request $request, Response $response): Response
    {
        return Json::write($response, $this->service->createSchoolYear($this->body($request)), 201);
    }

    public function updateSchoolYear(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->service->updateSchoolYear($args['id'], $this->body($request)));
    }

    public function destroySchoolYear(Request $request, Response $response, array $args): Response
    {
        $this->service->deleteSchoolYear($args['id']);
        return Json::empty($response);
    }

    public function terms(Request $request, Response $response): Response
    {
        $year = $request->getQueryParams()['gibbonSchoolYearID'] ?? null;
        return Json::write($response, ['data' => $this->service->listTerms($year)]);
    }

    public function storeTerm(Request $request, Response $response): Response
    {
        return Json::write($response, $this->service->createTerm($this->body($request)), 201);
    }

    public function updateTerm(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->service->updateTerm($args['id'], $this->body($request)));
    }

    public function destroyTerm(Request $request, Response $response, array $args): Response
    {
        $this->service->deleteTerm($args['id']);
        return Json::empty($response);
    }

    public function specialDays(Request $request, Response $response): Response
    {
        return Json::write($response, ['data' => $this->service->listSpecialDays($request->getQueryParams())]);
    }

    public function storeSpecialDay(Request $request, Response $response): Response
    {
        return Json::write($response, $this->service->createSpecialDay($this->body($request)), 201);
    }

    public function updateSpecialDay(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->service->updateSpecialDay($args['id'], $this->body($request)));
    }

    public function destroySpecialDay(Request $request, Response $response, array $args): Response
    {
        $this->service->deleteSpecialDay($args['id']);
        return Json::empty($response);
    }

    protected function body(Request $request): array
    {
        $body = $request->getParsedBody();
        return is_array($body) ? $body : [];
    }
}
