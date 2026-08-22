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
use Gibbon\Domain\Finance\FinanceBudgetCycleGateway;
use Gibbon\Domain\Finance\FinanceExpenseApproverGateway;
use Gibbon\Domain\Finance\FinanceGateway;
use Gibbon\Domain\Finance\PettyCashGateway;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\API\Auth\PermissionMapper;
use Gibbon\Module\API\Http\ApiException;
use Gibbon\Module\API\Support\RestTable;

class FinanceAdminService
{
    public function __construct(
        protected PermissionMapper $permissions,
        protected Connection $db,
        protected Session $session,
        protected SettingGateway $settings,
        protected FinanceBudgetCycleGateway $cycles,
        protected FinanceGateway $budgets,
        protected FinanceExpenseApproverGateway $approvers,
        protected PettyCashGateway $pettyCash
    ) {
    }

    public function listCycles(): array
    {
        $this->permissions->assertCanManageBudgetCycles();

        return $this->cycles->selectBy([])->fetchAll();
    }

    public function createCycle(array $body): array
    {
        $this->permissions->assertCanManageBudgetCycles();
        $data = RestTable::pick($body, ['name', 'status', 'sequenceNumber', 'dateStart', 'dateEnd']);
        RestTable::requireFields($data, ['name', 'status', 'sequenceNumber', 'dateStart', 'dateEnd']);
        if (!is_numeric($data['sequenceNumber'])) {
            throw new ApiException('sequenceNumber must be numeric.', 422);
        }
        if (!in_array($data['status'], ['Past', 'Current', 'Upcoming'], true)) {
            throw new ApiException('status must be Past, Current or Upcoming.', 422);
        }
        if (!$this->cycles->unique($data, ['name']) || !$this->cycles->unique($data, ['sequenceNumber'])) {
            throw new ApiException('name and sequenceNumber must be unique.', 422);
        }
        $data['gibbonPersonIDCreator'] = $this->session->get('gibbonPersonID');
        $data['timestampCreator'] = date('Y-m-d H:i:s');
        $row = RestTable::create($this->cycles, $data);
        if (isset($body['allocations'])) {
            $this->replaceAllocations($row['gibbonFinanceBudgetCycleID'], $body['allocations']);
        }

        return $this->getCycle($row['gibbonFinanceBudgetCycleID']);
    }

    public function getCycle(string $id): array
    {
        $this->permissions->assertCanManageBudgetCycles();
        $row = RestTable::requireRow($this->cycles, $id, 'Budget cycle not found.');
        $row['allocations'] = $this->listAllocations($id);

        return $row;
    }

    public function updateCycle(string $id, array $body): array
    {
        $this->permissions->assertCanManageBudgetCycles();
        $existing = RestTable::requireRow($this->cycles, $id, 'Budget cycle not found.');
        $data = RestTable::pick($body, ['name', 'status', 'sequenceNumber', 'dateStart', 'dateEnd']);
        if (isset($data['status']) && !in_array($data['status'], ['Past', 'Current', 'Upcoming'], true)) {
            throw new ApiException('status must be Past, Current or Upcoming.', 422);
        }
        $merged = array_merge($existing, $data);
        if (isset($data['name']) && !$this->cycles->unique($merged, ['name'], $id)) {
            throw new ApiException('name must be unique.', 422);
        }
        if (isset($data['sequenceNumber']) && !$this->cycles->unique($merged, ['sequenceNumber'], $id)) {
            throw new ApiException('sequenceNumber must be unique.', 422);
        }
        $data['gibbonPersonIDUpdate'] = $this->session->get('gibbonPersonID');
        $data['timestampUpdate'] = date('Y-m-d H:i:s');
        RestTable::update($this->cycles, $id, $data, 'Budget cycle not found.');
        if (isset($body['allocations'])) {
            $this->replaceAllocations($id, $body['allocations']);
        }

        return $this->getCycle($id);
    }

    public function deleteCycle(string $id): void
    {
        $this->permissions->assertCanManageBudgetCycles();
        RestTable::requireRow($this->cycles, $id, 'Budget cycle not found.');
        $this->db->delete('DELETE FROM gibbonFinanceBudgetCycleAllocation WHERE gibbonFinanceBudgetCycleID=:id', ['id' => $id]);
        RestTable::delete($this->cycles, $id, 'Budget cycle not found.');
    }

