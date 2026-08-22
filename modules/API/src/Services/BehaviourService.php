<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

namespace Gibbon\Module\API\Services;

use Gibbon\Contracts\Services\Session;
use Gibbon\Domain\Behaviour\BehaviourFollowUpGateway;
use Gibbon\Domain\Behaviour\BehaviourGateway;
use Gibbon\Domain\Students\StudentNoteGateway;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\API\Auth\PermissionMapper;
use Gibbon\Module\API\Http\ApiException;
use Gibbon\Module\API\Support\RestTable;
use Gibbon\UI\Components\Alert;

class BehaviourService
{
    public function __construct(
        protected PermissionMapper $permissions,
        protected Session $session,
        protected SettingGateway $settings,
        protected BehaviourGateway $records,
        protected BehaviourFollowUpGateway $followUps,
        protected StudentNoteGateway $notes,
        protected Alert $alerts
    ) {
    }

    public function list(array $query): array
    {
        $this->permissions->assertCanManageBehaviour();
        $yearId = $query['gibbonSchoolYearID'] ?? $this->session->get('gibbonSchoolYearID');
        $creator = $this->permissions->canManageAllBehaviour() ? null : $this->session->get('gibbonPersonID');
        $criteria = $this->records->newQueryCriteria()
            ->sortBy('date', 'DESC')
            ->pageSize((int) ($query['limit'] ?? 50));
        if (!empty($query['type'])) {
            $criteria->filterBy('type', $query['type']);
        }

        return $this->records->queryBehaviourBySchoolYear($criteria, $yearId, $creator)->toArray();
    }

    public function get(string $id): array
    {
        $this->permissions->assertCanManageBehaviour();
        $row = RestTable::requireRow($this->records, $id, 'Behaviour record not found.');
        $this->assertCanTouch($row);
        $row['followUps'] = $this->followUps->selectBy(['gibbonBehaviourID' => $id])->fetchAll();

        return $row;
    }

    public function create(array $body): array
    {
        $this->permissions->assertCanManageBehaviour();
        $ids = $body['gibbonPersonIDs'] ?? null;
        if (is_array($ids) && $ids !== []) {
            $created = [];
            $incident = hash('sha256', getSalt());
            foreach ($ids as $personId) {
                $one = $body;
                $one['gibbonPersonID'] = $personId;
                unset($one['gibbonPersonIDs']);
                $row = $this->createOne($one, $incident);
                $created[] = $row;
            }

            return ['gibbonMultiIncidentID' => $incident, 'data' => $created];
        }

        return $this->createOne($body, null);
    }

    public function update(string $id, array $body): array
    {
        $this->permissions->assertCanManageBehaviour();
        $existing = $this->get($id);
        $data = RestTable::pick($body, ['date', 'type', 'descriptor', 'level', 'comment', 'gibbonPlannerEntryID', 'fields']);
        if (isset($data['type'])) {
            $this->assertType($data['type']);
        }
        $enableDescriptors = $this->settings->getSettingByScope('Behaviour', 'enableDescriptors');
        $mergedType = $data['type'] ?? $existing['type'];
        $mergedDescriptor = array_key_exists('descriptor', $data) ? $data['descriptor'] : $existing['descriptor'];
        if ($enableDescriptors === 'Y' && ($mergedDescriptor === '' || $mergedDescriptor === null) && $mergedType !== '') {
            throw new ApiException('descriptor is required when behaviour descriptors are enabled.', 422);
        }
        $data = RestTable::emptyToNull($data, ['level', 'gibbonPlannerEntryID']);
        $this->records->update($id, $data);
        $this->alerts->recalculateAlerts($existing['gibbonPersonID']);
        if (!empty($body['followUp'])) {
            $this->addFollowUp($id, (string) $body['followUp']);
        }

        return $this->get($id);
    }

