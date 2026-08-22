<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

namespace Gibbon\Module\API\Services;

use Gibbon\Contracts\Database\Connection;
use Gibbon\Contracts\Services\Session;
use Gibbon\Domain\Attendance\AttendanceLogPersonGateway;
use Gibbon\Domain\School\SchoolYearGateway;
use Gibbon\Domain\School\SchoolYearSpecialDayGateway;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Domain\User\UserGateway;
use Gibbon\Module\API\Auth\PermissionMapper;
use Gibbon\Module\API\Http\ApiException;
use Gibbon\Module\API\Support\RestTable;
use Gibbon\Module\Attendance\StudentHistoryData;

class AttendanceReportService
{
    public function __construct(
        protected PermissionMapper $permissions,
        protected Connection $db,
        protected Session $session,
        protected SettingGateway $settings,
        protected AttendanceLogPersonGateway $logs,
        protected SchoolYearGateway $years,
        protected SchoolYearSpecialDayGateway $specialDays,
        protected UserGateway $users,
        protected StudentHistoryData $history
    ) {
    }

    public function studentHistory(array $query): array
    {
        $this->permissions->assertAllowsReport('report_studentHistory', 'You do not have permission to view student attendance history.');
        $personId = (string) ($query['gibbonPersonID'] ?? '');
        $action = $this->permissions->studentHistoryAction();
        $me = (string) $this->session->get('gibbonPersonID');
        if ($action === 'Student History_my') {
            $personId = $me;
        } elseif ($action === 'Student History_myChildren') {
            if ($personId === '' || !$this->isMyChild($personId)) {
                throw new ApiException('gibbonPersonID must be one of your children.', 403);
            }
        } else {
            if ($personId === '') {
                throw new ApiException('gibbonPersonID is required.', 422);
            }
        }
        RestTable::requireRow($this->users, $personId, 'Person not found.');
        $yearId = $this->session->get('gibbonSchoolYearID');
        $year = RestTable::requireRow($this->years, $yearId, 'School year not found.');
        $data = $this->history->getAttendanceData($yearId, $personId, $year['firstDay'], $year['lastDay']);

        return [
            'gibbonPersonID' => $personId,
            'gibbonSchoolYearID' => $yearId,
            'data' => method_exists($data, 'toArray') ? $data->toArray() : (array) $data,
        ];
    }

    public function consecutiveAbsences(array $query): array
    {
        $this->permissions->assertAllowsReport('report_consecutiveAbsences', 'You do not have permission to view consecutive absences.');
        $n = (int) ($query['numberOfSchoolDays'] ?? 7);
        if ($n < 1 || $n > 99) {
            throw new ApiException('numberOfSchoolDays must be between 1 and 99.', 422);
        }
        $dates = $this->lastSchoolDays($n);
        if (count($dates) < $n) {
            throw new ApiException('Not enough open school days to build this report.', 422);
        }
        $yearId = $this->session->get('gibbonSchoolYearID');
        $students = $this->db->select(
            "SELECT gibbonPerson.gibbonPersonID, title, surname, preferredName,
                    gibbonFormGroup.gibbonFormGroupID, gibbonFormGroup.name as formGroupName,
                    gibbonFormGroup.nameShort AS formGroup
             FROM gibbonPerson
             JOIN gibbonStudentEnrolment ON gibbonStudentEnrolment.gibbonPersonID=gibbonPerson.gibbonPersonID
             LEFT JOIN gibbonFormGroup ON gibbonFormGroup.gibbonFormGroupID=gibbonStudentEnrolment.gibbonFormGroupID
             WHERE status='Full'
               AND (dateStart IS NULL OR dateStart <= CURRENT_TIMESTAMP)
               AND (dateEnd IS NULL OR dateEnd >= CURRENT_TIMESTAMP)
               AND gibbonStudentEnrolment.gibbonSchoolYearID=:year
             ORDER BY surname, preferredName",
            ['year' => $yearId]
        )->fetchAll();
        $start = $dates[count($dates) - 1];
        $end = $dates[0];
        $logRows = $this->db->select(
            "SELECT gibbonPersonID, date, type, direction
             FROM gibbonAttendanceLogPerson
             WHERE date>=:start AND date<=:end
             ORDER BY timestampTaken DESC, gibbonAttendanceLogPersonID DESC",
            ['start' => $start, 'end' => $end]
        )->fetchAll();
        $latest = [];
        foreach ($logRows as $log) {
            $key = $log['gibbonPersonID'].'|'.$log['date'];
            if (!isset($latest[$key])) {
                $latest[$key] = $log;
            }
        }
        $out = [];
        foreach ($students as $student) {
            $count = 0;
            foreach ($dates as $date) {
                $log = $latest[$student['gibbonPersonID'].'|'.$date] ?? null;
                if ($log && (($log['direction'] ?? '') === 'Out' || stripos((string) ($log['type'] ?? ''), 'Absent') !== false)) {
                    $count++;
                }
            }
            if ($count >= $n) {
                $student['count'] = $count;
                $out[] = $student;
            }
        }

        return ['numberOfSchoolDays' => $n, 'dates' => $dates, 'data' => $out];
    }

