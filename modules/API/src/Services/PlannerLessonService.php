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
use Gibbon\Domain\Planner\PlannerEntryGateway;
use Gibbon\Domain\Planner\PlannerEntryHomeworkGateway;
use Gibbon\Domain\Planner\UnitClassBlockGateway;
use Gibbon\Domain\Planner\UnitGateway;
use Gibbon\Module\API\Auth\PermissionMapper;
use Gibbon\Module\API\Http\ApiException;

class PlannerLessonService
{
    protected PlannerEntryGateway $entryGateway;
    protected PlannerEntryHomeworkGateway $homeworkGateway;
    protected UnitClassBlockGateway $blockGateway;
    protected UnitGateway $unitGateway;
    protected PermissionMapper $permissions;
    protected Session $session;
    protected Connection $db;

    public function __construct(
        PlannerEntryGateway $entryGateway,
        PlannerEntryHomeworkGateway $homeworkGateway,
        UnitClassBlockGateway $blockGateway,
        UnitGateway $unitGateway,
        PermissionMapper $permissions,
        Session $session,
        Connection $db
    ) {
        $this->entryGateway = $entryGateway;
        $this->homeworkGateway = $homeworkGateway;
        $this->blockGateway = $blockGateway;
        $this->unitGateway = $unitGateway;
        $this->permissions = $permissions;
        $this->session = $session;
        $this->db = $db;
    }

    public function list(array $query): array
    {
        $this->permissions->assertCanViewPlanner();

        $yearID = $query['gibbonSchoolYearID'] ?? $this->session->get('gibbonSchoolYearID');
        $classID = $query['gibbonCourseClassID'] ?? null;
        $from = $query['from'] ?? null;
        $to = $query['to'] ?? null;

        if (!empty($classID)) {
            $this->permissions->assertClassReadable($classID);
        }

        $sql = "SELECT gibbonPlannerEntry.gibbonPlannerEntryID, gibbonPlannerEntry.gibbonCourseClassID,
                       gibbonPlannerEntry.gibbonUnitID, gibbonPlannerEntry.date, gibbonPlannerEntry.timeStart,
                       gibbonPlannerEntry.timeEnd, gibbonPlannerEntry.name, gibbonPlannerEntry.summary,
                       gibbonPlannerEntry.homework, gibbonPlannerEntry.homeworkDueDateTime,
                       gibbonPlannerEntry.homeworkSubmission, gibbonPlannerEntry.viewableStudents,
                       gibbonPlannerEntry.viewableParents, gibbonCourse.nameShort AS course,
                       gibbonCourseClass.nameShort AS class, gibbonUnit.name AS unit
                FROM gibbonPlannerEntry
                JOIN gibbonCourseClass ON (gibbonPlannerEntry.gibbonCourseClassID=gibbonCourseClass.gibbonCourseClassID)
                JOIN gibbonCourse ON (gibbonCourse.gibbonCourseID=gibbonCourseClass.gibbonCourseID)
                LEFT JOIN gibbonUnit ON (gibbonUnit.gibbonUnitID=gibbonPlannerEntry.gibbonUnitID)
                WHERE gibbonCourse.gibbonSchoolYearID=:gibbonSchoolYearID";
        $params = ['gibbonSchoolYearID' => $yearID];

        if (!empty($classID)) {
            $sql .= " AND gibbonPlannerEntry.gibbonCourseClassID=:gibbonCourseClassID";
            $params['gibbonCourseClassID'] = $classID;
        } else {
            $scope = $this->permissions->classScopeSql('gibbonCourseClass');
            $sql .= $scope['sql'];
            $params = array_merge($params, $scope['params']);
        }

        if (!empty($from)) {
            $sql .= " AND gibbonPlannerEntry.date>=:dateFrom";
            $params['dateFrom'] = $from;
        }
        if (!empty($to)) {
            $sql .= " AND gibbonPlannerEntry.date<=:dateTo";
            $params['dateTo'] = $to;
        }

        $sql .= " ORDER BY gibbonPlannerEntry.date, gibbonPlannerEntry.timeStart";

        return $this->db->select($sql, $params)->fetchAll();
    }

    public function get(string $id): array
    {
        $entry = $this->requireEntry($id);
        $this->permissions->assertClassReadable($entry['gibbonCourseClassID']);

        $entry['homeworkSubmissions'] = $this->homeworkGateway->selectBy(['gibbonPlannerEntryID' => $id])->fetchAll();
        $entry['smartBlocks'] = $this->blockGateway->selectBlocksByLessonAndClass($id, $entry['gibbonCourseClassID'])->fetchAll();
        $entry['outcomes'] = $this->db->select(
            "SELECT gibbonPlannerEntryOutcome.*, gibbonOutcome.name
             FROM gibbonPlannerEntryOutcome
             JOIN gibbonOutcome ON (gibbonOutcome.gibbonOutcomeID=gibbonPlannerEntryOutcome.gibbonOutcomeID)
             WHERE gibbonPlannerEntryID=:id
             ORDER BY sequenceNumber",
            ['id' => $id]
        )->fetchAll();

        return $entry;
    }

