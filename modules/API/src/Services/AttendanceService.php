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
use Gibbon\Domain\Attendance\AttendanceCodeGateway;
use Gibbon\Domain\Attendance\AttendanceLogCourseClassGateway;
use Gibbon\Domain\Attendance\AttendanceLogPersonGateway;
use Gibbon\Domain\FormGroups\FormGroupGateway;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Domain\Timetable\CourseClassGateway;
use Gibbon\Domain\User\UserGateway;
use Gibbon\Module\API\Auth\PermissionMapper;
use Gibbon\Module\API\Http\ApiException;
use Gibbon\Module\API\Support\RestTable;

class AttendanceService
{
    public function __construct(
        protected PermissionMapper $permissions,
        protected Connection $db,
        protected Session $session,
        protected SettingGateway $settings,
        protected AttendanceCodeGateway $codes,
        protected AttendanceLogPersonGateway $personLogs,
        protected AttendanceLogCourseClassGateway $classLogs,
        protected CourseClassGateway $classes,
        protected FormGroupGateway $formGroups,
        protected UserGateway $users
    ) {
    }

    public function listCodes(): array
    {
        $this->permissions->assertCanTakeAnyAttendance();

        return $this->db->select(
            "SELECT * FROM gibbonAttendanceCode ORDER BY sequenceNumber, name"
        )->fetchAll();
    }

    public function getCode(string $id): array
    {
        $this->permissions->assertCanTakeAnyAttendance();

        return RestTable::requireRow($this->codes, $id, 'Attendance code not found.');
    }

    public function createCode(array $body): array
    {
        $this->permissions->assertCanManageAttendanceCodes();
        $data = RestTable::pick($body, [
            'name', 'nameShort', 'direction', 'scope', 'sequenceNumber',
            'active', 'reportable', 'prefill', 'future', 'gibbonRoleIDAll',
        ]);
        RestTable::requireFields($data, ['name', 'nameShort', 'direction', 'scope', 'sequenceNumber']);
        $this->assertCodeValues($data);
        $data = RestTable::defaults($data, [
            'type' => 'Additional',
            'active' => 'Y',
            'reportable' => 'Y',
            'prefill' => 'Y',
            'future' => 'N',
            'gibbonRoleIDAll' => '',
        ]);
        $data['type'] = 'Additional';
        if (!$this->codes->unique($data, ['name']) || !$this->codes->unique($data, ['nameShort'])) {
            throw new ApiException('name and nameShort must be unique.', 422);
        }

        return RestTable::create($this->codes, $data);
    }

    public function updateCode(string $id, array $body): array
    {
        $this->permissions->assertCanManageAttendanceCodes();
        $existing = RestTable::requireRow($this->codes, $id, 'Attendance code not found.');
        $data = RestTable::pick($body, [
            'name', 'nameShort', 'direction', 'scope', 'sequenceNumber',
            'active', 'reportable', 'prefill', 'future', 'gibbonRoleIDAll',
        ]);
        $this->assertCodeValues($data);
        $merged = array_merge($existing, $data);
        if (isset($data['name']) && !$this->codes->unique($merged, ['name'], $id)) {
            throw new ApiException('name must be unique.', 422);
        }
        if (isset($data['nameShort']) && !$this->codes->unique($merged, ['nameShort'], $id)) {
            throw new ApiException('nameShort must be unique.', 422);
        }
        RestTable::update($this->codes, $id, $data, 'Attendance code not found.');

        return $this->codes->getByID($id);
    }

    public function deleteCode(string $id): void
    {
        $this->permissions->assertCanManageAttendanceCodes();
        $row = RestTable::requireRow($this->codes, $id, 'Attendance code not found.');
        if (($row['type'] ?? '') === 'Core') {
            throw new ApiException('Core attendance codes cannot be deleted.', 422);
        }
        RestTable::delete($this->codes, $id, 'Attendance code not found.');
    }

