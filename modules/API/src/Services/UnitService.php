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
use Gibbon\Domain\Planner\UnitBlockGateway;
use Gibbon\Domain\Planner\UnitClassBlockGateway;
use Gibbon\Domain\Planner\UnitGateway;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\API\Auth\PermissionMapper;
use Gibbon\Module\API\Http\ApiException;
use Gibbon\Module\API\Support\RestTable;

class UnitService
{
    public function __construct(
        protected PermissionMapper $permissions,
        protected UnitGateway $units,
        protected UnitBlockGateway $blocks,
        protected UnitClassBlockGateway $classBlocks,
        protected PlannerEntryGateway $entries,
        protected SettingGateway $settings,
        protected Session $session,
        protected Connection $db
    ) {
    }

    public function get(string $id): array
    {
        $this->permissions->assertCanManageUnits();
        $unit = RestTable::requireRow($this->units, $id, 'Unit not found.');
        $unit['blocks'] = $this->blocks->selectBlocksByUnit($id)->fetchAll();
        $unit['classes'] = $this->db->select(
            'SELECT * FROM gibbonUnitClass WHERE gibbonUnitID=:id',
            ['id' => $id]
        )->fetchAll();
        return $unit;
    }

    public function create(array $body): array
    {
        $this->permissions->assertCanManageUnits();
        $data = RestTable::pick($body, [
            'gibbonCourseID', 'name', 'description', 'active', 'ordering', 'details',
            'license', 'sharedPublic', 'map', 'tags',
        ]);
        RestTable::requireFields($data, ['gibbonCourseID', 'name']);
        $personID = $this->session->get('gibbonPersonID');
        $data = RestTable::defaults($data, [
            'active' => 'Y',
            'ordering' => 0,
            'map' => 'Y',
            'description' => '',
            'tags' => '',
            'details' => '',
            'attachment' => '',
        ]);
        $data['gibbonPersonIDCreator'] = $personID;
        $data['gibbonPersonIDLastEdit'] = $personID;
        $unit = RestTable::create($this->units, $data);
        return $this->get($unit['gibbonUnitID']);
    }

    public function update(string $id, array $body): array
    {
        $this->permissions->assertCanManageUnits();
        $data = RestTable::pick($body, [
            'gibbonCourseID', 'name', 'description', 'active', 'ordering', 'details',
            'license', 'sharedPublic', 'map', 'tags',
        ]);
        $data['gibbonPersonIDLastEdit'] = $this->session->get('gibbonPersonID');
        RestTable::update($this->units, $id, $data, 'Unit not found.');
        return $this->get($id);
    }

    public function delete(string $id): void
    {
        $this->permissions->assertCanManageUnits();
        RestTable::requireRow($this->units, $id, 'Unit not found.');
        $this->db->delete('DELETE FROM gibbonUnitBlock WHERE gibbonUnitID=:id', ['id' => $id]);
        $this->db->delete('DELETE FROM gibbonUnitClass WHERE gibbonUnitID=:id', ['id' => $id]);
        $this->units->delete($id);
    }

    public function createBlock(string $unitId, array $body): array
    {
        $this->permissions->assertCanManageUnits();
        RestTable::requireRow($this->units, $unitId, 'Unit not found.');
        $data = RestTable::pick($body, ['title', 'type', 'length', 'contents', 'teachersNotes', 'sequenceNumber']);
        RestTable::requireFields($data, ['title']);
        $data['gibbonUnitID'] = $unitId;
        $data = RestTable::defaults($data, [
            'sequenceNumber' => 1,
            'type' => '',
            'length' => '',
            'contents' => '',
            'teachersNotes' => '',
        ]);
        return RestTable::create($this->blocks, $data);
    }

    public function updateBlock(string $id, array $body): array
    {
        $this->permissions->assertCanManageUnits();
        $data = RestTable::pick($body, ['title', 'type', 'length', 'contents', 'teachersNotes', 'sequenceNumber']);
        return RestTable::update($this->blocks, $id, $data, 'Unit block not found.');
    }

    public function deleteBlock(string $id): void
    {
        $this->permissions->assertCanManageUnits();
        RestTable::delete($this->blocks, $id, 'Unit block not found.');
    }

