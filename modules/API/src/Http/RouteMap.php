<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

namespace Gibbon\Module\API\Http;

use Gibbon\Module\API\Controllers\AttendanceController;
use Gibbon\Module\API\Controllers\BehaviourController;
use Gibbon\Module\API\Controllers\ExpansionController;
use Gibbon\Module\API\Controllers\FinanceController;
use Gibbon\Module\API\Controllers\MarkbookController;
use Gibbon\Module\API\Controllers\SchoolStructureController;
use Psr\Container\ContainerInterface;
use Slim\App;

class RouteMap
{
    public static function register(App $app, ContainerInterface $c): void
    {
        $school = fn () => $c->get(SchoolStructureController::class);
        $x = fn () => $c->get(ExpansionController::class);
        $att = fn () => $c->get(AttendanceController::class);
        $mb = fn () => $c->get(MarkbookController::class);

        $app->get('/v1/year-groups', fn ($req, $res) => $school()->yearGroups($req, $res));
        $app->post('/v1/year-groups', fn ($req, $res) => $school()->storeYearGroup($req, $res));
        $app->patch('/v1/year-groups/{id}', fn ($req, $res, $args) => $school()->updateYearGroup($req, $res, $args));
        $app->delete('/v1/year-groups/{id}', fn ($req, $res, $args) => $school()->destroyYearGroup($req, $res, $args));

        $app->get('/v1/departments', fn ($req, $res) => $school()->departments($req, $res));
        $app->post('/v1/departments', fn ($req, $res) => $school()->storeDepartment($req, $res));
        $app->patch('/v1/departments/{id}', fn ($req, $res, $args) => $school()->updateDepartment($req, $res, $args));
        $app->delete('/v1/departments/{id}', fn ($req, $res, $args) => $school()->destroyDepartment($req, $res, $args));

        $app->get('/v1/houses', fn ($req, $res) => $school()->houses($req, $res));
        $app->post('/v1/houses', fn ($req, $res) => $school()->storeHouse($req, $res));
        $app->patch('/v1/houses/{id}', fn ($req, $res, $args) => $school()->updateHouse($req, $res, $args));
        $app->delete('/v1/houses/{id}', fn ($req, $res, $args) => $school()->destroyHouse($req, $res, $args));

        $app->get('/v1/form-groups', fn ($req, $res) => $school()->formGroups($req, $res));
        $app->post('/v1/form-groups', fn ($req, $res) => $school()->storeFormGroup($req, $res));
        $app->patch('/v1/form-groups/{id}', fn ($req, $res, $args) => $school()->updateFormGroup($req, $res, $args));
        $app->delete('/v1/form-groups/{id}', fn ($req, $res, $args) => $school()->destroyFormGroup($req, $res, $args));

        $app->post('/v1/spaces', fn ($req, $res) => $school()->storeSpace($req, $res));
        $app->patch('/v1/spaces/{id}', fn ($req, $res, $args) => $school()->updateSpace($req, $res, $args));
        $app->delete('/v1/spaces/{id}', fn ($req, $res, $args) => $school()->destroySpace($req, $res, $args));

        $app->get('/v1/school-years', fn ($req, $res) => $school()->schoolYears($req, $res));
        $app->post('/v1/school-years', fn ($req, $res) => $school()->storeSchoolYear($req, $res));
        $app->patch('/v1/school-years/{id}', fn ($req, $res, $args) => $school()->updateSchoolYear($req, $res, $args));
        $app->delete('/v1/school-years/{id}', fn ($req, $res, $args) => $school()->destroySchoolYear($req, $res, $args));

        $app->get('/v1/terms', fn ($req, $res) => $school()->terms($req, $res));
        $app->post('/v1/terms', fn ($req, $res) => $school()->storeTerm($req, $res));
        $app->patch('/v1/terms/{id}', fn ($req, $res, $args) => $school()->updateTerm($req, $res, $args));
        $app->delete('/v1/terms/{id}', fn ($req, $res, $args) => $school()->destroyTerm($req, $res, $args));

        $app->get('/v1/special-days', fn ($req, $res) => $school()->specialDays($req, $res));
        $app->post('/v1/special-days', fn ($req, $res) => $school()->storeSpecialDay($req, $res));
        $app->patch('/v1/special-days/{id}', fn ($req, $res, $args) => $school()->updateSpecialDay($req, $res, $args));
        $app->delete('/v1/special-days/{id}', fn ($req, $res, $args) => $school()->destroySpecialDay($req, $res, $args));

        $app->post('/v1/timetables', fn ($req, $res) => $x()->storeTimetable($req, $res));
        $app->patch('/v1/timetables/{id}', fn ($req, $res, $args) => $x()->updateTimetable($req, $res, $args));
        $app->delete('/v1/timetables/{id}', fn ($req, $res, $args) => $x()->destroyTimetable($req, $res, $args));
        $app->post('/v1/timetables/{id}/days', fn ($req, $res, $args) => $x()->storeDay($req, $res, $args));
        $app->patch('/v1/timetables/{id}/days/{dayId}', fn ($req, $res, $args) => $x()->updateDay($req, $res, $args));
        $app->delete('/v1/timetables/{id}/days/{dayId}', fn ($req, $res, $args) => $x()->destroyDay($req, $res, $args));
        $app->post('/v1/timetables/{id}/dates', fn ($req, $res, $args) => $x()->storeDate($req, $res, $args));
        $app->delete('/v1/timetable-dates/{id}', fn ($req, $res, $args) => $x()->destroyDate($req, $res, $args));

        $app->get('/v1/timetable-columns', fn ($req, $res) => $x()->columns($req, $res));
        $app->post('/v1/timetable-columns', fn ($req, $res) => $x()->storeColumn($req, $res));
        $app->get('/v1/timetable-columns/{id}', fn ($req, $res, $args) => $x()->showColumn($req, $res, $args));
        $app->patch('/v1/timetable-columns/{id}', fn ($req, $res, $args) => $x()->updateColumn($req, $res, $args));
        $app->delete('/v1/timetable-columns/{id}', fn ($req, $res, $args) => $x()->destroyColumn($req, $res, $args));
        $app->post('/v1/timetable-columns/{id}/rows', fn ($req, $res, $args) => $x()->storeColumnRow($req, $res, $args));
        $app->patch('/v1/timetable-column-rows/{id}', fn ($req, $res, $args) => $x()->updateColumnRow($req, $res, $args));
        $app->delete('/v1/timetable-column-rows/{id}', fn ($req, $res, $args) => $x()->destroyColumnRow($req, $res, $args));

        $app->get('/v1/timetable-slots/{id}/exceptions', fn ($req, $res, $args) => $x()->exceptions($req, $res, $args));
        $app->post('/v1/timetable-slots/{id}/exceptions', fn ($req, $res, $args) => $x()->storeException($req, $res, $args));
        $app->delete('/v1/timetable-slot-exceptions/{id}', fn ($req, $res, $args) => $x()->destroyException($req, $res, $args));

        $app->post('/v1/courses', fn ($req, $res) => $x()->storeCourse($req, $res));
        $app->get('/v1/courses/{id}', fn ($req, $res, $args) => $x()->showCourse($req, $res, $args));
        $app->patch('/v1/courses/{id}', fn ($req, $res, $args) => $x()->updateCourse($req, $res, $args));
        $app->delete('/v1/courses/{id}', fn ($req, $res, $args) => $x()->destroyCourse($req, $res, $args));
        $app->get('/v1/courses/{id}/classes', fn ($req, $res, $args) => $x()->courseClasses($req, $res, $args));
        $app->post('/v1/courses/{id}/classes', fn ($req, $res, $args) => $x()->storeClass($req, $res, $args));
        $app->patch('/v1/classes/{id}', fn ($req, $res, $args) => $x()->updateClass($req, $res, $args));
        $app->delete('/v1/classes/{id}', fn ($req, $res, $args) => $x()->destroyClass($req, $res, $args));
        $app->get('/v1/classes/{id}/enrolment', fn ($req, $res, $args) => $x()->enrolment($req, $res, $args));
        $app->post('/v1/classes/{id}/enrolment', fn ($req, $res, $args) => $x()->storeEnrolment($req, $res, $args));
        $app->patch('/v1/enrolment/{id}', fn ($req, $res, $args) => $x()->updateEnrolment($req, $res, $args));
        $app->delete('/v1/enrolment/{id}', fn ($req, $res, $args) => $x()->destroyEnrolment($req, $res, $args));

        $app->get('/v1/people', fn ($req, $res) => $x()->people($req, $res));
        $app->post('/v1/people', fn ($req, $res) => $x()->storePerson($req, $res));
        $app->get('/v1/people/{id}', fn ($req, $res, $args) => $x()->showPerson($req, $res, $args));
        $app->patch('/v1/people/{id}', fn ($req, $res, $args) => $x()->updatePerson($req, $res, $args));
        $app->delete('/v1/people/{id}', fn ($req, $res, $args) => $x()->destroyPerson($req, $res, $args));
        $app->post('/v1/people/{id}/password', fn ($req, $res, $args) => $x()->resetPassword($req, $res, $args));
        $app->get('/v1/people/{id}/enrolment', fn ($req, $res, $args) => $x()->personEnrolment($req, $res, $args));
        $app->post('/v1/people/{id}/enrolment', fn ($req, $res, $args) => $x()->storePersonEnrolment($req, $res, $args));
        $app->get('/v1/student-enrolments', fn ($req, $res) => $x()->studentEnrolments($req, $res));
        $app->patch('/v1/student-enrolments/{id}', fn ($req, $res, $args) => $x()->updateStudentEnrolment($req, $res, $args));
        $app->delete('/v1/student-enrolments/{id}', fn ($req, $res, $args) => $x()->destroyStudentEnrolment($req, $res, $args));

        $app->get('/v1/roles', fn ($req, $res) => $x()->roles($req, $res));
        $app->post('/v1/roles', fn ($req, $res) => $x()->storeRole($req, $res));
        $app->patch('/v1/roles/{id}', fn ($req, $res, $args) => $x()->updateRole($req, $res, $args));
        $app->delete('/v1/roles/{id}', fn ($req, $res, $args) => $x()->destroyRole($req, $res, $args));

        $app->get('/v1/families', fn ($req, $res) => $x()->families($req, $res));
        $app->post('/v1/families', fn ($req, $res) => $x()->storeFamily($req, $res));
        $app->get('/v1/families/{id}', fn ($req, $res, $args) => $x()->showFamily($req, $res, $args));
        $app->patch('/v1/families/{id}', fn ($req, $res, $args) => $x()->updateFamily($req, $res, $args));
        $app->delete('/v1/families/{id}', fn ($req, $res, $args) => $x()->destroyFamily($req, $res, $args));
        $app->post('/v1/families/{id}/adults', fn ($req, $res, $args) => $x()->storeFamilyAdult($req, $res, $args));
        $app->delete('/v1/family-adults/{id}', fn ($req, $res, $args) => $x()->destroyFamilyAdult($req, $res, $args));
        $app->post('/v1/families/{id}/children', fn ($req, $res, $args) => $x()->storeFamilyChild($req, $res, $args));
        $app->delete('/v1/family-children/{id}', fn ($req, $res, $args) => $x()->destroyFamilyChild($req, $res, $args));

        $app->get('/v1/medical-conditions', fn ($req, $res) => $x()->medicalConditions($req, $res));
        $app->post('/v1/medical-conditions', fn ($req, $res) => $x()->storeMedicalCondition($req, $res));
        $app->patch('/v1/medical-conditions/{id}', fn ($req, $res, $args) => $x()->updateMedicalCondition($req, $res, $args));
        $app->delete('/v1/medical-conditions/{id}', fn ($req, $res, $args) => $x()->destroyMedicalCondition($req, $res, $args));
        $app->get('/v1/people/{id}/medical', fn ($req, $res, $args) => $x()->personMedical($req, $res, $args));
        $app->put('/v1/people/{id}/medical', fn ($req, $res, $args) => $x()->upsertPersonMedical($req, $res, $args));
        $app->post('/v1/people/{id}/medical-conditions', fn ($req, $res, $args) => $x()->storePersonCondition($req, $res, $args));
        $app->delete('/v1/person-medical-conditions/{id}', fn ($req, $res, $args) => $x()->destroyPersonCondition($req, $res, $args));

        $app->post('/v1/planner/units', fn ($req, $res) => $x()->storeUnit($req, $res));
        $app->get('/v1/planner/units/{id}', fn ($req, $res, $args) => $x()->showUnit($req, $res, $args));
        $app->patch('/v1/planner/units/{id}', fn ($req, $res, $args) => $x()->updateUnit($req, $res, $args));
        $app->delete('/v1/planner/units/{id}', fn ($req, $res, $args) => $x()->destroyUnit($req, $res, $args));
        $app->post('/v1/planner/units/{id}/blocks', fn ($req, $res, $args) => $x()->storeUnitBlock($req, $res, $args));
        $app->patch('/v1/planner/unit-blocks/{id}', fn ($req, $res, $args) => $x()->updateUnitBlock($req, $res, $args));
        $app->delete('/v1/planner/unit-blocks/{id}', fn ($req, $res, $args) => $x()->destroyUnitBlock($req, $res, $args));
        $app->post('/v1/planner/units/{id}/classes', fn ($req, $res, $args) => $x()->attachUnitClass($req, $res, $args));
        $app->post('/v1/planner/units/{id}/deploy', fn ($req, $res, $args) => $x()->deployUnit($req, $res, $args));
        $app->post('/v1/planner/units/{id}/copy-forward', fn ($req, $res, $args) => $x()->copyForwardUnit($req, $res, $args));
        $app->post('/v1/planner/unit-classes/{id}/copy-back', fn ($req, $res, $args) => $x()->copyBackUnit($req, $res, $args));
        $app->post('/v1/planner/units/{id}/smart-blockify', fn ($req, $res, $args) => $x()->smartBlockify($req, $res, $args));

        $app->get('/v1/planner/lessons/{id}/homework', fn ($req, $res, $args) => $x()->homework($req, $res, $args));
        $app->post('/v1/planner/lessons/{id}/homework', fn ($req, $res, $args) => $x()->storeHomework($req, $res, $args));
        $app->patch('/v1/planner/homework/{id}', fn ($req, $res, $args) => $x()->updateHomework($req, $res, $args));
        $app->delete('/v1/planner/homework/{id}', fn ($req, $res, $args) => $x()->destroyHomework($req, $res, $args));

        $app->get('/v1/attendance/codes', fn ($req, $res) => $att()->codes($req, $res));
        $app->post('/v1/attendance/codes', fn ($req, $res) => $att()->storeCode($req, $res));
        $app->get('/v1/attendance/codes/{id}', fn ($req, $res, $args) => $att()->showCode($req, $res, $args));
        $app->patch('/v1/attendance/codes/{id}', fn ($req, $res, $args) => $att()->updateCode($req, $res, $args));
        $app->delete('/v1/attendance/codes/{id}', fn ($req, $res, $args) => $att()->destroyCode($req, $res, $args));
        $app->get('/v1/attendance/classes/{id}', fn ($req, $res, $args) => $att()->classSheet($req, $res, $args));
        $app->post('/v1/attendance/classes/{id}', fn ($req, $res, $args) => $att()->takeClass($req, $res, $args));
        $app->get('/v1/attendance/form-groups/{id}', fn ($req, $res, $args) => $att()->formGroupSheet($req, $res, $args));
        $app->post('/v1/attendance/form-groups/{id}', fn ($req, $res, $args) => $att()->takeFormGroup($req, $res, $args));
        $app->get('/v1/attendance/people/{id}', fn ($req, $res, $args) => $att()->personSheet($req, $res, $args));
        $app->post('/v1/attendance/people/{id}', fn ($req, $res, $args) => $att()->takePerson($req, $res, $args));
        $app->get('/v1/attendance/reports/student-history', fn ($req, $res) => $att()->studentHistory($req, $res));
        $app->get('/v1/attendance/reports/consecutive-absences', fn ($req, $res) => $att()->consecutiveAbsences($req, $res));
        $app->get('/v1/attendance/reports/not-present', fn ($req, $res) => $att()->notPresent($req, $res));
        $app->get('/v1/attendance/reports/not-onsite', fn ($req, $res) => $att()->notOnsite($req, $res));
        $app->get('/v1/attendance/reports/not-in-class', fn ($req, $res) => $att()->notInClass($req, $res));
        $app->get('/v1/attendance/reports/form-groups-not-registered', fn ($req, $res) => $att()->formGroupsNotRegistered($req, $res));
        $app->get('/v1/attendance/reports/classes-not-registered', fn ($req, $res) => $att()->classesNotRegistered($req, $res));
        $app->get('/v1/attendance/reports/trends', fn ($req, $res) => $att()->trends($req, $res));

        $fin = fn () => $c->get(FinanceController::class);
        $app->get('/v1/finance/budget-cycles', fn ($req, $res) => $fin()->cycles($req, $res));
        $app->post('/v1/finance/budget-cycles', fn ($req, $res) => $fin()->storeCycle($req, $res));
        $app->get('/v1/finance/budget-cycles/{id}', fn ($req, $res, $args) => $fin()->showCycle($req, $res, $args));
        $app->patch('/v1/finance/budget-cycles/{id}', fn ($req, $res, $args) => $fin()->updateCycle($req, $res, $args));
        $app->delete('/v1/finance/budget-cycles/{id}', fn ($req, $res, $args) => $fin()->destroyCycle($req, $res, $args));
        $app->get('/v1/finance/budget-cycles/{id}/allocations', fn ($req, $res, $args) => $fin()->allocations($req, $res, $args));
        $app->put('/v1/finance/budget-cycles/{id}/allocations', fn ($req, $res, $args) => $fin()->saveAllocations($req, $res, $args));
        $app->get('/v1/finance/budgets', fn ($req, $res) => $fin()->budgets($req, $res));
        $app->post('/v1/finance/budgets', fn ($req, $res) => $fin()->storeBudget($req, $res));
        $app->get('/v1/finance/budgets/{id}', fn ($req, $res, $args) => $fin()->showBudget($req, $res, $args));
        $app->patch('/v1/finance/budgets/{id}', fn ($req, $res, $args) => $fin()->updateBudget($req, $res, $args));
        $app->delete('/v1/finance/budgets/{id}', fn ($req, $res, $args) => $fin()->destroyBudget($req, $res, $args));
        $app->post('/v1/finance/budgets/{id}/staff', fn ($req, $res, $args) => $fin()->storeBudgetStaff($req, $res, $args));
        $app->delete('/v1/finance/budget-staff/{id}', fn ($req, $res, $args) => $fin()->destroyBudgetStaff($req, $res, $args));
        $app->get('/v1/finance/expense-approvers', fn ($req, $res) => $fin()->approvers($req, $res));
        $app->post('/v1/finance/expense-approvers', fn ($req, $res) => $fin()->storeApprover($req, $res));
        $app->patch('/v1/finance/expense-approvers/{id}', fn ($req, $res, $args) => $fin()->updateApprover($req, $res, $args));
        $app->delete('/v1/finance/expense-approvers/{id}', fn ($req, $res, $args) => $fin()->destroyApprover($req, $res, $args));
        $app->get('/v1/finance/expenses', fn ($req, $res) => $fin()->expenses($req, $res));
        $app->post('/v1/finance/expenses', fn ($req, $res) => $fin()->storeExpense($req, $res));
        $app->get('/v1/finance/expenses/{id}', fn ($req, $res, $args) => $fin()->showExpense($req, $res, $args));
        $app->get('/v1/finance/expenses/{id}/print', fn ($req, $res, $args) => $fin()->printExpense($req, $res, $args));
        $app->post('/v1/finance/expenses/{id}/approve', fn ($req, $res, $args) => $fin()->approveExpense($req, $res, $args));
        $app->post('/v1/finance/expenses/{id}/reimburse', fn ($req, $res, $args) => $fin()->reimburseExpense($req, $res, $args));
        $app->get('/v1/finance/petty-cash', fn ($req, $res) => $fin()->pettyCash($req, $res));
        $app->post('/v1/finance/petty-cash', fn ($req, $res) => $fin()->storePettyCash($req, $res));
        $app->patch('/v1/finance/petty-cash/{id}', fn ($req, $res, $args) => $fin()->updatePettyCash($req, $res, $args));
        $app->delete('/v1/finance/petty-cash/{id}', fn ($req, $res, $args) => $fin()->destroyPettyCash($req, $res, $args));
        $app->post('/v1/finance/petty-cash/{id}/action', fn ($req, $res, $args) => $fin()->actionPettyCash($req, $res, $args));
        $app->get('/v1/finance/fee-categories', fn ($req, $res) => $fin()->feeCategories($req, $res));
        $app->post('/v1/finance/fee-categories', fn ($req, $res) => $fin()->storeFeeCategory($req, $res));
        $app->get('/v1/finance/fee-categories/{id}', fn ($req, $res, $args) => $fin()->showFeeCategory($req, $res, $args));
        $app->patch('/v1/finance/fee-categories/{id}', fn ($req, $res, $args) => $fin()->updateFeeCategory($req, $res, $args));
        $app->delete('/v1/finance/fee-categories/{id}', fn ($req, $res, $args) => $fin()->destroyFeeCategory($req, $res, $args));
        $app->get('/v1/finance/fees', fn ($req, $res) => $fin()->feeItems($req, $res));
        $app->post('/v1/finance/fees', fn ($req, $res) => $fin()->storeFee($req, $res));
        $app->get('/v1/finance/fees/{id}', fn ($req, $res, $args) => $fin()->showFee($req, $res, $args));
        $app->patch('/v1/finance/fees/{id}', fn ($req, $res, $args) => $fin()->updateFee($req, $res, $args));

        $beh = fn () => $c->get(BehaviourController::class);
        $app->get('/v1/behaviour', fn ($req, $res) => $beh()->index($req, $res));
        $app->post('/v1/behaviour', fn ($req, $res) => $beh()->store($req, $res));
        $app->get('/v1/behaviour/{id}', fn ($req, $res, $args) => $beh()->show($req, $res, $args));
        $app->patch('/v1/behaviour/{id}', fn ($req, $res, $args) => $beh()->update($req, $res, $args));
        $app->delete('/v1/behaviour/{id}', fn ($req, $res, $args) => $beh()->destroy($req, $res, $args));
        $app->post('/v1/behaviour/{id}/follow-up', fn ($req, $res, $args) => $beh()->followUp($req, $res, $args));

        $app->get('/v1/grade-scales', fn ($req, $res) => $mb()->scales($req, $res));
        $app->get('/v1/grade-scales/{id}', fn ($req, $res, $args) => $mb()->showScale($req, $res, $args));
        $app->get('/v1/markbook/classes/{classId}/columns', fn ($req, $res, $args) => $mb()->columns($req, $res, $args));
        $app->post('/v1/markbook/classes/{classId}/columns', fn ($req, $res, $args) => $mb()->storeColumn($req, $res, $args));
        $app->get('/v1/markbook/columns/{id}', fn ($req, $res, $args) => $mb()->showColumn($req, $res, $args));
        $app->patch('/v1/markbook/columns/{id}', fn ($req, $res, $args) => $mb()->updateColumn($req, $res, $args));
        $app->delete('/v1/markbook/columns/{id}', fn ($req, $res, $args) => $mb()->destroyColumn($req, $res, $args));
        $app->get('/v1/markbook/columns/{id}/entries', fn ($req, $res, $args) => $mb()->entries($req, $res, $args));
        $app->put('/v1/markbook/columns/{id}/entries', fn ($req, $res, $args) => $mb()->saveEntries($req, $res, $args));
    }
}