    protected function assertCodeValues(array $data): void
    {
        if (isset($data['direction']) && !in_array($data['direction'], ['In', 'Out'], true)) {
            throw new ApiException('direction must be In or Out.', 422);
        }
        if (isset($data['scope']) && !in_array($data['scope'], ['Onsite', 'Onsite - Late', 'Offsite', 'Offsite - Left', 'Offsite - Late'], true)) {
            throw new ApiException('scope must be Onsite, Onsite - Late, Offsite, Offsite - Left or Offsite - Late.', 422);
        }
        if (isset($data['sequenceNumber']) && !is_numeric($data['sequenceNumber'])) {
            throw new ApiException('sequenceNumber must be numeric.', 422);
        }
        foreach (['active', 'reportable', 'prefill', 'future'] as $flag) {
            if (isset($data[$flag]) && !in_array($data[$flag], ['Y', 'N'], true)) {
                throw new ApiException($flag.' must be Y or N.', 422);
            }
        }
    }

    public function getClassSheet(string $gibbonCourseClassID, array $query): array
    {
        $this->permissions->assertCanTakeClassAttendance();
        $class = RestTable::requireRow($this->classes, $gibbonCourseClassID, 'Class not found.');
        $date = $this->requireDate($query['date'] ?? null);
        $ttId = $query['gibbonTTDayRowClassID'] ?? null;
        $ttId = ($ttId === '') ? null : $ttId;
        $this->assertSchoolDay($date);

        $students = [];
        foreach ($this->classStudents($gibbonCourseClassID, $date, $ttId) as $student) {
            $log = $this->latestClassLog($student['gibbonPersonID'], $date, $gibbonCourseClassID, $ttId);
            $students[] = [
                'gibbonPersonID' => $student['gibbonPersonID'],
                'surname' => $student['surname'],
                'preferredName' => $student['preferredName'],
                'type' => $log['type'] ?? $this->settings->getSettingByScope('Attendance', 'defaultClassAttendanceType'),
                'reason' => $log['reason'] ?? '',
                'comment' => $log['comment'] ?? '',
                'direction' => $log['direction'] ?? null,
                'timestampTaken' => $log['timestampTaken'] ?? null,
            ];
        }

        $taken = $this->classLogRow($gibbonCourseClassID, $date, $ttId);

        return [
            'gibbonCourseClassID' => $gibbonCourseClassID,
            'attendance' => $class['attendance'] ?? null,
            'date' => $date,
            'gibbonTTDayRowClassID' => $ttId,
            'taken' => !empty($taken),
            'defaultType' => $this->settings->getSettingByScope('Attendance', 'defaultClassAttendanceType'),
            'students' => $students,
        ];
    }

    public function takeClassAttendance(string $gibbonCourseClassID, array $body): array
    {
        $this->permissions->assertCanTakeClassAttendance();
        RestTable::requireRow($this->classes, $gibbonCourseClassID, 'Class not found.');
        $date = $this->requireDate($body['date'] ?? null);
        $this->assertSchoolDay($date);
        $ttId = $body['gibbonTTDayRowClassID'] ?? null;
        $ttId = ($ttId === '') ? null : $ttId;
        $records = $body['records'] ?? null;
        if (!is_array($records) || $records === []) {
            throw new ApiException('records must be a non-empty array.', 422);
        }

        $existing = $this->classLogRow($gibbonCourseClassID, $date, $ttId);
        $taker = $this->session->get('gibbonPersonID');
        $now = date('Y-m-d H:i:s');
        if (!empty($existing)) {
            $this->classLogs->update($existing['gibbonAttendanceLogCourseClassID'], [
                'gibbonPersonIDTaker' => $taker,
                'timestampTaken' => $now,
            ]);
        } else {
            $inserted = $this->classLogs->insert([
                'gibbonPersonIDTaker' => $taker,
                'gibbonCourseClassID' => $gibbonCourseClassID,
                'gibbonTTDayRowClassID' => $ttId,
                'date' => $date,
                'timestampTaken' => $now,
            ]);
            if (empty($inserted)) {
                throw new ApiException('Unable to save class attendance log.', 500);
            }
        }

        $recordFirstClassAsSchool = $this->settings->getSettingByScope('Attendance', 'recordFirstClassAsSchool');
        $allowed = array_column($this->classStudents($gibbonCourseClassID, $date, $ttId), 'gibbonPersonID');

        foreach ($records as $i => $record) {
            if (!is_array($record)) {
                throw new ApiException("records[$i] must be an object.", 422);
            }
            $personId = (string) ($record['gibbonPersonID'] ?? '');
            $type = (string) ($record['type'] ?? '');
            if ($personId === '' || $type === '') {
                throw new ApiException("records[$i] requires gibbonPersonID and type.", 422);
            }
            if (!in_array($personId, $allowed, true)) {
                throw new ApiException("Student $personId is not in this class on $date.", 422);
            }
            $code = $this->codeByName($type);
            $data = [
                'gibbonAttendanceCodeID' => $code['gibbonAttendanceCodeID'],
                'gibbonPersonID' => $personId,
                'context' => 'Class',
                'direction' => $code['direction'],
                'type' => $type,
                'reason' => (string) ($record['reason'] ?? ''),
                'comment' => (string) ($record['comment'] ?? ''),
                'gibbonPersonIDTaker' => $taker,
                'gibbonCourseClassID' => $gibbonCourseClassID,
                'gibbonTTDayRowClassID' => $ttId,
                'date' => $date,
                'timestampTaken' => $now,
            ];
            $matchId = $this->matchingClassLogId($personId, $date, $gibbonCourseClassID, $ttId);
            if ($matchId) {
                $this->personLogs->update($matchId, $data);
            } else {
                $this->personLogs->insert($data);
            }
            if ($recordFirstClassAsSchool === 'Y' && !$this->hasPersonContextLog($personId, $date)) {
                $school = $data;
                $school['context'] = 'Person';
                $this->personLogs->insert($school);
            }
        }

        return $this->getClassSheet($gibbonCourseClassID, ['date' => $date, 'gibbonTTDayRowClassID' => $ttId]);
    }