    public function attachClass(string $unitId, array $body): array
    {
        $this->permissions->assertCanManageUnits();
        RestTable::requireRow($this->units, $unitId, 'Unit not found.');
        $classId = $body['gibbonCourseClassID'] ?? '';
        if ($classId === '') {
            throw new ApiException('gibbonCourseClassID is required.', 422);
        }
        $running = ($body['running'] ?? 'N') === 'Y' ? 'Y' : 'N';
        $existing = $this->db->selectOne(
            'SELECT * FROM gibbonUnitClass WHERE gibbonUnitID=:unit AND gibbonCourseClassID=:class',
            ['unit' => $unitId, 'class' => $classId]
        );
        if (!empty($existing)) {
            if ($running === 'Y' && ($existing['running'] ?? 'N') !== 'Y') {
                $this->db->update(
                    'UPDATE gibbonUnitClass SET running=:running WHERE gibbonUnitClassID=:id',
                    ['running' => $running, 'id' => $existing['gibbonUnitClassID']]
                );
                $existing['running'] = $running;
            }
            return $existing;
        }
        $id = $this->db->insert(
            'INSERT INTO gibbonUnitClass SET gibbonUnitID=:unit, gibbonCourseClassID=:class, running=:running',
            ['unit' => $unitId, 'class' => $classId, 'running' => $running]
        );
        return $this->db->selectOne('SELECT * FROM gibbonUnitClass WHERE gibbonUnitClassID=:id', ['id' => $id]);
    }

    public function deploy(string $unitId, array $body): array
    {
        $this->permissions->assertCanManageUnits();
        $unit = RestTable::requireRow($this->units, $unitId, 'Unit not found.');
        $classId = $body['gibbonCourseClassID'] ?? '';
        $lessons = $body['lessons'] ?? [];
        if ($classId === '' || !is_array($lessons) || $lessons === []) {
            throw new ApiException('gibbonCourseClassID and lessons[] are required.', 422);
        }

        $unitClass = $this->attachClass($unitId, [
            'gibbonCourseClassID' => $classId,
            'running' => $body['running'] ?? 'Y',
        ]);
        $viewableStudents = ($body['viewableStudents'] ?? 'N') === 'Y' ? 'Y' : 'N';
        $viewableParents = ($body['viewableParents'] ?? 'N') === 'Y' ? 'Y' : 'N';
        $lessonNameReplace = ($body['lessonNameReplace'] ?? 'N') === 'Y';
        $teachersNotes = $this->settings->getSettingByScope('Planner', 'teachersNotesTemplate') ?? '';
        $personID = $this->session->get('gibbonPersonID');
        $created = [];
        $sequenceNumber = 0;
        $lessonCount = 0;

        foreach ($lessons as $index => $lesson) {
            if (!is_array($lesson)) {
                throw new ApiException('Each lessons[] item must be an object.', 422);
            }
            $date = $lesson['date'] ?? '';
            $timeStart = $this->normalizeTime($lesson['timeStart'] ?? '');
            $timeEnd = $this->normalizeTime($lesson['timeEnd'] ?? '');
            if ($date === '' || $timeStart === '' || $timeEnd === '') {
                throw new ApiException('lessons['.$index.'] needs date, timeStart and timeEnd.', 422);
            }
            $lessonCount++;
            $name = trim($lesson['name'] ?? '');
            if ($name === '') {
                $name = $unit['name'].' '.$lessonCount;
            }
            $entryId = $this->entries->insert([
                'gibbonCourseClassID' => $classId,
                'date' => $date,
                'timeStart' => $timeStart,
                'timeEnd' => $timeEnd,
                'gibbonUnitID' => $unitId,
                'name' => mb_substr($name, 0, 50),
                'summary' => 'Part of the '.$unit['name'].' unit.',
                'description' => '',
                'teachersNotes' => $teachersNotes,
                'homework' => 'N',
                'viewableParents' => $viewableParents,
                'viewableStudents' => $viewableStudents,
                'gibbonPersonIDCreator' => $personID,
                'gibbonPersonIDLastEdit' => $personID,
            ]);
            if (empty($entryId)) {
                throw new ApiException('Unable to create lesson during deploy.', 500);
            }

            $titles = [];
            $firstTitle = '';
            $blocks = $lesson['blocks'] ?? [];
            if (!is_array($blocks)) {
                throw new ApiException('lessons['.$index.'].blocks must be an array.', 422);
            }
            foreach ($blocks as $blockInput) {
                $blockId = is_array($blockInput) ? ($blockInput['gibbonUnitBlockID'] ?? '') : $blockInput;
                $source = $this->blocks->getByID($blockId);
                if (empty($source)) {
                    throw new ApiException('Unit block not found: '.$blockId, 404);
                }
                $title = is_array($blockInput) ? ($blockInput['title'] ?? $source['title']) : $source['title'];
                $this->classBlocks->insert([
                    'gibbonUnitClassID' => $unitClass['gibbonUnitClassID'],
                    'gibbonPlannerEntryID' => $entryId,
                    'gibbonUnitBlockID' => $blockId,
                    'title' => $title,
                    'type' => is_array($blockInput) ? ($blockInput['type'] ?? $source['type']) : $source['type'],
                    'length' => is_array($blockInput) ? ($blockInput['length'] ?? $source['length']) : $source['length'],
                    'contents' => is_array($blockInput) ? ($blockInput['contents'] ?? $source['contents']) : $source['contents'],
                    'teachersNotes' => is_array($blockInput) ? ($blockInput['teachersNotes'] ?? ($source['teachersNotes'] ?? '')) : ($source['teachersNotes'] ?? ''),
                    'sequenceNumber' => $sequenceNumber,
                    'complete' => 'N',
                ]);
                $titles[] = $title;
                if ($firstTitle === '') {
                    $firstTitle = $title;
                }
                $sequenceNumber++;
            }

            $summary = implode(', ', $titles);
            if (strlen($summary) > 75) {
                $summary = substr($summary, 0, 72).'...';
            }
            $update = [];
            if ($summary !== '') {
                $update['summary'] = $summary;
            }
            if ($lessonNameReplace && $firstTitle !== '') {
                $update['name'] = mb_substr($firstTitle, 0, 50);
            }
            if (!empty($update)) {
                $this->entries->update($entryId, $update);
            }
            $created[] = $this->entries->getByID($entryId);
        }

        return ['unitClass' => $unitClass, 'lessons' => $created];
    }