    public function listAllocations(string $cycleId): array
    {
        $this->permissions->assertCanManageBudgetCycles();
        RestTable::requireRow($this->cycles, $cycleId, 'Budget cycle not found.');

        return $this->db->select(
            "SELECT gibbonFinanceBudget.gibbonFinanceBudgetID,
                    gibbonFinanceBudget.name,
                    gibbonFinanceBudget.nameShort,
                    gibbonFinanceBudget.active,
                    gibbonFinanceBudget.category,
                    gibbonFinanceBudgetCycleAllocation.gibbonFinanceBudgetCycleAllocationID,
                    COALESCE(gibbonFinanceBudgetCycleAllocation.value, 0.00) AS value
             FROM gibbonFinanceBudget
             LEFT JOIN gibbonFinanceBudgetCycleAllocation
               ON gibbonFinanceBudgetCycleAllocation.gibbonFinanceBudgetID=gibbonFinanceBudget.gibbonFinanceBudgetID
              AND gibbonFinanceBudgetCycleAllocation.gibbonFinanceBudgetCycleID=:cycle
             ORDER BY gibbonFinanceBudget.name",
            ['cycle' => $cycleId]
        )->fetchAll();
    }

    public function saveAllocations(string $cycleId, array $body): array
    {
        $this->permissions->assertCanManageBudgetCycles();
        RestTable::requireRow($this->cycles, $cycleId, 'Budget cycle not found.');
        $rows = $body['allocations'] ?? null;
        if (!is_array($rows)) {
            throw new ApiException('allocations must be an array of { gibbonFinanceBudgetID, value }.', 422);
        }
        $this->replaceAllocations($cycleId, $rows);

        return ['data' => $this->listAllocations($cycleId)];
    }

