<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

namespace Gibbon\Module\API\Auth;

use Gibbon\Auth\Access\Access;
use Gibbon\Contracts\Database\Connection;
use Gibbon\Contracts\Services\Session;
use Gibbon\Module\API\Http\ApiException;

class PermissionMapper
{
    protected Access $access;
    protected Session $session;
    protected Connection $db;

    public function __construct(Access $access, Session $session, Connection $db)
    {
        $this->access = $access;
        $this->session = $session;
        $this->db = $db;
    }

    public function canViewPlanner(): bool
    {
        return $this->access->allows('Planner', 'planner');
    }

    public function canEditPlanner(): bool
    {
        return $this->plannerHighestAction() === 'Lesson Planner_viewEditAllClasses'
            || $this->plannerHighestAction() === 'Lesson Planner_viewAllEditMyClasses';
    }

    public function canEditAllPlannerClasses(): bool
    {
        return $this->plannerHighestAction() === 'Lesson Planner_viewEditAllClasses';
    }

    public function canViewAllPlannerClasses(): bool
    {
        $action = $this->plannerHighestAction();
        return in_array($action, ['Lesson Planner_viewEditAllClasses', 'Lesson Planner_viewOnly'], true);
    }

    public function canViewTimetable(): bool
    {
        return $this->access->allows('Timetable', 'tt')
            || $this->canManageTimetables();
    }

    public function canManageTimetables(): bool
    {
        return $this->access->allows('Timetable Admin', 'tt');
    }

    public function plannerHighestAction(): ?string
    {
        $action = $this->access->get('Planner', 'planner');
        foreach ([
            'Lesson Planner_viewEditAllClasses',
            'Lesson Planner_viewAllEditMyClasses',
            'Lesson Planner_viewOnly',
            'Lesson Planner_viewMyClasses',
            'Lesson Planner_viewMyChildrensClasses',
        ] as $name) {
            if ($action->allows($name)) {
                return $name;
            }
        }

        return $this->canViewPlanner() ? 'Lesson Planner_viewMyClasses' : null;
    }

    public function capabilities(): array
    {
        return [
            'planner.read' => $this->canViewPlanner(),
            'planner.write' => $this->canEditPlanner(),
            'planner.editAllClasses' => $this->canEditAllPlannerClasses(),
            'planner.units' => $this->canManageUnits(),
            'timetable.read' => $this->canViewTimetable(),
            'timetable.write' => $this->canManageTimetables(),
            'timetable.courses' => $this->canManageCourses(),
            'timetable.enrolment' => $this->canManageEnrolment(),
            'school.structure' => $this->canManageSchoolStructure(),
            'user.admin' => $this->canManageUsers(),
            'staff.read' => $this->canViewStaff(),
            'staff.write' => $this->canManageStaff(),
            'attendance.class' => $this->canTakeClassAttendance(),
            'attendance.formGroup' => $this->canTakeFormGroupAttendance(),
            'attendance.person' => $this->canTakePersonAttendance(),
            'attendance.codes' => $this->canManageAttendanceCodes(),
            'markbook.write' => $this->canEditMarkbook(),
            'markbook.editAllClasses' => $this->canEditAllMarkbookClasses(),
            'finance.expenses' => $this->canManageExpenses() || $this->canRequestExpenses(),
            'finance.expensesAll' => $this->canManageAllExpenses(),
            'finance.fees' => $this->canManageFees() || $this->canManageFeeCategories(),
            'finance.budgets' => $this->canManageBudgets(),
            'finance.pettyCash' => $this->canManagePettyCash(),
            'behaviour.write' => $this->canManageBehaviour(),
            'behaviour.writeAll' => $this->canManageAllBehaviour(),
            'attendance.reports' => $this->canViewAttendanceReports(),
        ];
    }

    public function canManageUnits(): bool
    {
        return $this->access->allows('Planner', 'units');
    }

    public function canManageCourses(): bool
    {
        return $this->access->allows('Timetable Admin', 'course_manage');
    }

    public function canManageEnrolment(): bool
    {
        return $this->access->allows('Timetable Admin', 'courseEnrolment_manage');
    }

