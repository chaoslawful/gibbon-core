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
use Gibbon\Domain\Markbook\MarkbookColumnGateway;
use Gibbon\Domain\Markbook\MarkbookEntryGateway;
use Gibbon\Domain\School\GradeScaleGateway;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Domain\Timetable\CourseClassGateway;
use Gibbon\Module\API\Auth\PermissionMapper;
use Gibbon\Module\API\Http\ApiException;
use Gibbon\Module\API\Support\RestTable;
use Gibbon\UI\Components\Alert;

class MarkbookService
{
    public function __construct(
        protected PermissionMapper $permissions,
        protected Connection $db,
        protected Session $session,
        protected SettingGateway $settings,
        protected MarkbookColumnGateway $columns,
        protected MarkbookEntryGateway $entries,
        protected GradeScaleGateway $scales,
        protected CourseClassGateway $classes,
        protected Alert $alerts
    ) {
    }

    public function listScales(): array
    {
        $this->permissions->assertCanEditMarkbook();
        $criteria = $this->scales->newQueryCriteria()->sortBy('name')->pageSize(0);

        return $this->scales->queryGradeScales($criteria)->toArray();
    }

    public function getScale(string $id): array
    {
        $this->permissions->assertCanEditMarkbook();
        $scale = RestTable::requireRow($this->scales, $id, 'Grade scale not found.');
        $criteria = $this->scales->newQueryCriteria()->sortBy('sequenceNumber')->pageSize(0);
        $scale['grades'] = $this->scales->queryGradeScaleGrades($criteria, $id)->toArray();

        return $scale;
    }

    public function listColumns(string $gibbonCourseClassID): array
    {
        $this->permissions->assertMarkbookClassWritable($gibbonCourseClassID);
        RestTable::requireRow($this->classes, $gibbonCourseClassID, 'Class not found.');
        $criteria = $this->columns->newQueryCriteria()->sortBy('sequenceNumber')->pageSize(0);
        $types = array_values(array_filter(array_map('trim', explode(',', (string) $this->settings->getSettingByScope('Markbook', 'markbookType')))));

        return [
            'gibbonCourseClassID' => $gibbonCourseClassID,
            'types' => $types,
            'enableEffort' => $this->settings->getSettingByScope('Markbook', 'enableEffort'),
            'data' => $this->columns->queryMarkbookColumnsByClass($criteria, $gibbonCourseClassID)->toArray(),
        ];
    }

    public function getColumn(string $id): array
    {
        $column = RestTable::requireRow($this->columns, $id, 'Markbook column not found.');
        $this->permissions->assertMarkbookClassWritable($column['gibbonCourseClassID']);

        return $column;
    }

    public function createColumn(string $gibbonCourseClassID, array $body): array
    {
        $this->permissions->assertMarkbookClassWritable($gibbonCourseClassID);
        RestTable::requireRow($this->classes, $gibbonCourseClassID, 'Class not found.');
        $data = $this->columnPayload($body, true);
        $data['gibbonCourseClassID'] = $gibbonCourseClassID;
        $data['gibbonPersonIDCreator'] = $this->session->get('gibbonPersonID');
        $data['gibbonPersonIDLastEdit'] = $this->session->get('gibbonPersonID');
        $max = $this->db->selectOne(
            'SELECT MAX(sequenceNumber) FROM gibbonMarkbookColumn WHERE gibbonCourseClassID=:id',
            ['id' => $gibbonCourseClassID]
        );
        $data['sequenceNumber'] = ((int) $max) + 1;
        if (empty($data['gibbonSchoolYearTermID']) && !empty($data['date'])) {
            $termId = $this->db->selectOne(
                "SELECT gibbonSchoolYearTermID FROM gibbonSchoolYearTerm
                 WHERE gibbonSchoolYearID=:year AND :date BETWEEN firstDay AND lastDay",
                ['year' => $this->session->get('gibbonSchoolYearID'), 'date' => $data['date']]
            );
            $data['gibbonSchoolYearTermID'] = $termId ?: null;
        }

        return RestTable::create($this->columns, $data);
    }

    public function updateColumn(string $id, array $body): array
    {
        $column = $this->getColumn($id);
        $data = $this->columnPayload(array_merge($column, $body), false);
        $changed = RestTable::pick($body, array_keys($data));
        if (isset($body['attainment']) && ($data['attainment'] ?? '') === 'N') {
            $changed['gibbonScaleIDAttainment'] = null;
        }
        if (($data['effort'] ?? 'N') === 'N' && (isset($body['effort']) || $this->settings->getSettingByScope('Markbook', 'enableEffort') !== 'Y')) {
            $changed['gibbonScaleIDEffort'] = null;
        }
        if (isset($body['complete']) && ($data['complete'] ?? 'N') === 'N') {
            $changed['completeDate'] = null;
        }
        foreach (['gibbonScaleIDAttainment', 'gibbonScaleIDEffort', 'gibbonUnitID', 'gibbonPlannerEntryID', 'gibbonSchoolYearTermID', 'completeDate'] as $key) {
            if (array_key_exists($key, $changed)) {
                $changed = RestTable::emptyToNull($changed, [$key]);
            }
        }
        $changed['gibbonPersonIDLastEdit'] = $this->session->get('gibbonPersonID');

        return RestTable::update($this->columns, $id, $changed, 'Markbook column not found.');
    }