    public function getFormGroupSheet(string $gibbonFormGroupID, array $query): array
    {
        $this->permissions->assertCanTakeFormGroupAttendance();
        $group = $this->requireWritableFormGroup($gibbonFormGroupID);
        $date = $this->requireDate($query['date'] ?? null);
        $this->assertSchoolDay($date);

        $default = $this->settings->getSettingByScope('Attendance', 'defaultFormGroupAttendanceType');
        $students = [];
        foreach ($this->formGroupStudents($gibbonFormGroupID, $date) as $student) {
            $log = $this->latestFormGroupLog($student['gibbonPersonID'], $date);
            $students[] = [
                'gibbonPersonID' => $student['gibbonPersonID'],
                'surname' => $student['surname'],
                'preferredName' => $student['preferredName'],
                'type' => $log['type'] ?? $default,
                'reason' => $log['reason'] ?? '',
                'comment' => $log['comment'] ?? '',
                'direction' => $log['direction'] ?? null,
                'timestampTaken' => $log['timestampTaken'] ?? null,
            ];
        }

        $taken = $this->db->selectOne(
            "SELECT gibbonAttendanceLogFormGroupID FROM gibbonAttendanceLogFormGroup
             WHERE gibbonFormGroupID=:id AND date=:date ORDER BY timestampTaken DESC LIMIT 1",
            ['id' => $gibbonFormGroupID, 'date' => $date]
        );

        return [
            'gibbonFormGroupID' => $gibbonFormGroupID,
            'name' => $group['name'] ?? null,
            'date' => $date,
            'taken' => !empty($taken),
            'defaultType' => $default,
            'students' => $students,
        ];
    }