    public function canManageSchoolStructure(): bool
    {
        return $this->access->allows('School Admin', 'yearGroup_manage')
            || $this->access->allows('School Admin', 'department_manage')
            || $this->access->allows('School Admin', 'house_manage')
            || $this->access->allows('School Admin', 'formGroup_manage')
            || $this->access->allows('School Admin', 'space_manage')
            || $this->access->allows('School Admin', 'schoolYearTerm_manage')
            || $this->access->allows('School Admin', 'schoolYearSpecialDay_manage');
    }

    public function canTakeClassAttendance(): bool
    {
        return $this->access->allows('Attendance', 'attendance_take_byCourseClass');
    }

    public function canTakeFormGroupAttendance(): bool
    {
        return $this->access->allows('Attendance', 'attendance_take_byFormGroup');
    }

    public function canTakePersonAttendance(): bool
    {
        return $this->access->allows('Attendance', 'attendance_take_byPerson');
    }

    public function canEditMarkbook(): bool
    {
        return $this->access->allows('Markbook', 'markbook_edit');
    }

    public function canEditAllMarkbookClasses(): bool
    {
        return $this->markbookHighestEditAction() === 'Edit Markbook_everything';
    }

    public function markbookHighestEditAction(): ?string
    {
        $action = $this->access->get('Markbook', 'markbook_edit');
        foreach ([
            'Edit Markbook_everything',
            'Edit Markbook_multipleClassesAcrossSchool',
            'Edit Markbook_multipleClassesInDepartment',
            'Edit Markbook_singleClass',
        ] as $name) {
            if ($action->allows($name)) {
                return $name;
            }
        }

        return $this->canEditMarkbook() ? 'Edit Markbook_singleClass' : null;
    }

    public function canTakeAllFormGroups(): bool
    {
        return $this->access->get('Attendance', 'attendance_take_byFormGroup')->allows('Attendance By Form Group_all');
    }

    public function canTakeAnyAttendance(): bool
    {
        return $this->canTakeClassAttendance()
            || $this->canTakeFormGroupAttendance()
            || $this->canTakePersonAttendance();
    }

    public function canManageAttendanceCodes(): bool
    {
        return $this->access->allows('School Admin', 'attendanceSettings');
    }

    public function assertCanTakeAnyAttendance(): void
    {
        if (!$this->canTakeAnyAttendance() && !$this->canManageAttendanceCodes()) {
            throw new ApiException('You do not have permission to view or take attendance.', 403);
        }
    }

    public function assertCanManageAttendanceCodes(): void
    {
        if (!$this->canManageAttendanceCodes()) {
            throw new ApiException('You do not have permission to manage attendance codes.', 403);
        }
    }

    public function assertCanTakeClassAttendance(): void
    {
        if (!$this->canTakeClassAttendance()) {
            throw new ApiException('You do not have permission to take class attendance.', 403);
        }
    }

    public function assertCanTakeFormGroupAttendance(): void
    {
        if (!$this->canTakeFormGroupAttendance()) {
            throw new ApiException('You do not have permission to take form group attendance.', 403);
        }
    }

    public function assertCanTakePersonAttendance(): void
    {
        if (!$this->canTakePersonAttendance()) {
            throw new ApiException('You do not have permission to take attendance by person.', 403);
        }
    }

    public function assertCanEditMarkbook(): void
    {
        if (!$this->canEditMarkbook()) {
            throw new ApiException('You do not have permission to edit the markbook.', 403);
        }
    }

    public function assertMarkbookClassWritable(string $gibbonCourseClassID): void
    {
        $this->assertCanEditMarkbook();
        if ($this->canEditAllMarkbookClasses()) {
            return;
        }
        if (!$this->isTeacherOfClass($gibbonCourseClassID)) {
            throw new ApiException('You can only edit the markbook for classes you teach.', 403);
        }
    }

    public function canManageUsers(): bool
    {
        return $this->access->allows('User Admin', 'user_manage');
    }

    public function canViewStaff(): bool
    {
        return $this->access->allows('Staff', 'staff_view')
            || $this->canManageStaff();
    }

    public function canViewFullStaffDirectory(): bool
    {
        return $this->access->get('Staff', 'staff_view')->allows('Staff Directory_full')
            || $this->canManageStaff();
    }

    public function canManageStaff(): bool
    {
        return $this->access->allows('Staff', 'staff_manage');
    }

    public function assertAllows(string $module, string $route, string $message): void
    {
        if (!$this->access->allows($module, $route)) {
            throw new ApiException($message, 403);
        }
    }