    public function deleteColumn(string $id): void
    {
        $column = $this->getColumn($id);
        $this->db->delete(
            'DELETE FROM gibbonMarkbookEntry WHERE gibbonMarkbookColumnID=:id',
            ['id' => $column['gibbonMarkbookColumnID']]
        );
        RestTable::delete($this->columns, $id, 'Markbook column not found.');
    }

    public function listEntries(string $id): array
    {
        $column = $this->getColumn($id);
        $students = $this->classStudents($column['gibbonCourseClassID']);
        $rows = $this->entries->selectBy(['gibbonMarkbookColumnID' => $id])->fetchAll();
        $byStudent = [];
        foreach ($rows as $row) {
            $byStudent[$row['gibbonPersonIDStudent']] = $row;
        }
        $out = [];
        foreach ($students as $student) {
            $entry = $byStudent[$student['gibbonPersonID']] ?? [];
            $out[] = [
                'gibbonPersonIDStudent' => $student['gibbonPersonID'],
                'surname' => $student['surname'],
                'preferredName' => $student['preferredName'],
                'gibbonMarkbookEntryID' => $entry['gibbonMarkbookEntryID'] ?? null,
                'attainmentValue' => $entry['attainmentValue'] ?? null,
                'attainmentDescriptor' => $entry['attainmentDescriptor'] ?? null,
                'attainmentConcern' => $entry['attainmentConcern'] ?? null,
                'effortValue' => $entry['effortValue'] ?? null,
                'effortDescriptor' => $entry['effortDescriptor'] ?? null,
                'effortConcern' => $entry['effortConcern'] ?? null,
                'comment' => $entry['comment'] ?? null,
            ];
        }

        return [
            'column' => $column,
            'data' => $out,
        ];
    }

    public function saveEntries(string $id, array $body): array
    {
        $column = $this->getColumn($id);
        $entries = $body['entries'] ?? null;
        if (!is_array($entries) || $entries === []) {
            throw new ApiException('entries must be a non-empty array.', 422);
        }
        $allowed = array_column($this->classStudents($column['gibbonCourseClassID']), 'gibbonPersonID');
        $enableEffort = $this->settings->getSettingByScope('Markbook', 'enableEffort');
        $editor = $this->session->get('gibbonPersonID');

        foreach ($entries as $i => $row) {
            if (!is_array($row)) {
                throw new ApiException("entries[$i] must be an object.", 422);
            }
            $studentId = (string) ($row['gibbonPersonIDStudent'] ?? '');
            if ($studentId === '' || !in_array($studentId, $allowed, true)) {
                throw new ApiException("entries[$i] has an unknown gibbonPersonIDStudent.", 422);
            }
            $payload = $this->entryPayload($column, $row, $enableEffort === 'Y');
            $payload['gibbonMarkbookColumnID'] = $id;
            $payload['gibbonPersonIDStudent'] = $studentId;
            $payload['gibbonPersonIDLastEdit'] = $editor;
            $existing = $this->entries->selectBy([
                'gibbonMarkbookColumnID' => $id,
                'gibbonPersonIDStudent' => $studentId,
            ])->fetch();
            if (!empty($existing)) {
                $this->entries->update($existing['gibbonMarkbookEntryID'], $payload);
            } else {
                $this->entries->insert($payload);
            }
            $this->alerts->recalculateAlerts($studentId);
        }

        return $this->listEntries($id);
    }

    protected function columnPayload(array $body, bool $creating): array
    {
        $data = RestTable::pick($body, [
            'name', 'description', 'type', 'date', 'columnColor',
            'attainment', 'gibbonScaleIDAttainment',
            'effort', 'gibbonScaleIDEffort',
            'comment', 'uploadedResponse', 'complete', 'completeDate',
            'viewableStudents', 'viewableParents',
            'gibbonUnitID', 'gibbonPlannerEntryID', 'gibbonSchoolYearTermID',
        ]);
        if ($creating) {
            RestTable::requireFields($data, ['name', 'description', 'type', 'date']);
        }
        $enableEffort = $this->settings->getSettingByScope('Markbook', 'enableEffort');
        $data = RestTable::defaults($data, [
            'columnColor' => '',
            'attainment' => 'Y',
            'effort' => $enableEffort === 'Y' ? 'Y' : 'N',
            'comment' => 'Y',
            'uploadedResponse' => 'N',
            'complete' => 'N',
            'viewableStudents' => 'N',
            'viewableParents' => 'N',
            'attachment' => '',
            'attainmentWeighting' => 1,
            'attainmentRaw' => 'N',
        ]);
        if ($enableEffort !== 'Y') {
            $data['effort'] = 'N';
            $data['gibbonScaleIDEffort'] = null;
        }
        if (($data['attainment'] ?? 'Y') === 'N') {
            $data['gibbonScaleIDAttainment'] = null;
        } elseif ($creating && empty($data['gibbonScaleIDAttainment'])) {
            throw new ApiException('gibbonScaleIDAttainment is required when attainment is Y. Use GET /v1/grade-scales.', 422);
        }
        if (($data['effort'] ?? 'N') === 'N') {
            $data['gibbonScaleIDEffort'] = null;
        }
        if (($data['complete'] ?? 'N') === 'N') {
            $data['completeDate'] = null;
        }
        $data = RestTable::emptyToNull($data, [
            'gibbonScaleIDAttainment', 'gibbonScaleIDEffort', 'gibbonUnitID',
            'gibbonPlannerEntryID', 'gibbonSchoolYearTermID', 'completeDate',
        ]);
        $data['gibbonRubricIDAttainment'] = null;
        $data['gibbonRubricIDEffort'] = null;

        return $data;
    }