    public function create(array $body): array
    {
        $this->permissions->assertCanEditPlanner();
        $data = $this->validatePayload($body, true);
        $this->permissions->assertClassWritable($data['gibbonCourseClassID']);

        $id = $this->entryGateway->insert($data);
        if (empty($id)) {
            throw new ApiException('Unable to create lesson plan.', 500);
        }

        return $this->get((string) $id);
    }

    public function update(string $id, array $body): array
    {
        $entry = $this->requireEntry($id);
        $this->permissions->assertClassWritable($entry['gibbonCourseClassID']);

        $data = $this->validatePayload(array_merge($this->extractEditable($entry), $body), false);
        if (!empty($body['gibbonCourseClassID']) && $body['gibbonCourseClassID'] != $entry['gibbonCourseClassID']) {
            $this->permissions->assertClassWritable($data['gibbonCourseClassID']);
        }

        $this->entryGateway->update($id, $data);

        return $this->get($id);
    }

    public function delete(string $id): void
    {
        $entry = $this->requireEntry($id);
        $this->permissions->assertClassWritable($entry['gibbonCourseClassID']);
        $this->entryGateway->delete($id);
    }

    public function slots(string $classID, ?string $from, ?string $to): array
    {
        $this->permissions->assertClassReadable($classID);
        $yearID = $this->session->get('gibbonSchoolYearID');
        $criteria = $this->entryGateway->newQueryCriteria()->pageSize(0);
        $rows = $this->entryGateway->queryPlannerTimeSlotsByClass($criteria, $yearID, $classID)->toArray();

        if (!empty($from) || !empty($to)) {
            $rows = array_values(array_filter($rows, function ($row) use ($from, $to) {
                if (!empty($from) && ($row['date'] ?? '') < $from) {
                    return false;
                }
                if (!empty($to) && ($row['date'] ?? '') > $to) {
                    return false;
                }
                return true;
            }));
        }

        foreach ($rows as &$row) {
            $row['hasLesson'] = !empty($row['gibbonPlannerEntryID']);
        }

        return $rows;
    }

    public function coverage(string $classID, ?string $from, ?string $to): array
    {
        $slots = $this->slots($classID, $from, $to);
        $expected = count($slots);
        $filled = count(array_filter($slots, fn ($row) => !empty($row['hasLesson'])));
        $missing = array_values(array_filter($slots, fn ($row) => empty($row['hasLesson'])));

        return [
            'gibbonCourseClassID' => $classID,
            'from' => $from,
            'to' => $to,
            'expected' => $expected,
            'filled' => $filled,
            'missing' => $expected - $filled,
            'coverageRate' => $expected > 0 ? round($filled / $expected, 4) : null,
            'missingSlots' => $missing,
        ];
    }

    public function units(?string $gibbonCourseID): array
    {
        $this->permissions->assertCanViewPlanner();
        if (empty($gibbonCourseID)) {
            throw new ApiException('gibbonCourseID is required.', 422);
        }

        $criteria = $this->unitGateway->newQueryCriteria()->sortBy('name')->pageSize(0);
        return $this->unitGateway->queryUnitsByCourse($criteria, $gibbonCourseID)->toArray();
    }

    protected function requireEntry(string $id): array
    {
        $entry = $this->entryGateway->getByID($id);
        if (empty($entry)) {
            throw new ApiException('Lesson plan not found.', 404);
        }
        return $entry;
    }

    protected function extractEditable(array $entry): array
    {
        $keys = [
            'gibbonCourseClassID', 'gibbonUnitID', 'date', 'timeStart', 'timeEnd', 'name', 'summary',
            'description', 'teachersNotes', 'homework', 'homeworkDueDateTime', 'homeworkDetails',
            'homeworkTimeCap', 'homeworkLocation', 'homeworkSubmission', 'homeworkSubmissionDateOpen',
            'homeworkSubmissionDrafts', 'homeworkSubmissionType', 'homeworkSubmissionRequired',
            'homeworkCrowdAssess', 'homeworkCrowdAssessOtherTeachersRead', 'homeworkCrowdAssessClassmatesRead',
            'homeworkCrowdAssessOtherStudentsRead', 'homeworkCrowdAssessSubmitterParentsRead',
            'homeworkCrowdAssessClassmatesParentsRead', 'homeworkCrowdAssessOtherParentsRead',
            'viewableStudents', 'viewableParents', 'fields',
        ];
        return array_intersect_key($entry, array_flip($keys));
    }