    public function assertCanManageUnits(): void
    {
        if (!$this->canManageUnits()) {
            throw new ApiException('You do not have permission to manage units.', 403);
        }
    }

    public function assertCanManageCourses(): void
    {
        if (!$this->canManageCourses()) {
            throw new ApiException('You do not have permission to manage courses.', 403);
        }
    }

    public function assertCanManageEnrolment(): void
    {
        if (!$this->canManageEnrolment()) {
            throw new ApiException('You do not have permission to manage class enrolment.', 403);
        }
    }

    public function assertCanManageUsers(): void
    {
        if (!$this->canManageUsers()) {
            throw new ApiException('You do not have permission to manage users.', 403);
        }
    }

    public function assertCanViewStaff(): void
    {
        if (!$this->canViewStaff()) {
            throw new ApiException('You do not have permission to view staff.', 403);
        }
    }

    public function assertCanManageStaff(): void
    {
        if (!$this->canManageStaff()) {
            throw new ApiException('You do not have permission to manage staff.', 403);
        }
    }

    public function assertCanViewPlanner(): void
    {
        if (!$this->canViewPlanner()) {
            throw new ApiException('You do not have permission to view lesson planner data.', 403);
        }
    }

    public function assertCanEditPlanner(): void
    {
        if (!$this->canEditPlanner()) {
            throw new ApiException('You do not have permission to edit lesson plans.', 403);
        }
    }

    public function assertCanViewTimetable(): void
    {
        if (!$this->canViewTimetable()) {
            throw new ApiException('You do not have permission to view timetables.', 403);
        }
    }

    public function assertCanManageTimetables(): void
    {
        if (!$this->canManageTimetables()) {
            throw new ApiException('You do not have permission to manage timetable slots.', 403);
        }
    }

    public function assertClassReadable(string $gibbonCourseClassID): void
    {
        $this->assertCanViewPlanner();
        if ($this->canViewAllPlannerClasses() || $this->canEditAllPlannerClasses()) {
            return;
        }
        if (!$this->isEnrolledInClass($gibbonCourseClassID)) {
            throw new ApiException('You do not have permission to view this class.', 403);
        }
    }

    public function assertClassWritable(string $gibbonCourseClassID): void
    {
        $this->assertCanEditPlanner();
        if ($this->canEditAllPlannerClasses()) {
            return;
        }
        if (!$this->isTeacherOfClass($gibbonCourseClassID)) {
            throw new ApiException('You can only edit lesson plans for classes you teach.', 403);
        }
    }

    public function isTeacherOfClass(string $gibbonCourseClassID): bool
    {
        $sql = "SELECT gibbonCourseClassPersonID
                FROM gibbonCourseClassPerson
                WHERE gibbonCourseClassID=:gibbonCourseClassID
                AND gibbonPersonID=:gibbonPersonID
                AND (role='Teacher' OR role='Assistant')
                AND role NOT LIKE '%Left'
                LIMIT 1";

        $id = $this->db->selectOne($sql, [
            'gibbonCourseClassID' => $gibbonCourseClassID,
            'gibbonPersonID' => $this->session->get('gibbonPersonID'),
        ]);

        return !empty($id);
    }

    public function isEnrolledInClass(string $gibbonCourseClassID): bool
    {
        $sql = "SELECT gibbonCourseClassPersonID
                FROM gibbonCourseClassPerson
                WHERE gibbonCourseClassID=:gibbonCourseClassID
                AND gibbonPersonID=:gibbonPersonID
                AND role NOT LIKE '%Left'
                LIMIT 1";

        $id = $this->db->selectOne($sql, [
            'gibbonCourseClassID' => $gibbonCourseClassID,
            'gibbonPersonID' => $this->session->get('gibbonPersonID'),
        ]);

        return !empty($id);
    }

