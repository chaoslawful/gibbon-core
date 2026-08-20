<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

namespace Gibbon\Module\API\Services;

use Gibbon\Contracts\Database\Connection;
use Gibbon\Domain\Timetable\TimetableDayGateway;
use Gibbon\Module\API\Auth\PermissionMapper;
use Gibbon\Module\API\Http\ApiException;

class TimetableSlotService
{
    protected TimetableDayGateway $dayGateway;
    protected PermissionMapper $permissions;
    protected Connection $db;

    public function __construct(
        TimetableDayGateway $dayGateway,
        PermissionMapper $permissions,
        Connection $db
    ) {
        $this->dayGateway = $dayGateway;
        $this->permissions = $permissions;
        $this->db = $db;
    }

    public function listByDay(string $gibbonTTID, string $gibbonTTDayID): array
    {
        $this->permissions->assertCanViewTimetable();
        $this->requireDay($gibbonTTID, $gibbonTTDayID);

        return $this->dayGateway->selectTTDayRowClassesByID($gibbonTTDayID)->fetchAll();
    }

    public function create(string $gibbonTTID, string $gibbonTTDayID, array $body): array
    {
        $this->permissions->assertCanManageTimetables();
        $this->requireDay($gibbonTTID, $gibbonTTDayID);

        $columnRowID = $body['gibbonTTColumnRowID'] ?? '';
        $classID = $body['gibbonCourseClassID'] ?? '';
        if ($columnRowID === '' || $classID === '') {
            throw new ApiException('gibbonTTColumnRowID and gibbonCourseClassID are required.', 422);
        }

        $row = $this->dayGateway->getTTDayRowByID($gibbonTTDayID, $columnRowID);
        if (empty($row)) {
            throw new ApiException('The specified period does not belong to this timetable day.', 422);
        }

        $id = $this->dayGateway->insertDayRowClass([
            'gibbonTTDayID' => $gibbonTTDayID,
            'gibbonTTColumnRowID' => $columnRowID,
            'gibbonCourseClassID' => $classID,
            'gibbonSpaceID' => !empty($body['gibbonSpaceID']) ? $body['gibbonSpaceID'] : null,
        ]);

        if (empty($id)) {
            throw new ApiException('Unable to create timetable slot.', 500);
        }

        return $this->getSlot((string) $id);
    }

    public function update(string $id, array $body): array
    {
        $this->permissions->assertCanManageTimetables();
        $slot = $this->getSlot($id);

        $data = [
            'gibbonTTDayID' => $body['gibbonTTDayID'] ?? $slot['gibbonTTDayID'],
            'gibbonTTColumnRowID' => $body['gibbonTTColumnRowID'] ?? $slot['gibbonTTColumnRowID'],
            'gibbonCourseClassID' => $body['gibbonCourseClassID'] ?? $slot['gibbonCourseClassID'],
            'gibbonSpaceID' => array_key_exists('gibbonSpaceID', $body)
                ? (!empty($body['gibbonSpaceID']) ? $body['gibbonSpaceID'] : null)
                : $slot['gibbonSpaceID'],
        ];

        $this->dayGateway->updateDayRowClass($id, $data);

        return $this->getSlot($id);
    }

    public function delete(string $id): void
    {
        $this->permissions->assertCanManageTimetables();
        $slot = $this->getSlot($id);

        $sql = 'DELETE FROM gibbonTTDayRowClass WHERE gibbonTTDayRowClassID=:id';
        $this->db->delete($sql, ['id' => $id]);
    }

    public function getSlot(string $id): array
    {
        $sql = "SELECT gibbonTTDayRowClass.*, gibbonCourse.nameShort AS courseName,
                       gibbonCourseClass.nameShort AS className, gibbonSpace.name AS location,
                       gibbonTTDay.gibbonTTID, gibbonTT.gibbonSchoolYearID
                FROM gibbonTTDayRowClass
                JOIN gibbonTTDay ON (gibbonTTDay.gibbonTTDayID=gibbonTTDayRowClass.gibbonTTDayID)
                JOIN gibbonTT ON (gibbonTT.gibbonTTID=gibbonTTDay.gibbonTTID)
                JOIN gibbonCourseClass ON (gibbonCourseClass.gibbonCourseClassID=gibbonTTDayRowClass.gibbonCourseClassID)
                JOIN gibbonCourse ON (gibbonCourse.gibbonCourseID=gibbonCourseClass.gibbonCourseID)
                LEFT JOIN gibbonSpace ON (gibbonSpace.gibbonSpaceID=gibbonTTDayRowClass.gibbonSpaceID)
                WHERE gibbonTTDayRowClass.gibbonTTDayRowClassID=:id";

        $row = $this->db->selectOne($sql, ['id' => $id]);
        if (empty($row)) {
            throw new ApiException('Timetable slot not found.', 404);
        }

        return $row;
    }

    protected function requireDay(string $gibbonTTID, string $gibbonTTDayID): array
    {
        $day = $this->dayGateway->getTTDayByID($gibbonTTDayID);
        if (empty($day) || (string) $day['gibbonTTID'] !== ltrim($gibbonTTID, '0') && (string) $day['gibbonTTID'] !== $gibbonTTID) {
            if (empty($day) || intval($day['gibbonTTID']) !== intval($gibbonTTID)) {
                throw new ApiException('Timetable day not found.', 404);
            }
        }

        return $day;
    }
}