    protected function validatePayload(array $body, bool $creating): array
    {
        $classID = $body['gibbonCourseClassID'] ?? '';
        $date = $body['date'] ?? '';
        $timeStart = $this->normalizeTime($body['timeStart'] ?? '');
        $timeEnd = $this->normalizeTime($body['timeEnd'] ?? '');
        $name = trim($body['name'] ?? '');

        if ($classID === '' || $date === '' || $timeStart === '' || $timeEnd === '' || $name === '') {
            throw new ApiException('gibbonCourseClassID, date, timeStart, timeEnd and name are required.', 422);
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new ApiException('date must be YYYY-MM-DD.', 422);
        }

        $homework = ($body['homework'] ?? 'N') === 'Y' ? 'Y' : 'N';
        $homeworkDetails = $body['homeworkDetails'] ?? '';
        $homeworkDueDateTime = $body['homeworkDueDateTime'] ?? null;
        if ($homework === 'Y' && ($homeworkDetails === '' || empty($homeworkDueDateTime))) {
            throw new ApiException('homeworkDetails and homeworkDueDateTime are required when homework is Y.', 422);
        }

        $description = $body['description'] ?? '';
        $summary = trim(strip_tags($body['summary'] ?? ''));
        if ($summary === '') {
            $summary = mb_substr(trim(strip_tags($description)), 0, 252);
        }

        $personID = $this->session->get('gibbonPersonID');
        $data = [
            'gibbonCourseClassID' => $classID,
            'gibbonUnitID' => !empty($body['gibbonUnitID']) ? $body['gibbonUnitID'] : null,
            'date' => $date,
            'timeStart' => $timeStart,
            'timeEnd' => $timeEnd,
            'name' => mb_substr($name, 0, 50),
            'summary' => $summary,
            'description' => $description,
            'teachersNotes' => $body['teachersNotes'] ?? '',
            'homework' => $homework,
            'homeworkDueDateTime' => $homework === 'Y' ? $homeworkDueDateTime : null,
            'homeworkDetails' => $homework === 'Y' ? $homeworkDetails : '',
            'homeworkTimeCap' => $homework === 'Y' ? ($body['homeworkTimeCap'] ?? null) : null,
            'homeworkLocation' => $homework === 'Y' ? ($body['homeworkLocation'] ?? 'Out of Class') : null,
            'homeworkSubmission' => ($body['homeworkSubmission'] ?? 'N') === 'Y' ? 'Y' : 'N',
            'homeworkSubmissionDateOpen' => $body['homeworkSubmissionDateOpen'] ?? null,
            'homeworkSubmissionDrafts' => $body['homeworkSubmissionDrafts'] ?? null,
            'homeworkSubmissionType' => $body['homeworkSubmissionType'] ?? '',
            'homeworkSubmissionRequired' => $body['homeworkSubmissionRequired'] ?? null,
            'homeworkCrowdAssess' => ($body['homeworkCrowdAssess'] ?? 'N') === 'Y' ? 'Y' : 'N',
            'homeworkCrowdAssessOtherTeachersRead' => ($body['homeworkCrowdAssessOtherTeachersRead'] ?? 'N') === 'Y' ? 'Y' : 'N',
            'homeworkCrowdAssessClassmatesRead' => ($body['homeworkCrowdAssessClassmatesRead'] ?? 'N') === 'Y' ? 'Y' : 'N',
            'homeworkCrowdAssessOtherStudentsRead' => ($body['homeworkCrowdAssessOtherStudentsRead'] ?? 'N') === 'Y' ? 'Y' : 'N',
            'homeworkCrowdAssessSubmitterParentsRead' => ($body['homeworkCrowdAssessSubmitterParentsRead'] ?? 'N') === 'Y' ? 'Y' : 'N',
            'homeworkCrowdAssessClassmatesParentsRead' => ($body['homeworkCrowdAssessClassmatesParentsRead'] ?? 'N') === 'Y' ? 'Y' : 'N',
            'homeworkCrowdAssessOtherParentsRead' => ($body['homeworkCrowdAssessOtherParentsRead'] ?? 'N') === 'Y' ? 'Y' : 'N',
            'viewableParents' => ($body['viewableParents'] ?? 'N') === 'Y' ? 'Y' : 'N',
            'viewableStudents' => ($body['viewableStudents'] ?? 'Y') === 'N' ? 'N' : 'Y',
            'gibbonPersonIDLastEdit' => $personID,
        ];

        if ($creating) {
            $data['gibbonPersonIDCreator'] = $personID;
            $data['fields'] = isset($body['fields']) ? (is_string($body['fields']) ? $body['fields'] : json_encode($body['fields'])) : null;
        }

        return $data;
    }

    protected function normalizeTime(string $time): string
    {
        if (preg_match('/^\d{2}:\d{2}$/', $time)) {
            return $time.':00';
        }
        return $time;
    }
}