    public function notPresent(array $query): array
    {
        $this->permissions->assertAllowsReport('report_studentsNotPresent_byDate', 'You do not have permission to view students not present.');

        return $this->byDateReport('notPresent', $query);
    }

    public function notOnsite(array $query): array
    {
        $this->permissions->assertAllowsReport('report_studentsNotOnsite_byDate', 'You do not have permission to view students not onsite.');

        return $this->byDateReport('notOnsite', $query);
    }

    public function notInClass(array $query): array
    {
        $this->permissions->assertAllowsReport('report_studentsNotInClass_byDate', 'You do not have permission to view students not in class.');
        $date = $this->requireDate($query['date'] ?? $query['currentDate'] ?? null);
        $all = $query['allStudents'] ?? 'N';
        $yearId = $this->session->get('gibbonSchoolYearID');
        $criteria = $this->logs->newQueryCriteria()->pageSize(0);
        if (!empty($query['gibbonYearGroupIDList'])) {
            $criteria->filterBy('yearGroup', is_array($query['gibbonYearGroupIDList']) ? implode(',', $query['gibbonYearGroupIDList']) : $query['gibbonYearGroupIDList']);
        }
        if (!empty($query['types'])) {
            $criteria->filterBy('types', is_array($query['types']) ? implode(',', $query['types']) : $query['types']);
        }

        return [
            'date' => $date,
            'data' => $this->logs->queryStudentsNotInClass($criteria, $yearId, $date, $all)->toArray(),
        ];
    }

    public function formGroupsNotRegistered(array $query): array
    {
        $this->permissions->assertAllowsReport('report_formGroupsNotRegistered_byDate', 'You do not have permission to view unregistered form groups.');
        [$start, $end] = $this->dateRange($query);
        $yearId = $this->session->get('gibbonSchoolYearID');
        $days = $this->schoolDaysBetween($start, $end);
        $logs = $this->db->select(
            "SELECT date, gibbonFormGroupID FROM gibbonAttendanceLogFormGroup WHERE date>=:start AND date<=:end",
            ['start' => $start, 'end' => $end]
        )->fetchAll();
        $taken = [];
        foreach ($logs as $log) {
            $taken[$log['gibbonFormGroupID'].'|'.$log['date']] = true;
        }
        $groups = $this->db->select(
            "SELECT gibbonFormGroupID, name FROM gibbonFormGroup WHERE gibbonSchoolYearID=:year AND attendance='Y'",
            ['year' => $yearId]
        )->fetchAll();
        $data = [];
        foreach ($groups as $group) {
            $history = [];
            $missing = 0;
            foreach ($days as $date) {
                $off = $this->specialDays->getIsFormGroupOffTimetableByDate($yearId, $group['gibbonFormGroupID'], $date);
                $registered = isset($taken[$group['gibbonFormGroupID'].'|'.$date]);
                $status = $off ? 'offTimetable' : ($registered ? 'registered' : 'missing');
                if ($status === 'missing') {
                    $missing++;
                }
                $history[] = ['date' => $date, 'status' => $status];
            }
            if ($missing > 0) {
                $group['history'] = $history;
                $group['missingCount'] = $missing;
                $data[] = $group;
            }
        }

        return ['dateStart' => $start, 'dateEnd' => $end, 'data' => $data];
    }