    public function takeFormGroupAttendance(string $gibbonFormGroupID, array $body): array
    {
        $this->permissions->assertCanTakeFormGroupAttendance();
        $this->requireWritableFormGroup($gibbonFormGroupID);
        $date = $this->requireDate($body['date'] ?? null);
        $this->assertSchoolDay($date);
        $records = $body['records'] ?? null;
        if (!is_array($records) || $records === []) {
            throw new ApiException('records must be a non-empty array.', 422);
        }

        $this->db->insert(
            'INSERT INTO gibbonAttendanceLogFormGroup SET gibbonPersonIDTaker=:taker, gibbonFormGroupID=:id, date=:date, timestampTaken=:taken',
            [
                'taker' => $this->session->get('gibbonPersonID'),
                'id' => $gibbonFormGroupID,
                'date' => $date,
                'taken' => date('Y-m-d H:i:s'),
            ]
        );

        $allowed = array_column($this->formGroupStudents($gibbonFormGroupID, $date), 'gibbonPersonID');
        $taker = $this->session->get('gibbonPersonID');
        $now = date('Y-m-d H:i:s');

        foreach ($records as $i => $record) {
            if (!is_array($record)) {
                throw new ApiException("records[$i] must be an object.", 422);
            }
            $personId = (string) ($record['gibbonPersonID'] ?? '');
            $type = (string) ($record['type'] ?? '');
            if ($personId === '' || $type === '') {
                throw new ApiException("records[$i] requires gibbonPersonID and type.", 422);
            }
            if (!in_array($personId, $allowed, true)) {
                throw new ApiException("Student $personId is not in this form group on $date.", 422);
            }
            $code = $this->codeByName($type);
            $data = [
                'gibbonAttendanceCodeID' => $code['gibbonAttendanceCodeID'],
                'gibbonPersonID' => $personId,
                'context' => 'Form Group',
                'direction' => $code['direction'],
                'type' => $type,
                'reason' => (string) ($record['reason'] ?? ''),
                'comment' => (string) ($record['comment'] ?? ''),
                'gibbonPersonIDTaker' => $taker,
                'gibbonFormGroupID' => $gibbonFormGroupID,
                'date' => $date,
                'timestampTaken' => $now,
            ];
            $existing = $this->db->select(
                "SELECT * FROM gibbonAttendanceLogPerson WHERE gibbonPersonID=:id AND date=:date ORDER BY gibbonAttendanceLogPersonID DESC",
                ['id' => $personId, 'date' => $date]
            )->fetch();
            if (!empty($existing) && ($existing['context'] ?? '') === 'Form Group' && ($existing['type'] ?? '') === $type && ($existing['direction'] ?? '') === $code['direction']) {
                $this->personLogs->update($existing['gibbonAttendanceLogPersonID'], $data);
            } else {
                $this->personLogs->insert($data);
            }
        }

        return $this->getFormGroupSheet($gibbonFormGroupID, ['date' => $date]);
    }

    public function getPersonSheet(string $gibbonPersonID, array $query): array
    {
        $this->permissions->assertCanTakePersonAttendance();
        $person = RestTable::requireRow($this->users, $gibbonPersonID, 'Person not found.');
        $date = $this->requireDate($query['date'] ?? null);
        $this->assertSchoolDay($date);
        $logs = $this->db->select(
            "SELECT gibbonAttendanceLogPersonID, context, type, direction, reason, comment, gibbonCourseClassID, gibbonFormGroupID, gibbonTTDayRowClassID, timestampTaken
             FROM gibbonAttendanceLogPerson
             WHERE gibbonPersonID=:id AND date=:date
             ORDER BY timestampTaken DESC, gibbonAttendanceLogPersonID DESC",
            ['id' => $gibbonPersonID, 'date' => $date]
        )->fetchAll();

        return [
            'gibbonPersonID' => $gibbonPersonID,
            'surname' => $person['surname'] ?? null,
            'preferredName' => $person['preferredName'] ?? null,
            'date' => $date,
            'logs' => $logs,
        ];
    }

    public function takePersonAttendance(string $gibbonPersonID, array $body): array
    {
        $this->permissions->assertCanTakePersonAttendance();
        RestTable::requireRow($this->users, $gibbonPersonID, 'Person not found.');
        $date = $this->requireDate($body['date'] ?? null);
        $this->assertSchoolDay($date);
        $type = (string) ($body['type'] ?? '');
        if ($type === '') {
            throw new ApiException('Missing required fields: type.', 422);
        }
        $code = $this->codeByName($type);
        $reason = (string) ($body['reason'] ?? '');
        $comment = (string) ($body['comment'] ?? '');
        $taker = $this->session->get('gibbonPersonID');
        $now = date('Y-m-d H:i:s');

        $rows = $this->db->select(
            "SELECT * FROM gibbonAttendanceLogPerson WHERE gibbonPersonID=:id AND date=:date ORDER BY gibbonAttendanceLogPersonID DESC",
            ['id' => $gibbonPersonID, 'date' => $date]
        )->fetchAll();
        $existing = $rows[0] ?? [];
        $samePersonType = !empty($existing) && ($existing['context'] ?? '') === 'Person' && ($existing['type'] ?? '') === $type;
        $data = [
            'gibbonAttendanceCodeID' => $code['gibbonAttendanceCodeID'],
            'gibbonPersonID' => $gibbonPersonID,
            'direction' => $code['direction'],
            'type' => $type,
            'context' => 'Person',
            'reason' => $reason,
            'comment' => $comment,
            'gibbonPersonIDTaker' => $taker,
            'date' => $date,
            'timestampTaken' => $now,
        ];

        if ($samePersonType && ($existing['direction'] ?? '') === $code['direction'] && (int) ($existing['gibbonCourseClassID'] ?? 0) === 0) {
            $this->personLogs->update($existing['gibbonAttendanceLogPersonID'], $data);
        } else {
            $this->personLogs->insert($data);
        }

        return $this->getPersonSheet($gibbonPersonID, ['date' => $date]);
    }

