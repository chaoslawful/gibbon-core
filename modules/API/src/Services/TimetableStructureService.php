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
use Gibbon\Domain\Timetable\TimetableColumnGateway;
use Gibbon\Domain\Timetable\TimetableDayDateGateway;
use Gibbon\Domain\Timetable\TimetableDayGateway;
use Gibbon\Domain\Timetable\TimetableGateway;
use Gibbon\Module\API\Auth\PermissionMapper;
use Gibbon\Module\API\Http\ApiException;
use Gibbon\Module\API\Support\RestTable;

class TimetableStructureService
{
    public function __construct(
        protected PermissionMapper $permissions,
        protected TimetableGateway $timetables,
        protected TimetableDayGateway $days,
        protected TimetableColumnGateway $columns,
        protected TimetableDayDateGateway $dates,
        protected Session $session,
        protected Connection $db
    ) {
    }

    public function createTimetable(array $body): array
    {
        $this->permissions->assertCanManageTimetables();
        $data = RestTable::pick($body, ['gibbonSchoolYearID', 'name', 'nameShort', 'nameShortDisplay', 'active', 'gibbonYearGroupIDList']);
        if (empty($data['gibbonSchoolYearID'])) {
            $data['gibbonSchoolYearID'] = $this->session->get('gibbonSchoolYearID');
        }
        RestTable::requireFields($data, ['name', 'nameShort']);
        $data['active'] = $data['active'] ?? 'Y';
        $data['nameShortDisplay'] = $data['nameShortDisplay'] ?? 'Day Of The Week';
        $data['gibbonYearGroupIDList'] = $data['gibbonYearGroupIDList'] ?? '';
        return RestTable::create($this->timetables, $data);
    }

    public function updateTimetable(string $id, array $body): array
    {
        $this->permissions->assertCanManageTimetables();
        $data = RestTable::pick($body, ['gibbonSchoolYearID', 'name', 'nameShort', 'nameShortDisplay', 'active', 'gibbonYearGroupIDList']);
        return RestTable::update($this->timetables, $id, $data, 'Timetable not found.');
    }

    public function deleteTimetable(string $id): void
    {
        $this->permissions->assertCanManageTimetables();
        RestTable::delete($this->timetables, $id, 'Timetable not found.');
    }

    public function listColumns(): array
    {
        $this->permissions->assertCanViewTimetable();
        return $this->columns->selectTTColumns()->fetchAll();
    }

    public function getColumn(string $id): array
    {
        $this->permissions->assertCanViewTimetable();
        $column = $this->columns->getTTColumnByID($id);
        if (empty($column)) {
            throw new ApiException('Period template not found.', 404);
        }
        $column['rows'] = $this->columns->selectTTColumnRowsByID($id)->fetchAll();
        return $column;
    }

    public function createColumn(array $body): array
    {
        $this->permissions->assertCanManageTimetables();
        $data = RestTable::pick($body, ['name', 'nameShort']);
        RestTable::requireFields($data, ['name', 'nameShort']);
        $row = RestTable::create($this->columns, $data);
        return $this->getColumn($row['gibbonTTColumnID']);
    }

    public function updateColumn(string $id, array $body): array
    {
        $this->permissions->assertCanManageTimetables();
        $data = RestTable::pick($body, ['name', 'nameShort']);
        RestTable::update($this->columns, $id, $data, 'Period template not found.');
        return $this->getColumn($id);
    }

    public function deleteColumn(string $id): void
    {
        $this->permissions->assertCanManageTimetables();
        RestTable::requireRow($this->columns, $id, 'Period template not found.');
        $this->db->delete('DELETE FROM gibbonTTColumnRow WHERE gibbonTTColumnID=:id', ['id' => $id]);
        $this->columns->delete($id);
    }

    public function createColumnRow(string $columnId, array $body): array
    {
        $this->permissions->assertCanManageTimetables();
        RestTable::requireRow($this->columns, $columnId, 'Period template not found.');
        $data = RestTable::pick($body, ['name', 'nameShort', 'timeStart', 'timeEnd', 'type']);
        RestTable::requireFields($data, ['name', 'nameShort', 'timeStart', 'timeEnd']);
        $data['gibbonTTColumnID'] = $columnId;
        $data['type'] = $data['type'] ?? 'Lesson';
        $id = $this->columns->insertColumnRow($data);
        if (empty($id)) {
            throw new ApiException('Unable to create period.', 500);
        }
        return $this->db->selectOne('SELECT * FROM gibbonTTColumnRow WHERE gibbonTTColumnRowID=:id', ['id' => $id]);
    }

    public function updateColumnRow(string $rowId, array $body): array
    {
        $this->permissions->assertCanManageTimetables();
        $row = $this->db->selectOne('SELECT * FROM gibbonTTColumnRow WHERE gibbonTTColumnRowID=:id', ['id' => $rowId]);
        if (empty($row)) {
            throw new ApiException('Period not found.', 404);
        }
        $data = RestTable::pick($body, ['name', 'nameShort', 'timeStart', 'timeEnd', 'type']);
        if (!empty($data)) {
            $sets = [];
            $params = ['id' => $rowId];
            foreach ($data as $key => $value) {
                $sets[] = "$key=:$key";
                $params[$key] = $value;
            }
            $this->db->update('UPDATE gibbonTTColumnRow SET '.implode(', ', $sets).' WHERE gibbonTTColumnRowID=:id', $params);
        }
        return $this->db->selectOne('SELECT * FROM gibbonTTColumnRow WHERE gibbonTTColumnRowID=:id', ['id' => $rowId]);
    }