    public function classesNotRegistered(array $query): array
    {
        $this->permissions->assertAllowsReport('report_courseClassesNotRegistered_byDate', 'You do not have permission to view unregistered classes.');
        [$start, $end] = $this->dateRange($query);
        $yearId = $this->session->get('gibbonSchoolYearID');
        $days = $this->schoolDaysBetween($start, $end);
        $logs = $this->db->select(
            "SELECT date, gibbonCourseClassID FROM gibbonAttendanceLogCourseClass WHERE date>=:start AND date<=:end",
            ['start' => $start, 'end' => $end]
        )->fetchAll();
        $taken = [];
        foreach ($logs as $log) {
            $taken[$log['gibbonCourseClassID'].'|'.$log['date']] = true;
        }
        $scheduled = $this->db->select(
            "SELECT gibbonCourseClass.gibbonCourseClassID, CONCAT(gibbonCourse.nameShort, '.', gibbonCourseClass.nameShort) AS class,
                    gibbonTTDayDate.date
             FROM gibbonCourseClass
             JOIN gibbonCourse ON gibbonCourse.gibbonCourseID=gibbonCourseClass.gibbonCourseID
             JOIN gibbonTTDayRowClass ON gibbonTTDayRowClass.gibbonCourseClassID=gibbonCourseClass.gibbonCourseClassID
             JOIN gibbonTTDayDate ON gibbonTTDayDate.gibbonTTDayID=gibbonTTDayRowClass.gibbonTTDayID
             WHERE gibbonCourse.gibbonSchoolYearID=:year
               AND gibbonCourseClass.attendance='Y'
               AND gibbonTTDayDate.date>=:start AND gibbonTTDayDate.date<=:end",
            ['year' => $yearId, 'start' => $start, 'end' => $end]
        )->fetchAll();
        $byClass = [];
        foreach ($scheduled as $row) {
            $id = $row['gibbonCourseClassID'];
            if (!isset($byClass[$id])) {
                $byClass[$id] = ['gibbonCourseClassID' => $id, 'class' => $row['class'], 'dates' => []];
            }
            $byClass[$id]['dates'][$row['date']] = true;
        }
        $data = [];
        foreach ($byClass as $class) {
            $history = [];
            $missing = 0;
            foreach ($days as $date) {
                if (empty($class['dates'][$date])) {
                    $history[] = ['date' => $date, 'status' => 'noData'];
                    continue;
                }
                $off = $this->specialDays->getIsClassOffTimetableByDate($yearId, $class['gibbonCourseClassID'], $date);
                $registered = isset($taken[$class['gibbonCourseClassID'].'|'.$date]);
                $status = $off ? 'offTimetable' : ($registered ? 'present' : 'absent');
                if ($status === 'absent') {
                    $missing++;
                }
                $history[] = ['date' => $date, 'status' => $status];
            }
            if ($missing > 0) {
                $class['history'] = $history;
                $class['missingCount'] = $missing;
                unset($class['dates']);
                $data[] = $class;
            }
        }

        return ['dateStart' => $start, 'dateEnd' => $end, 'data' => $data];
    }

    public function trends(array $query): array
    {
        $this->permissions->assertAllowsReport('report_graph_byType', 'You do not have permission to view attendance trends.');
        $end = $query['dateEnd'] ?? date('Y-m-d');
        $start = $query['dateStart'] ?? date('Y-m-d', strtotime($end.' -1 month'));
        $this->requireDate($start);
        $this->requireDate($end);
        $yearId = $this->session->get('gibbonSchoolYearID');
        $countClass = $this->settings->getSettingByScope('Attendance', 'countClassAsSchool');
        $formGroups = $query['gibbonFormGroupID'] ?? ['all'];
        if (!is_array($formGroups)) {
            $formGroups = [$formGroups];
        }
        $criteria = $this->logs->newQueryCriteria()->pageSize(0);
        $rows = $this->logs->queryAttendanceCountsByType($criteria, $yearId, $formGroups, $start, $end, $countClass)->toArray();
        $days = $this->schoolDaysBetween($start, $end);
        $series = [];
        foreach ($rows as $row) {
            if (!$this->isSchoolOpen($row['date'])) {
                continue;
            }
            $name = $row['name'];
            if (!isset($series[$name])) {
                $series[$name] = array_fill_keys($days, 0);
            }
            if (isset($series[$name][$row['date']])) {
                $series[$name][$row['date']] += (int) $row['count'];
            }
        }
        $out = [];
        foreach ($series as $name => $counts) {
            $out[$name] = array_values($counts);
        }

        return ['dateStart' => $start, 'dateEnd' => $end, 'days' => $days, 'series' => $out];
    }