    public function classScopeSql(string $alias = 'gibbonCourseClass'): array
    {
        if ($this->canViewAllPlannerClasses() || $this->canEditAllPlannerClasses()) {
            return ['sql' => '', 'params' => []];
        }

        return [
            'sql' => " AND {$alias}.gibbonCourseClassID IN (
                SELECT gibbonCourseClassID FROM gibbonCourseClassPerson
                WHERE gibbonPersonID=:apiPersonID AND role NOT LIKE '%Left'
            )",
            'params' => ['apiPersonID' => $this->session->get('gibbonPersonID')],
        ];
    }

    public function canRequestExpenses(): bool
    {
        return $this->access->allows('Finance', 'expenseRequest_manage');
    }

    public function canManageExpenses(): bool
    {
        return $this->access->allows('Finance', 'expenses_manage');
    }

    public function canManageAllExpenses(): bool
    {
        return $this->access->get('Finance', 'expenses_manage')->allows('Manage Expenses_all');
    }

    public function canManageBudgets(): bool
    {
        return $this->access->allows('Finance', 'budgets_manage');
    }

    public function canManageBudgetCycles(): bool
    {
        return $this->access->allows('Finance', 'budgetCycles_manage');
    }

    public function canManageExpenseApprovers(): bool
    {
        return $this->access->allows('Finance', 'expenseApprovers_manage');
    }

    public function canManagePettyCash(): bool
    {
        return $this->access->allows('Finance', 'pettyCash');
    }

    public function canManageFeeCategories(): bool
    {
        return $this->access->allows('Finance', 'feeCategories_manage');
    }

    public function canManageFees(): bool
    {
        return $this->access->allows('Finance', 'fees_manage');
    }

    public function canManageBehaviour(): bool
    {
        return $this->access->allows('Behaviour', 'behaviour_manage');
    }

    public function canManageAllBehaviour(): bool
    {
        return $this->access->get('Behaviour', 'behaviour_manage')->allows('Manage Behaviour Records_all');
    }

    public function canViewAttendanceReports(): bool
    {
        return $this->access->allows('Attendance', 'report_studentHistory')
            || $this->access->allows('Attendance', 'report_consecutiveAbsences')
            || $this->access->allows('Attendance', 'report_studentsNotPresent_byDate')
            || $this->access->allows('Attendance', 'report_studentsNotOnsite_byDate')
            || $this->access->allows('Attendance', 'report_studentsNotInClass_byDate')
            || $this->access->allows('Attendance', 'report_formGroupsNotRegistered_byDate')
            || $this->access->allows('Attendance', 'report_courseClassesNotRegistered_byDate')
            || $this->access->allows('Attendance', 'report_graph_byType');
    }

    public function studentHistoryAction(): ?string
    {
        $action = $this->access->get('Attendance', 'report_studentHistory');
        foreach (['Student History_all', 'Student History_my', 'Student History_myChildren'] as $name) {
            if ($action->allows($name)) {
                return $name;
            }
        }

        return $this->access->allows('Attendance', 'report_studentHistory') ? 'Student History_my' : null;
    }

    public function assertCanRequestOrManageExpenses(): void
    {
        if (!$this->canRequestExpenses() && !$this->canManageExpenses()) {
            throw new ApiException('You do not have permission to work with expenses.', 403);
        }
    }

    public function assertCanManageBudgets(): void
    {
        if (!$this->canManageBudgets()) {
            throw new ApiException('You do not have permission to manage budgets.', 403);
        }
    }

    public function assertCanManageBudgetCycles(): void
    {
        if (!$this->canManageBudgetCycles()) {
            throw new ApiException('You do not have permission to manage budget cycles.', 403);
        }
    }

    public function assertCanManageExpenseApprovers(): void
    {
        if (!$this->canManageExpenseApprovers()) {
            throw new ApiException('You do not have permission to manage expense approvers.', 403);
        }
    }

    public function assertCanManagePettyCash(): void
    {
        if (!$this->canManagePettyCash()) {
            throw new ApiException('You do not have permission to manage petty cash.', 403);
        }
    }

    public function assertCanManageFeeCategories(): void
    {
        if (!$this->canManageFeeCategories()) {
            throw new ApiException('You do not have permission to manage fee categories.', 403);
        }
    }

    public function assertCanManageFees(): void
    {
        if (!$this->canManageFees()) {
            throw new ApiException('You do not have permission to manage fees.', 403);
        }
    }

    public function assertCanManageBehaviour(): void
    {
        if (!$this->canManageBehaviour()) {
            throw new ApiException('You do not have permission to manage behaviour records.', 403);
        }
    }

    public function assertAllowsReport(string $route, string $message): void
    {
        $this->assertAllows('Attendance', $route, $message);
    }
}