    protected function requireWritableFormGroup(string $id): array
    {
        if ($this->permissions->canTakeAllFormGroups()) {
            return RestTable::requireRow($this->formGroups, $id, 'Form group not found.');
        }
        $personId = $this->session->get('gibbonPersonID');
        $row = $this->db->selectOne(
            "SELECT * FROM gibbonFormGroup
             WHERE gibbonFormGroupID=:id
             AND gibbonSchoolYearID=:year
             AND attendance='Y'
             AND (gibbonPersonIDTutor=:p1 OR gibbonPersonIDTutor2=:p2 OR gibbonPersonIDTutor3=:p3)",
            [
                'id' => $id,
                'year' => $this->session->get('gibbonSchoolYearID'),
                'p1' => $personId,
                'p2' => $personId,
                'p3' => $personId,
            ]
        );
        if (empty($row) || empty($row['gibbonFormGroupID'])) {
            throw new ApiException('You can only take attendance for form groups you tutor.', 403);
        }

        return $row;
    }

    protected function classStudents(string $classId, string $date, ?string $ttId): array
    {
        $data = ['gibbonCourseClassID' => $classId, 'date' => $date];
        $sql = "SELECT gibbonPerson.surname, gibbonPerson.preferredName, gibbonPerson.gibbonPersonID
                FROM gibbonCourseClassPerson
                INNER JOIN gibbonPerson ON gibbonCourseClassPerson.gibbonPersonID=gibbonPerson.gibbonPersonID
                LEFT JOIN (
                    SELECT gibbonTTDayRowClass.gibbonCourseClassID, gibbonTTDayRowClass.gibbonTTDayRowClassID
                    FROM gibbonTTDayDate
                    JOIN gibbonTTDayRowClass ON (gibbonTTDayDate.gibbonTTDayID=gibbonTTDayRowClass.gibbonTTDayID)
                    WHERE gibbonTTDayDate.date=:date
                ) AS gibbonTTDayRowClassSubset ";
        if (!empty($ttId)) {
            $data['gibbonTTDayRowClassID'] = $ttId;
            $sql .= " ON (gibbonTTDayRowClassSubset.gibbonCourseClassID=gibbonCourseClassPerson.gibbonCourseClassID AND gibbonTTDayRowClassSubset.gibbonTTDayRowClassID=:gibbonTTDayRowClassID) ";
        } else {
            $sql .= " ON (gibbonTTDayRowClassSubset.gibbonCourseClassID=gibbonCourseClassPerson.gibbonCourseClassID) ";
        }
        $sql .= "LEFT JOIN gibbonTTDayRowClassException ON (gibbonTTDayRowClassException.gibbonTTDayRowClassID=gibbonTTDayRowClassSubset.gibbonTTDayRowClassID AND gibbonTTDayRowClassException.gibbonPersonID=gibbonCourseClassPerson.gibbonPersonID)
                WHERE gibbonCourseClassPerson.gibbonCourseClassID=:gibbonCourseClassID
                AND status='Full' AND role='Student'
                AND (dateStart IS NULL OR dateStart<=:date) AND (dateEnd IS NULL OR dateEnd>=:date)
                GROUP BY gibbonCourseClassPerson.gibbonPersonID
                HAVING COUNT(gibbonTTDayRowClassExceptionID) = 0
                ORDER BY surname, preferredName";

        return $this->db->select($sql, $data)->fetchAll();
    }