    protected function byDateReport(string $kind, array $query): array
    {
        $date = $this->requireDate($query['date'] ?? $query['currentDate'] ?? null);
        $all = $query['allStudents'] ?? 'N';
        $yearId = $this->session->get('gibbonSchoolYearID');
        $countClass = $this->settings->getSettingByScope('Attendance', 'countClassAsSchool');
        $criteria = $this->logs->newQueryCriteria()->pageSize(0);
        $data = $kind === 'notOnsite'
            ? $this->logs->queryStudentsNotOnsite($criteria, $yearId, $date, $all, $countClass)->toArray()
            : $this->logs->queryStudentsNotPresent($criteria, $yearId, $date, $all, $countClass)->toArray();

        return ['date' => $date, 'data' => $data];
    }

    protected function isMyChild(string $gibbonPersonID): bool
    {
        $id = $this->db->selectOne(
            "SELECT gibbonFamilyChild.gibbonPersonID
             FROM gibbonFamilyAdult
             JOIN gibbonFamilyChild ON gibbonFamilyChild.gibbonFamilyID=gibbonFamilyAdult.gibbonFamilyID
             WHERE gibbonFamilyAdult.gibbonPersonID=:adult
               AND gibbonFamilyAdult.childDataAccess='Y'
               AND gibbonFamilyChild.gibbonPersonID=:child
             LIMIT 1",
            ['adult' => $this->session->get('gibbonPersonID'), 'child' => $gibbonPersonID]
        );

        return !empty($id);
    }

    protected function requireDate($date): string
    {
        $date = (string) $date;
        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new ApiException('date is required as YYYY-MM-DD.', 422);
        }
        if ($date > date('Y-m-d')) {
            throw new ApiException('Reports cannot use a future date.', 422);
        }

        return $date;
    }

    protected function dateRange(array $query): array
    {
        $start = $query['dateStart'] ?? $query['date'] ?? date('Y-m-d');
        $end = $query['dateEnd'] ?? $query['date'] ?? $start;
        $this->requireDate($start);
        $this->requireDate($end);
        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }

        return [$start, $end];
    }

    protected function lastSchoolDays(int $n): array
    {
        $out = [];
        $cursor = date('Y-m-d');
        for ($i = 0; $i < 400 && count($out) < $n; $i++) {
            if ($this->isSchoolOpen($cursor)) {
                $out[] = $cursor;
            }
            $cursor = date('Y-m-d', strtotime($cursor.' -1 day'));
        }

        return $out;
    }

    protected function schoolDaysBetween(string $start, string $end): array
    {
        $out = [];
        $cursor = $start;
        while ($cursor <= $end) {
            if ($this->isSchoolOpen($cursor)) {
                $out[] = $cursor;
            }
            $cursor = date('Y-m-d', strtotime($cursor.' +1 day'));
        }

        return $out;
    }

    protected function isSchoolOpen(string $date): bool
    {
        $terms = $this->db->select(
            "SELECT firstDay, lastDay FROM gibbonSchoolYearTerm WHERE gibbonSchoolYearID=:id",
            ['id' => $this->session->get('gibbonSchoolYearID')]
        )->fetchAll();
        $inTerm = false;
        foreach ($terms as $term) {
            if ($date >= $term['firstDay'] && $date <= $term['lastDay']) {
                $inTerm = true;
                break;
            }
        }
        if (!$inTerm) {
            return false;
        }
        $dayOfWeek = date('D', strtotime($date.' 12:00:00'));
        $schoolDay = $this->db->selectOne(
            "SELECT gibbonDaysOfWeekID FROM gibbonDaysOfWeek WHERE nameShort=:d AND schoolDay='Y'",
            ['d' => $dayOfWeek]
        );
        if (empty($schoolDay)) {
            return false;
        }
        $closure = $this->db->selectOne(
            "SELECT gibbonSchoolYearSpecialDayID FROM gibbonSchoolYearSpecialDay WHERE type='School Closure' AND date=:date",
            ['date' => $date]
        );

        return empty($closure);
    }
}
