<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

namespace Gibbon\Module\API\Services;

use Gibbon\Contracts\Services\Session;
use Gibbon\Domain\Planner\PlannerEntryGateway;
use Gibbon\Domain\Planner\PlannerEntryHomeworkGateway;
use Gibbon\Module\API\Auth\PermissionMapper;
use Gibbon\Module\API\Http\ApiException;
use Gibbon\Module\API\Support\RestTable;

class HomeworkSubmissionService
{
    public function __construct(
        protected PermissionMapper $permissions,
        protected PlannerEntryGateway $entries,
        protected PlannerEntryHomeworkGateway $homework,
        protected Session $session
    ) {
    }

    public function list(string $lessonId): array
    {
        $entry = RestTable::requireRow($this->entries, $lessonId, 'Lesson plan not found.');
        $this->permissions->assertClassReadable($entry['gibbonCourseClassID']);
        return $this->homework->selectBy(['gibbonPlannerEntryID' => $lessonId])->fetchAll();
    }

    public function create(string $lessonId, array $body): array
    {
        $entry = RestTable::requireRow($this->entries, $lessonId, 'Lesson plan not found.');
        $this->permissions->assertClassWritable($entry['gibbonCourseClassID']);
        $data = RestTable::pick($body, ['gibbonPersonID', 'type', 'version', 'status', 'location', 'count', 'timestamp']);
        RestTable::requireFields($data, ['gibbonPersonID']);
        $data['gibbonPlannerEntryID'] = $lessonId;
        $data['type'] = $data['type'] ?? 'File';
        $data['version'] = $data['version'] ?? 'Final';
        $data['status'] = $data['status'] ?? 'On Time';
        $data['timestamp'] = $data['timestamp'] ?? date('Y-m-d H:i:s');
        $data['count'] = $data['count'] ?? 1;
        return RestTable::create($this->homework, $data);
    }

    public function update(string $id, array $body): array
    {
        $row = RestTable::requireRow($this->homework, $id, 'Homework submission not found.');
        $entry = RestTable::requireRow($this->entries, $row['gibbonPlannerEntryID'], 'Lesson plan not found.');
        $this->permissions->assertClassWritable($entry['gibbonCourseClassID']);
        $data = RestTable::pick($body, ['type', 'version', 'status', 'location', 'count']);
        return RestTable::update($this->homework, $id, $data, 'Homework submission not found.');
    }

    public function delete(string $id): void
    {
        $row = RestTable::requireRow($this->homework, $id, 'Homework submission not found.');
        $entry = RestTable::requireRow($this->entries, $row['gibbonPlannerEntryID'], 'Lesson plan not found.');
        $this->permissions->assertClassWritable($entry['gibbonCourseClassID']);
        $this->homework->delete($id);
    }
}