    public function deleteColumnRow(string $rowId): void
    {
        $this->permissions->assertCanManageTimetables();
        $row = $this->db->selectOne('SELECT * FROM gibbonTTColumnRow WHERE gibbonTTColumnRowID=:id', ['id' => $rowId]);
        if (empty($row)) {
            throw new ApiException('Period not found.', 404);
        }
        $this->db->delete('DELETE FROM gibbonTTColumnRow WHERE gibbonTTColumnRowID=:id', ['id' => $rowId]);
    }

    public function createDay(string $ttId, array $body): array
    {
        $this->permissions->assertCanManageTimetables();
        RestTable::requireRow($this->timetables, $ttId, 'Timetable not found.');
        $data = RestTable::pick($body, ['name', 'nameShort', 'color', 'fontColor', 'gibbonTTColumnID']);
        RestTable::requireFields($data, ['name', 'nameShort', 'gibbonTTColumnID']);
        $data['gibbonTTID'] = $ttId;
        $data['color'] = $data['color'] ?? '#ffffff';
        $data['fontColor'] = $data['fontColor'] ?? '#000000';
        return RestTable::create($this->days, $data);
    }

    public function updateDay(string $ttId, string $dayId, array $body): array
    {
        $this->permissions->assertCanManageTimetables();
        $day = RestTable::requireRow($this->days, $dayId, 'Timetable day not found.');
        if (intval($day['gibbonTTID']) !== intval($ttId)) {
            throw new ApiException('Timetable day not found.', 404);
        }
        $data = RestTable::pick($body, ['name', 'nameShort', 'color', 'fontColor', 'gibbonTTColumnID']);
        return RestTable::update($this->days, $dayId, $data, 'Timetable day not found.');
    }

    public function deleteDay(string $ttId, string $dayId): void
    {
        $this->permissions->assertCanManageTimetables();
        $day = RestTable::requireRow($this->days, $dayId, 'Timetable day not found.');
        if (intval($day['gibbonTTID']) !== intval($ttId)) {
            throw new ApiException('Timetable day not found.', 404);
        }
        $this->days->delete($dayId);
    }

    public function createDate(string $ttId, array $body): array
    {
        $this->permissions->assertCanManageTimetables();
        RestTable::requireRow($this->timetables, $ttId, 'Timetable not found.');
        $dayId = $body['gibbonTTDayID'] ?? '';
        $date = $body['date'] ?? '';
        if ($dayId === '' || $date === '') {
            throw new ApiException('gibbonTTDayID and date are required.', 422);
        }
        $day = RestTable::requireRow($this->days, $dayId, 'Timetable day not found.');
        if (intval($day['gibbonTTID']) !== intval($ttId)) {
            throw new ApiException('Timetable day not found.', 404);
        }
        $id = $this->dates->insert(['gibbonTTDayID' => $dayId, 'date' => $date]);
        if (empty($id)) {
            throw new ApiException('Unable to map date. It may already be assigned.', 422);
        }
        return $this->dates->getByID($id);
    }

    public function deleteDate(string $id): void
    {
        $this->permissions->assertCanManageTimetables();
        RestTable::delete($this->dates, $id, 'Timetable date mapping not found.');
    }

    public function listExceptions(string $slotId): array
    {
        $this->permissions->assertCanViewTimetable();
        return $this->db->select(
            'SELECT * FROM gibbonTTDayRowClassException WHERE gibbonTTDayRowClassID=:id',
            ['id' => $slotId]
        )->fetchAll();
    }

    public function createException(string $slotId, array $body): array
    {
        $this->permissions->assertCanManageTimetables();
        $personID = $body['gibbonPersonID'] ?? '';
        if ($personID === '') {
            throw new ApiException('gibbonPersonID is required.', 422);
        }
        $slot = $this->db->selectOne('SELECT gibbonTTDayRowClassID FROM gibbonTTDayRowClass WHERE gibbonTTDayRowClassID=:id', ['id' => $slotId]);
        if (empty($slot)) {
            throw new ApiException('Timetable slot not found.', 404);
        }
        $id = $this->db->insert(
            'INSERT INTO gibbonTTDayRowClassException SET gibbonTTDayRowClassID=:slot, gibbonPersonID=:person',
            ['slot' => $slotId, 'person' => $personID]
        );
        return $this->db->selectOne('SELECT * FROM gibbonTTDayRowClassException WHERE gibbonTTDayRowClassExceptionID=:id', ['id' => $id]);
    }

    public function deleteException(string $id): void
    {
        $this->permissions->assertCanManageTimetables();
        $row = $this->db->selectOne('SELECT gibbonTTDayRowClassExceptionID FROM gibbonTTDayRowClassException WHERE gibbonTTDayRowClassExceptionID=:id', ['id' => $id]);
        if (empty($row)) {
            throw new ApiException('Exception not found.', 404);
        }
        $this->db->delete('DELETE FROM gibbonTTDayRowClassException WHERE gibbonTTDayRowClassExceptionID=:id', ['id' => $id]);
    }
}