    public function copyForward(string $unitId, array $body): array
    {
        $this->permissions->assertCanManageUnits();
        $unit = RestTable::requireRow($this->units, $unitId, 'Unit not found.');
        $courseId = $body['gibbonCourseID'] ?? '';
        if ($courseId === '') {
            throw new ApiException('gibbonCourseID is required (target course).', 422);
        }
        unset($unit['gibbonUnitID']);
        $unit['gibbonCourseID'] = $courseId;
        $unit['gibbonPersonIDCreator'] = $this->session->get('gibbonPersonID');
        $unit['gibbonPersonIDLastEdit'] = $this->session->get('gibbonPersonID');
        $copy = RestTable::create($this->units, $unit);
        foreach ($this->blocks->selectBlocksByUnit($unitId)->fetchAll() as $block) {
            unset($block['gibbonUnitBlockID']);
            $block['gibbonUnitID'] = $copy['gibbonUnitID'];
            $this->blocks->insert($block);
        }
        return $this->get($copy['gibbonUnitID']);
    }

    public function copyBack(string $unitClassId): array
    {
        $this->permissions->assertCanManageUnits();
        $unitClass = $this->db->selectOne('SELECT * FROM gibbonUnitClass WHERE gibbonUnitClassID=:id', ['id' => $unitClassId]);
        if (empty($unitClass)) {
            throw new ApiException('Unit class working copy not found.', 404);
        }
        $this->db->delete('DELETE FROM gibbonUnitBlock WHERE gibbonUnitID=:id', ['id' => $unitClass['gibbonUnitID']]);
        $working = $this->db->select(
            'SELECT * FROM gibbonUnitClassBlock WHERE gibbonUnitClassID=:id ORDER BY sequenceNumber',
            ['id' => $unitClassId]
        )->fetchAll();
        foreach ($working as $block) {
            $this->blocks->insert([
                'gibbonUnitID' => $unitClass['gibbonUnitID'],
                'title' => $block['title'],
                'type' => $block['type'],
                'length' => $block['length'],
                'contents' => $block['contents'],
                'teachersNotes' => $block['teachersNotes'] ?? '',
                'sequenceNumber' => $block['sequenceNumber'],
            ]);
        }
        return $this->get($unitClass['gibbonUnitID']);
    }

    public function smartBlockify(string $unitId, string $lessonId): array
    {
        $this->permissions->assertCanManageUnits();
        RestTable::requireRow($this->units, $unitId, 'Unit not found.');
        $lessonBlocks = $this->db->select(
            'SELECT * FROM gibbonUnitClassBlock WHERE gibbonPlannerEntryID=:id ORDER BY sequenceNumber',
            ['id' => $lessonId]
        )->fetchAll();
        foreach ($lessonBlocks as $block) {
            $this->blocks->insert([
                'gibbonUnitID' => $unitId,
                'title' => $block['title'],
                'type' => $block['type'],
                'length' => $block['length'],
                'contents' => $block['contents'],
                'teachersNotes' => $block['teachersNotes'] ?? '',
                'sequenceNumber' => $block['sequenceNumber'],
            ]);
        }
        return $this->get($unitId);
    }

    protected function normalizeTime(string $time): string
    {
        if (preg_match('/^\d{2}:\d{2}$/', $time)) {
            return $time.':00';
        }
        return $time;
    }
}