    protected function formGroupStudents(string $formGroupId, string $date): array
    {
        return $this->db->select(
            "SELECT gibbonPerson.preferredName, gibbonPerson.surname, gibbonPerson.gibbonPersonID
             FROM gibbonStudentEnrolment
             INNER JOIN gibbonPerson ON gibbonStudentEnrolment.gibbonPersonID=gibbonPerson.gibbonPersonID
             WHERE gibbonFormGroupID=:id AND status='Full'
             AND (dateStart IS NULL OR dateStart<=:date) AND (dateEnd IS NULL OR dateEnd>=:date)
             ORDER BY rollOrder, surname, preferredName",
            ['id' => $formGroupId, 'date' => $date]
        )->fetchAll();
    }

    protected function classLogRow(string $classId, string $date, ?string $ttId): array
    {
        if (!empty($ttId)) {
            $row = $this->classLogs->selectBy([
                'gibbonCourseClassID' => $classId,
                'date' => $date,
                'gibbonTTDayRowClassID' => $ttId,
            ])->fetch();
        } else {
            $row = $this->classLogs->selectBy([
                'gibbonCourseClassID' => $classId,
                'date' => $date,
            ])->fetch();
        }

        return is_array($row) ? $row : [];
    }

    protected function latestClassLog(string $personId, string $date, string $classId, ?string $ttId): array
    {
        $rows = $this->personLogs->selectClassAttendanceLogsByPersonAndDate($classId, $personId, $date)->fetchAll();
        foreach ($rows as $row) {
            if (empty($ttId) || empty($row['gibbonTTDayRowClassID']) || $row['gibbonTTDayRowClassID'] == $ttId) {
                return $row;
            }
        }

        return [];
    }

    protected function matchingClassLogId(string $personId, string $date, string $classId, ?string $ttId): ?string
    {
        $rows = $this->db->select(
            "SELECT * FROM gibbonAttendanceLogPerson WHERE gibbonPersonID=:id AND date=:date ORDER BY gibbonAttendanceLogPersonID DESC",
            ['id' => $personId, 'date' => $date]
        )->fetchAll();
        foreach ($rows as $row) {
            if (($row['context'] ?? '') !== 'Class' || ($row['gibbonCourseClassID'] ?? '') != $classId) {
                continue;
            }
            if (empty($row['gibbonTTDayRowClassID']) || $row['gibbonTTDayRowClassID'] == $ttId) {
                return (string) $row['gibbonAttendanceLogPersonID'];
            }
        }

        return null;
    }

    protected function latestFormGroupLog(string $personId, string $date): array
    {
        $row = $this->db->selectOne(
            "SELECT type, reason, comment, direction, timestampTaken
             FROM gibbonAttendanceLogPerson
             WHERE gibbonPersonID=:id AND date=:date AND context='Form Group'
             ORDER BY timestampTaken DESC, gibbonAttendanceLogPersonID DESC
             LIMIT 1",
            ['id' => $personId, 'date' => $date]
        );

        return is_array($row) ? $row : [];
    }

    protected function hasPersonContextLog(string $personId, string $date): bool
    {
        $id = $this->db->selectOne(
            "SELECT gibbonAttendanceLogPersonID FROM gibbonAttendanceLogPerson
             WHERE gibbonPersonID=:id AND date=:date AND context='Person' LIMIT 1",
            ['id' => $personId, 'date' => $date]
        );

        return !empty($id);
    }

    protected function codeByName(string $type): array
    {
        $code = $this->db->selectOne(
            "SELECT * FROM gibbonAttendanceCode WHERE name=:name AND active='Y'",
            ['name' => $type]
        );
        if (empty($code) || empty($code['gibbonAttendanceCodeID'])) {
            throw new ApiException("Unknown attendance code: {$type}. Use GET /v1/attendance/codes.", 422);
        }

        return $code;
    }

    protected function requireDate($date): string
    {
        $date = (string) $date;
        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new ApiException('date is required as YYYY-MM-DD.', 422);
        }

        return $date;
    }

    protected function assertSchoolDay(string $date): void
    {
        if ($date > date('Y-m-d')) {
            throw new ApiException('Attendance cannot be recorded for a future date.', 422);
        }
        if (!$this->isSchoolOpen($date)) {
            throw new ApiException('This date is not an open school day.', 422);
        }
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
