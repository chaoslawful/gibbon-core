<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

namespace Gibbon\Module\API\Controllers;

use Gibbon\Module\API\Http\ApiException;
use Gibbon\Module\API\Http\Json;
use Gibbon\Module\API\Services\CourseAdminService;
use Gibbon\Module\API\Services\FamilyService;
use Gibbon\Module\API\Services\HomeworkSubmissionService;
use Gibbon\Module\API\Services\MedicalService;
use Gibbon\Module\API\Services\PersonService;
use Gibbon\Module\API\Services\TimetableStructureService;
use Gibbon\Module\API\Services\UnitService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ExpansionController
{
    public function __construct(
        protected TimetableStructureService $timetable,
        protected CourseAdminService $courses,
        protected PersonService $people,
        protected FamilyService $families,
        protected MedicalService $medical,
        protected UnitService $units,
        protected HomeworkSubmissionService $homework
    ) {
    }

    protected function body(Request $request): array
    {
        $body = $request->getParsedBody();
        return is_array($body) ? $body : [];
    }

    public function storeTimetable(Request $request, Response $response): Response
    {
        return Json::write($response, $this->timetable->createTimetable($this->body($request)), 201);
    }

    public function updateTimetable(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->timetable->updateTimetable($args['id'], $this->body($request)));
    }

    public function destroyTimetable(Request $request, Response $response, array $args): Response
    {
        $this->timetable->deleteTimetable($args['id']);
        return Json::empty($response);
    }

    public function columns(Request $request, Response $response): Response
    {
        return Json::write($response, ['data' => $this->timetable->listColumns()]);
    }

    public function showColumn(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->timetable->getColumn($args['id']));
    }

    public function storeColumn(Request $request, Response $response): Response
    {
        return Json::write($response, $this->timetable->createColumn($this->body($request)), 201);
    }

    public function updateColumn(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->timetable->updateColumn($args['id'], $this->body($request)));
    }

    public function destroyColumn(Request $request, Response $response, array $args): Response
    {
        $this->timetable->deleteColumn($args['id']);
        return Json::empty($response);
    }

    public function storeColumnRow(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->timetable->createColumnRow($args['id'], $this->body($request)), 201);
    }

    public function updateColumnRow(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->timetable->updateColumnRow($args['id'], $this->body($request)));
    }

    public function destroyColumnRow(Request $request, Response $response, array $args): Response
    {
        $this->timetable->deleteColumnRow($args['id']);
        return Json::empty($response);
    }

    public function storeDay(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->timetable->createDay($args['id'], $this->body($request)), 201);
    }

    public function updateDay(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->timetable->updateDay($args['id'], $args['dayId'], $this->body($request)));
    }

    public function destroyDay(Request $request, Response $response, array $args): Response
    {
        $this->timetable->deleteDay($args['id'], $args['dayId']);
        return Json::empty($response);
    }

    public function storeDate(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->timetable->createDate($args['id'], $this->body($request)), 201);
    }

    public function destroyDate(Request $request, Response $response, array $args): Response
    {
        $this->timetable->deleteDate($args['id']);
        return Json::empty($response);
    }

    public function exceptions(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, ['data' => $this->timetable->listExceptions($args['id'])]);
    }

    public function storeException(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->timetable->createException($args['id'], $this->body($request)), 201);
    }

    public function destroyException(Request $request, Response $response, array $args): Response
    {
        $this->timetable->deleteException($args['id']);
        return Json::empty($response);
    }

    public function showCourse(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->courses->getCourse($args['id']));
    }

    public function storeCourse(Request $request, Response $response): Response
    {
        return Json::write($response, $this->courses->createCourse($this->body($request)), 201);
    }

    public function updateCourse(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->courses->updateCourse($args['id'], $this->body($request)));
    }

    public function destroyCourse(Request $request, Response $response, array $args): Response
    {
        $this->courses->deleteCourse($args['id']);
        return Json::empty($response);
    }

    public function courseClasses(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, ['data' => $this->courses->listClasses($args['id'])]);
    }

    public function storeClass(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->courses->createClass($args['id'], $this->body($request)), 201);
    }

    public function updateClass(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->courses->updateClass($args['id'], $this->body($request)));
    }

    public function destroyClass(Request $request, Response $response, array $args): Response
    {
        $this->courses->deleteClass($args['id']);
        return Json::empty($response);
    }

    public function enrolment(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, ['data' => $this->courses->listEnrolment($args['id'])]);
    }

    public function storeEnrolment(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->courses->createEnrolment($args['id'], $this->body($request)), 201);
    }

    public function updateEnrolment(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->courses->updateEnrolment($args['id'], $this->body($request)));
    }

    public function destroyEnrolment(Request $request, Response $response, array $args): Response
    {
        $this->courses->deleteEnrolment($args['id']);
        return Json::empty($response);
    }

    public function people(Request $request, Response $response): Response
    {
        return Json::write($response, ['data' => $this->people->list($request->getQueryParams())]);
    }

    public function showPerson(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->people->get($args['id']));
    }

    public function storePerson(Request $request, Response $response): Response
    {
        return Json::write($response, $this->people->create($this->body($request)), 201);
    }

    public function updatePerson(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->people->update($args['id'], $this->body($request)));
    }

    public function destroyPerson(Request $request, Response $response, array $args): Response
    {
        $this->people->delete($args['id']);
        return Json::empty($response);
    }

    public function resetPassword(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->people->resetPassword($args['id'], $this->body($request)));
    }

    public function personEnrolment(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, ['data' => $this->people->listPersonEnrolment($args['id'])]);
    }

    public function storePersonEnrolment(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->people->createEnrolment($args['id'], $this->body($request)), 201);
    }

    public function studentEnrolments(Request $request, Response $response): Response
    {
        return Json::write($response, ['data' => $this->people->listEnrolment($request->getQueryParams())]);
    }

    public function updateStudentEnrolment(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->people->updateEnrolment($args['id'], $this->body($request)));
    }

    public function destroyStudentEnrolment(Request $request, Response $response, array $args): Response
    {
        $this->people->deleteEnrolment($args['id']);
        return Json::empty($response);
    }

    public function roles(Request $request, Response $response): Response
    {
        return Json::write($response, ['data' => $this->people->listRoles()]);
    }

    public function storeRole(Request $request, Response $response): Response
    {
        return Json::write($response, $this->people->createRole($this->body($request)), 201);
    }

    public function updateRole(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->people->updateRole($args['id'], $this->body($request)));
    }

    public function destroyRole(Request $request, Response $response, array $args): Response
    {
        $this->people->deleteRole($args['id']);
        return Json::empty($response);
    }

    public function families(Request $request, Response $response): Response
    {
        return Json::write($response, ['data' => $this->families->list()]);
    }

    public function showFamily(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->families->get($args['id']));
    }

    public function storeFamily(Request $request, Response $response): Response
    {
        return Json::write($response, $this->families->create($this->body($request)), 201);
    }

    public function updateFamily(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->families->update($args['id'], $this->body($request)));
    }

    public function destroyFamily(Request $request, Response $response, array $args): Response
    {
        $this->families->delete($args['id']);
        return Json::empty($response);
    }

    public function storeFamilyAdult(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->families->addAdult($args['id'], $this->body($request)), 201);
    }

    public function destroyFamilyAdult(Request $request, Response $response, array $args): Response
    {
        $this->families->deleteAdult($args['id']);
        return Json::empty($response);
    }

    public function storeFamilyChild(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->families->addChild($args['id'], $this->body($request)), 201);
    }

    public function destroyFamilyChild(Request $request, Response $response, array $args): Response
    {
        $this->families->deleteChild($args['id']);
        return Json::empty($response);
    }

    public function medicalConditions(Request $request, Response $response): Response
    {
        return Json::write($response, ['data' => $this->medical->listConditions()]);
    }

    public function storeMedicalCondition(Request $request, Response $response): Response
    {
        return Json::write($response, $this->medical->createCondition($this->body($request)), 201);
    }

    public function updateMedicalCondition(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->medical->updateCondition($args['id'], $this->body($request)));
    }

    public function destroyMedicalCondition(Request $request, Response $response, array $args): Response
    {
        $this->medical->deleteCondition($args['id']);
        return Json::empty($response);
    }

    public function personMedical(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->medical->getPersonMedical($args['id']));
    }

    public function upsertPersonMedical(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->medical->upsertPersonMedical($args['id'], $this->body($request)));
    }

    public function storePersonCondition(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->medical->addPersonCondition($args['id'], $this->body($request)), 201);
    }

    public function destroyPersonCondition(Request $request, Response $response, array $args): Response
    {
        $this->medical->deletePersonCondition($args['id']);
        return Json::empty($response);
    }

    public function showUnit(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->units->get($args['id']));
    }

    public function storeUnit(Request $request, Response $response): Response
    {
        return Json::write($response, $this->units->create($this->body($request)), 201);
    }

    public function updateUnit(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->units->update($args['id'], $this->body($request)));
    }

    public function destroyUnit(Request $request, Response $response, array $args): Response
    {
        $this->units->delete($args['id']);
        return Json::empty($response);
    }

    public function storeUnitBlock(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->units->createBlock($args['id'], $this->body($request)), 201);
    }

    public function updateUnitBlock(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->units->updateBlock($args['id'], $this->body($request)));
    }

    public function destroyUnitBlock(Request $request, Response $response, array $args): Response
    {
        $this->units->deleteBlock($args['id']);
        return Json::empty($response);
    }

    public function attachUnitClass(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->units->attachClass($args['id'], $this->body($request)), 201);
    }

    public function deployUnit(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->units->deploy($args['id'], $this->body($request)), 201);
    }

    public function copyForwardUnit(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->units->copyForward($args['id'], $this->body($request)), 201);
    }

    public function copyBackUnit(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->units->copyBack($args['id']));
    }

    public function smartBlockify(Request $request, Response $response, array $args): Response
    {
        $body = $this->body($request);
        $lessonId = $body['gibbonPlannerEntryID'] ?? '';
        if ($lessonId === '') {
            throw new ApiException('gibbonPlannerEntryID is required.', 422);
        }
        return Json::write($response, $this->units->smartBlockify($args['id'], $lessonId));
    }

    public function homework(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, ['data' => $this->homework->list($args['id'])]);
    }

    public function storeHomework(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->homework->create($args['id'], $this->body($request)), 201);
    }

    public function updateHomework(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->homework->update($args['id'], $this->body($request)));
    }

    public function destroyHomework(Request $request, Response $response, array $args): Response
    {
        $this->homework->delete($args['id']);
        return Json::empty($response);
    }
}