    public function delete(string $id): void
    {
        $this->permissions->assertCanManageBehaviour();
        $row = RestTable::requireRow($this->records, $id, 'Behaviour record not found.');
        $this->assertCanTouch($row);
        $this->followUps->deleteWhere(['gibbonBehaviourID' => $id]);
        RestTable::delete($this->records, $id, 'Behaviour record not found.');
        $this->alerts->recalculateAlerts($row['gibbonPersonID']);
    }

    public function addFollowUp(string $id, string $followUp): array
    {
        $this->permissions->assertCanManageBehaviour();
        $row = RestTable::requireRow($this->records, $id, 'Behaviour record not found.');
        $this->assertCanTouch($row);
        if (trim($followUp) === '') {
            throw new ApiException('followUp is required.', 422);
        }
        $this->followUps->insert([
            'gibbonBehaviourID' => $id,
            'gibbonPersonID' => $this->session->get('gibbonPersonID'),
            'followUp' => $followUp,
        ]);

        return $this->get($id);
    }

    protected function createOne(array $body, ?string $incidentId): array
    {
        $data = RestTable::pick($body, ['gibbonPersonID', 'date', 'type', 'descriptor', 'level', 'comment', 'fields']);
        RestTable::requireFields($data, ['gibbonPersonID', 'date', 'type']);
        $this->assertType($data['type']);
        if ($this->settings->getSettingByScope('Behaviour', 'enableDescriptors') === 'Y') {
            RestTable::requireFields($data, ['descriptor']);
        }
        $data = RestTable::defaults($data, [
            'comment' => '',
            'fields' => '{}',
            'gibbonPersonIDCreator' => $this->session->get('gibbonPersonID'),
            'gibbonSchoolYearID' => $this->session->get('gibbonSchoolYearID'),
        ]);
        $data = RestTable::emptyToNull($data, ['level', 'descriptor']);
        if ($incidentId) {
            $data['gibbonMultiIncidentID'] = $incidentId;
        }
        $row = RestTable::create($this->records, $data);
        $this->alerts->recalculateAlerts($data['gibbonPersonID']);
        if (!empty($body['followUp'])) {
            $this->followUps->insert([
                'gibbonBehaviourID' => $row['gibbonBehaviourID'],
                'gibbonPersonID' => $this->session->get('gibbonPersonID'),
                'followUp' => (string) $body['followUp'],
            ]);
        }
        $copy = $body['copyToNotes'] ?? '';
        if ($copy === 'Y' || $copy === 'on' || $copy === true) {
            $this->copyToNotes($data['gibbonPersonID'], $data['descriptor'] ?? '', $data['comment'] ?? '', (string) ($body['followUp'] ?? ''));
        }

        return $this->get($row['gibbonBehaviourID']);
    }

    protected function copyToNotes(string $personId, string $descriptor, string $comment, string $followUp): void
    {
        $note = $comment;
        if ($followUp !== '') {
            $note .= ' <br/><br/>'.$followUp;
        }
        $this->notes->insert([
            'title' => 'Behaviour'.($descriptor !== '' ? ': '.$descriptor : ''),
            'note' => $note,
            'gibbonPersonID' => $personId,
            'gibbonPersonIDCreator' => $this->session->get('gibbonPersonID'),
            'gibbonStudentNoteCategoryID' => $this->notes->getNoteCategoryIDByName('Behaviour') ?? null,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    protected function assertType(string $type): void
    {
        if (!in_array($type, ['Positive', 'Negative', 'Observation'], true)) {
            throw new ApiException('type must be Positive, Negative or Observation.', 422);
        }
    }

    protected function assertCanTouch(array $row): void
    {
        if ($this->permissions->canManageAllBehaviour()) {
            return;
        }
        if (($row['gibbonPersonIDCreator'] ?? '') == $this->session->get('gibbonPersonID')) {
            return;
        }
        throw new ApiException('You can only change behaviour records you created.', 403);
    }
}