    protected function entryPayload(array $column, array $row, bool $effortEnabled): array
    {
        $payload = [
            'modifiedAssessment' => null,
            'attainmentValueRaw' => null,
            'response' => $row['response'] ?? null,
        ];
        if (($column['attainment'] ?? 'Y') !== 'Y') {
            $payload['attainmentValue'] = null;
            $payload['attainmentDescriptor'] = null;
            $payload['attainmentConcern'] = null;
        } elseif (empty($column['gibbonScaleIDAttainment'])) {
            $payload['attainmentValue'] = $row['attainmentValue'] ?? '';
            $payload['attainmentDescriptor'] = '';
            $payload['attainmentConcern'] = '';
        } else {
            $value = $row['attainmentValue'] ?? '';
            $grade = $this->describeGrade($column['gibbonScaleIDAttainment'], $value);
            $payload['attainmentValue'] = $value;
            $payload['attainmentDescriptor'] = $grade['descriptor'];
            $payload['attainmentConcern'] = $grade['concern'];
        }

        if (!$effortEnabled || ($column['effort'] ?? 'N') !== 'Y') {
            $payload['effortValue'] = null;
            $payload['effortDescriptor'] = null;
            $payload['effortConcern'] = null;
        } elseif (empty($column['gibbonScaleIDEffort'])) {
            $payload['effortValue'] = $row['effortValue'] ?? '';
            $payload['effortDescriptor'] = '';
            $payload['effortConcern'] = '';
        } else {
            $value = $row['effortValue'] ?? '';
            $grade = $this->describeGrade($column['gibbonScaleIDEffort'], $value);
            $payload['effortValue'] = $value;
            $payload['effortDescriptor'] = $grade['descriptor'];
            $payload['effortConcern'] = $grade['concern'];
        }

        if (($column['comment'] ?? 'Y') !== 'Y') {
            $payload['comment'] = null;
        } else {
            $payload['comment'] = $row['comment'] ?? '';
        }

        return $payload;
    }

    protected function describeGrade($scaleId, $value): array
    {
        if ($value === null || $value === '') {
            return ['descriptor' => '', 'concern' => 'N'];
        }
        $row = $this->db->selectOne(
            "SELECT gibbonScaleGrade.descriptor, gibbonScaleGrade.sequenceNumber, gibbonScale.lowestAcceptable
             FROM gibbonScaleGrade
             JOIN gibbonScale ON (gibbonScale.gibbonScaleID=gibbonScaleGrade.gibbonScaleID)
             WHERE gibbonScaleGrade.gibbonScaleID=:id AND gibbonScaleGrade.value=:value",
            ['id' => $scaleId, 'value' => $value]
        );
        if (empty($row) || !isset($row['descriptor'])) {
            throw new ApiException("Unknown grade value '{$value}' for scale {$scaleId}.", 422);
        }
        $concern = 'N';
        if ($row['lowestAcceptable'] !== '' && $row['lowestAcceptable'] !== null && $row['sequenceNumber'] > $row['lowestAcceptable']) {
            $concern = 'Y';
        }

        return ['descriptor' => $row['descriptor'], 'concern' => $concern];
    }

    protected function classStudents(string $gibbonCourseClassID): array
    {
        return $this->db->select(
            "SELECT gibbonPerson.gibbonPersonID, gibbonPerson.surname, gibbonPerson.preferredName
             FROM gibbonCourseClassPerson
             JOIN gibbonPerson ON gibbonPerson.gibbonPersonID=gibbonCourseClassPerson.gibbonPersonID
             WHERE gibbonCourseClassPerson.gibbonCourseClassID=:id
             AND gibbonCourseClassPerson.role='Student'
             AND gibbonCourseClassPerson.role NOT LIKE '%Left'
             AND gibbonPerson.status='Full'
             ORDER BY surname, preferredName",
            ['id' => $gibbonCourseClassID]
        )->fetchAll();
    }
}