    protected function replaceAllocations(string $cycleId, array $rows): void
    {
        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                throw new ApiException('allocations['.$index.'] must be an object.', 422);
            }
            $budgetId = (string) ($row['gibbonFinanceBudgetID'] ?? '');
            $value = $row['value'] ?? '';
            if ($budgetId === '' || $value === '' || !is_numeric($value)) {
                throw new ApiException('allocations['.$index.'] needs gibbonFinanceBudgetID and a numeric value.', 422);
            }
            RestTable::requireRow($this->budgets, $budgetId, 'Budget not found: '.$budgetId);
            $existing = $this->db->selectOne(
                "SELECT gibbonFinanceBudgetCycleAllocationID FROM gibbonFinanceBudgetCycleAllocation
                 WHERE gibbonFinanceBudgetCycleID=:cycle AND gibbonFinanceBudgetID=:budget",
                ['cycle' => $cycleId, 'budget' => $budgetId]
            );
            if (!empty($existing)) {
                $this->db->update(
                    "UPDATE gibbonFinanceBudgetCycleAllocation SET value=:value
                     WHERE gibbonFinanceBudgetCycleID=:cycle AND gibbonFinanceBudgetID=:budget",
                    ['value' => $value, 'cycle' => $cycleId, 'budget' => $budgetId]
                );
            } else {
                $this->db->insert(
                    "INSERT INTO gibbonFinanceBudgetCycleAllocation SET value=:value, gibbonFinanceBudgetCycleID=:cycle, gibbonFinanceBudgetID=:budget",
                    ['value' => $value, 'cycle' => $cycleId, 'budget' => $budgetId]
                );
            }
        }
    }

    public function listBudgets(): array
    {
        $this->permissions->assertCanManageBudgets();

        return $this->budgets->selectBy([])->fetchAll();
    }

    public function getBudget(string $id): array
    {
        $this->permissions->assertCanManageBudgets();
        $row = RestTable::requireRow($this->budgets, $id, 'Budget not found.');
        $row['staff'] = $this->db->select(
            "SELECT gibbonFinanceBudgetPersonID, gibbonPersonID, access
             FROM gibbonFinanceBudgetPerson WHERE gibbonFinanceBudgetID=:id",
            ['id' => $id]
        )->fetchAll();

        return $row;
    }

    public function createBudget(array $body): array
    {
        $this->permissions->assertCanManageBudgets();
        $data = RestTable::pick($body, ['name', 'nameShort', 'active', 'category']);
        RestTable::requireFields($data, ['name', 'nameShort', 'active', 'category']);
        if (!$this->budgets->unique($data, ['name']) || !$this->budgets->unique($data, ['nameShort'])) {
            throw new ApiException('name and nameShort must be unique.', 422);
        }
        $data = RestTable::defaults($data, ['active' => 'Y']);
        $data['gibbonPersonIDCreator'] = $this->session->get('gibbonPersonID');
        $data['timestampCreator'] = date('Y-m-d H:i:s');
        $row = RestTable::create($this->budgets, $data);
        $staff = $body['staff'] ?? [];
        $access = $body['access'] ?? 'Read';
        if (!in_array($access, ['Full', 'Write', 'Read'], true)) {
            $access = 'Read';
        }
        if (is_array($staff)) {
            foreach ($staff as $personId) {
                $this->addBudgetStaff($row['gibbonFinanceBudgetID'], (string) $personId, $access);
            }
        }

        return $this->getBudget($row['gibbonFinanceBudgetID']);
    }

    public function updateBudget(string $id, array $body): array
    {
        $this->permissions->assertCanManageBudgets();
        $data = RestTable::pick($body, ['name', 'nameShort', 'active', 'category']);
        $data['gibbonPersonIDUpdate'] = $this->session->get('gibbonPersonID');
        $data['timestampUpdate'] = date('Y-m-d H:i:s');
        RestTable::update($this->budgets, $id, $data, 'Budget not found.');

        return $this->getBudget($id);
    }

    public function deleteBudget(string $id): void
    {
        $this->permissions->assertCanManageBudgets();
        $this->db->delete('DELETE FROM gibbonFinanceBudgetPerson WHERE gibbonFinanceBudgetID=:id', ['id' => $id]);
        RestTable::delete($this->budgets, $id, 'Budget not found.');
    }

    public function addBudgetStaff(string $budgetId, string $gibbonPersonID, string $access): array
    {
        $this->permissions->assertCanManageBudgets();
        RestTable::requireRow($this->budgets, $budgetId, 'Budget not found.');
        if (!in_array($access, ['Full', 'Write', 'Read'], true)) {
            throw new ApiException('access must be Full, Write or Read.', 422);
        }
        $exists = $this->db->selectOne(
            "SELECT gibbonFinanceBudgetPersonID FROM gibbonFinanceBudgetPerson
             WHERE gibbonFinanceBudgetID=:budget AND gibbonPersonID=:person",
            ['budget' => $budgetId, 'person' => $gibbonPersonID]
        );
        if (!empty($exists)) {
            throw new ApiException('This person is already on the budget.', 422);
        }
        $this->db->insert(
            "INSERT INTO gibbonFinanceBudgetPerson SET gibbonFinanceBudgetID=:budget, gibbonPersonID=:person, access=:access",
            ['budget' => $budgetId, 'person' => $gibbonPersonID, 'access' => $access]
        );

        return $this->getBudget($budgetId);
    }

    public function removeBudgetStaff(string $id): void
    {
        $this->permissions->assertCanManageBudgets();
        $row = $this->db->selectOne(
            "SELECT * FROM gibbonFinanceBudgetPerson WHERE gibbonFinanceBudgetPersonID=:id",
            ['id' => $id]
        );
        if (empty($row) || empty($row['gibbonFinanceBudgetPersonID'])) {
            throw new ApiException('Budget staff record not found.', 404);
        }
        $this->db->delete('DELETE FROM gibbonFinanceBudgetPerson WHERE gibbonFinanceBudgetPersonID=:id', ['id' => $id]);
    }

    public function listApprovers(): array
    {
        $this->permissions->assertCanManageExpenseApprovers();

        return $this->approvers->selectBy([])->fetchAll();
    }

    public function createApprover(array $body): array
    {
        $this->permissions->assertCanManageExpenseApprovers();
        $data = RestTable::pick($body, ['gibbonPersonID', 'sequenceNumber']);
        RestTable::requireFields($data, ['gibbonPersonID']);
        $type = $this->settings->getSettingByScope('Finance', 'expenseApprovalType');
        if ($type === 'Chain Of All' && ($data['sequenceNumber'] ?? '') === '') {
            throw new ApiException('sequenceNumber is required for Chain Of All approval.', 422);
        }
        if ($type !== 'Chain Of All') {
            $data['sequenceNumber'] = $data['sequenceNumber'] ?? null;
        }
        $existing = $this->approvers->selectBy(['gibbonPersonID' => $data['gibbonPersonID']])->fetch();
        if (!empty($existing)) {
            throw new ApiException('This person is already an approver.', 422);
        }
        $data['gibbonPersonIDCreator'] = $this->session->get('gibbonPersonID');
        $data['timestampCreator'] = date('Y-m-d H:i:s');

        return RestTable::create($this->approvers, $data);
    }

    public function updateApprover(string $id, array $body): array
    {
        $this->permissions->assertCanManageExpenseApprovers();
        $data = RestTable::pick($body, ['gibbonPersonID', 'sequenceNumber']);

        return RestTable::update($this->approvers, $id, $data, 'Approver not found.');
    }

    public function deleteApprover(string $id): void
    {
        $this->permissions->assertCanManageExpenseApprovers();
        RestTable::delete($this->approvers, $id, 'Approver not found.');
    }

    public function listPettyCash(array $query): array
    {
        $this->permissions->assertCanManagePettyCash();
        $yearId = $query['gibbonSchoolYearID'] ?? $this->session->get('gibbonSchoolYearID');
        $criteria = $this->pettyCash->newQueryCriteria()->sortBy('statusSort')->pageSize(0);

        return $this->pettyCash->queryPettyCashBySchoolYear($criteria, $yearId)->toArray();
    }

    public function createPettyCash(array $body): array
    {
        $this->permissions->assertCanManagePettyCash();
        $data = RestTable::pick($body, [
            'gibbonSchoolYearID', 'gibbonPersonID', 'amount', 'reason', 'notes', 'actionRequired',
        ]);
        $data['gibbonSchoolYearID'] = $data['gibbonSchoolYearID'] ?? $this->session->get('gibbonSchoolYearID');
        RestTable::requireFields($data, ['gibbonSchoolYearID', 'gibbonPersonID', 'amount']);
        $data = RestTable::defaults($data, [
            'reason' => '',
            'notes' => '',
            'actionRequired' => $this->settings->getSettingByScope('Finance', 'pettyCashDefaultAction') ?: 'None',
        ]);
        $data['status'] = ($data['actionRequired'] === 'None') ? 'Complete' : 'Pending';
        $data['gibbonPersonIDCreated'] = $this->session->get('gibbonPersonID');
        $data['timestampCreated'] = date('Y-m-d H:i:s');

        return RestTable::create($this->pettyCash, $data);
    }

    public function updatePettyCash(string $id, array $body): array
    {
        $this->permissions->assertCanManagePettyCash();
        $data = RestTable::pick($body, [
            'gibbonPersonID', 'amount', 'reason', 'notes', 'actionRequired', 'status',
        ]);

        return RestTable::update($this->pettyCash, $id, $data, 'Petty cash record not found.');
    }

    public function deletePettyCash(string $id): void
    {
        $this->permissions->assertCanManagePettyCash();
        RestTable::delete($this->pettyCash, $id, 'Petty cash record not found.');
    }

    public function actionPettyCash(string $id, array $body): array
    {
        $this->permissions->assertCanManagePettyCash();
        $values = RestTable::requireRow($this->pettyCash, $id, 'Petty cash record not found.');
        if (($values['actionRequired'] ?? '') === 'Repay') {
            $status = 'Repaid';
        } elseif (($values['actionRequired'] ?? '') === 'Refund') {
            $status = 'Refunded';
        } else {
            throw new ApiException('This record has no repay/refund action required.', 422);
        }
        $timestamp = $body['timestampStatus'] ?? null;
        if (empty($timestamp) && !empty($body['statusDate'])) {
            $timestamp = $body['statusDate'].' '.($body['statusTime'] ?? '00:00:00');
        }
        if (empty($timestamp)) {
            $timestamp = date('Y-m-d H:i:s');
        }
        $this->pettyCash->update($id, [
            'status' => $status,
            'notes' => $body['notes'] ?? $values['notes'],
            'gibbonPersonIDStatus' => $this->session->get('gibbonPersonID'),
            'timestampStatus' => $timestamp,
        ]);

        return $this->pettyCash->getByID($id);
    }
}
