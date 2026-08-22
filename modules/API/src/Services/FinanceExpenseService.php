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
use Gibbon\Domain\Finance\ExpenseGateway;
use Gibbon\Domain\Finance\FinanceBudgetCycleGateway;
use Gibbon\Domain\Finance\FinanceGateway;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\API\Auth\PermissionMapper;
use Gibbon\Module\API\Http\ApiException;
use Gibbon\Module\API\Http\WebProcessClient;
use Gibbon\Module\API\Support\RestTable;

class FinanceExpenseService
{
    public function __construct(
        protected PermissionMapper $permissions,
        protected Connection $db,
        protected Session $session,
        protected SettingGateway $settings,
        protected ExpenseGateway $expenses,
        protected FinanceGateway $budgets,
        protected FinanceBudgetCycleGateway $cycles,
        protected WebProcessClient $web
    ) {
    }

    public function list(array $query): array
    {
        $this->permissions->assertCanRequestOrManageExpenses();
        $cycleId = $query['gibbonFinanceBudgetCycleID'] ?? '';
        if ($cycleId === '') {
            throw new ApiException('gibbonFinanceBudgetCycleID is required.', 422);
        }
        RestTable::requireRow($this->cycles, $cycleId, 'Budget cycle not found.');
        $mine = ($query['mine'] ?? '') === 'Y';
        $personId = $this->session->get('gibbonPersonID');
        $params = ['cycle' => $cycleId];
        $sql = "SELECT gibbonFinanceExpense.*, gibbonFinanceBudget.name AS budget,
                       gibbonPerson.preferredName, gibbonPerson.surname
                FROM gibbonFinanceExpense
                JOIN gibbonFinanceBudget ON gibbonFinanceExpense.gibbonFinanceBudgetID=gibbonFinanceBudget.gibbonFinanceBudgetID
                JOIN gibbonPerson ON gibbonPerson.gibbonPersonID=gibbonFinanceExpense.gibbonPersonIDCreator
                WHERE gibbonFinanceExpense.gibbonFinanceBudgetCycleID=:cycle";
        if ($mine || (!$this->permissions->canManageExpenses() && $this->permissions->canRequestExpenses())) {
            $sql .= ' AND gibbonFinanceExpense.gibbonPersonIDCreator=:person';
            $params['person'] = $personId;
        } elseif (!$this->permissions->canManageAllExpenses()) {
            $sql .= " AND gibbonFinanceExpense.gibbonFinanceBudgetID IN (
                SELECT gibbonFinanceBudgetID FROM gibbonFinanceBudgetPerson
                WHERE gibbonPersonID=:person AND access IN ('Full','Write','Read')
            )";
            $params['person'] = $personId;
        }
        $sql .= ' ORDER BY gibbonFinanceExpense.timestampCreator DESC';

        return ['data' => $this->db->select($sql, $params)->fetchAll()];
    }

    public function get(string $id): array
    {
        $this->permissions->assertCanRequestOrManageExpenses();
        $row = RestTable::requireRow($this->expenses, $id, 'Expense not found.');
        $this->assertCanSeeExpense($row);
        $row['log'] = $this->expenses->queryExpenseLogByID($this->expenses->newQueryCriteria()->pageSize(0), $id)->toArray();
        $budget = $this->budgets->getByID($row['gibbonFinanceBudgetID']);
        $row['budget'] = $budget['name'] ?? null;

        return $row;
    }

    public function create(array $body): array
    {
        $adminAdd = $this->permissions->canManageAllExpenses()
            && ($this->settings->getSettingByScope('Finance', 'allowExpenseAdd') === 'Y')
            && isset($body['status'])
            && $body['status'] !== 'Requested';

        if ($adminAdd) {
            return $this->createAdmin($body);
        }

        if (!$this->permissions->canRequestExpenses()) {
            throw new ApiException('You do not have permission to request expenses.', 403);
        }

        $data = RestTable::pick($body, [
            'gibbonFinanceBudgetCycleID', 'gibbonFinanceBudgetID', 'title', 'body',
            'cost', 'countAgainstBudget', 'purchaseBy', 'purchaseDetails',
        ]);
        RestTable::requireFields($data, ['gibbonFinanceBudgetCycleID', 'gibbonFinanceBudgetID', 'title', 'cost', 'purchaseBy', 'countAgainstBudget']);
        RestTable::requireRow($this->cycles, $data['gibbonFinanceBudgetCycleID'], 'Budget cycle not found.');
        RestTable::requireRow($this->budgets, $data['gibbonFinanceBudgetID'], 'Budget not found.');
        $data = RestTable::defaults($data, ['body' => '', 'purchaseDetails' => '', 'status' => 'Requested']);
        $data['status'] = 'Requested';
        $data['gibbonPersonIDCreator'] = $this->session->get('gibbonPersonID');
        $data['timestampCreator'] = date('Y-m-d H:i:s');
        $data['statusApprovalBudgetCleared'] = $this->canSelfClearBudget($data['gibbonFinanceBudgetID']) ? 'Y' : 'N';
        $data['paymentReimbursementReceipt'] = '';
        $row = RestTable::create($this->expenses, $data);
        $this->addLog($row['gibbonFinanceExpenseID'], 'Request', '');

        return $this->get($row['gibbonFinanceExpenseID']);
    }

    public function approve(string $id, array $body): array
    {
        if (!$this->permissions->canManageExpenses()) {
            throw new ApiException('You do not have permission to approve expenses.', 403);
        }
        $row = RestTable::requireRow($this->expenses, $id, 'Expense not found.');
        $this->assertCanSeeExpense($row);

        $approval = $body['approval'] ?? '';
        $map = [
            'Approval' => 'Approval - Partial',
            'Approve' => 'Approval - Partial',
            'Approval - Partial' => 'Approval - Partial',
            'Rejection' => 'Rejection',
            'Reject' => 'Rejection',
            'Comment' => 'Comment',
        ];
        if (!isset($map[$approval])) {
            throw new ApiException('approval must be Approval, Rejection or Comment (same choices as the web form).', 422);
        }

        $result = $this->web->post('modules/Finance/expenses_manage_approveProcess.php', [
            'address' => '/modules/Finance/expenses_manage_approve.php',
            'gibbonFinanceExpenseID' => $id,
            'gibbonFinanceBudgetCycleID' => $row['gibbonFinanceBudgetCycleID'],
            'gibbonFinanceBudgetID' => $body['gibbonFinanceBudgetID'] ?? $row['gibbonFinanceBudgetID'],
            'gibbonFinanceBudgetID2' => $body['gibbonFinanceBudgetID2'] ?? '',
            'status2' => $body['status2'] ?? '',
            'approval' => $map[$approval],
            'comment' => (string) ($body['comment'] ?? ''),
        ]);
        $this->web->assertSuccess($result, 'The web approval process did not complete.');

        return $this->get($id);
    }

    public function reimburse(string $id, array $body): array
    {
        if (!$this->permissions->canRequestExpenses()) {
            throw new ApiException('You do not have permission to reimburse expenses.', 403);
        }
        $row = RestTable::requireRow($this->expenses, $id, 'Expense not found.');
        if ($row['gibbonPersonIDCreator'] != $this->session->get('gibbonPersonID') && !$this->permissions->canManageAllExpenses()) {
            throw new ApiException('You can only reimburse your own expense requests.', 403);
        }
        if (($row['status'] ?? '') !== 'Approved' && ($row['status'] ?? '') !== 'Paid') {
            throw new ApiException('Only approved expenses can be marked reimbursed.', 422);
        }
        $data = RestTable::pick($body, ['paymentDate', 'paymentAmount', 'paymentMethod', 'paymentID', 'gibbonPersonIDPayment', 'selfPaymentDate']);
        RestTable::requireFields($data, ['paymentDate', 'paymentAmount', 'paymentMethod']);
        $data['status'] = 'Paid';
        $data['paymentReimbursementStatus'] = 'Complete';
        $data['gibbonPersonIDPayment'] = $data['gibbonPersonIDPayment'] ?? $this->session->get('gibbonPersonID');
        if (!isset($body['paymentReimbursementReceipt'])) {
            $data['paymentReimbursementReceipt'] = $row['paymentReimbursementReceipt'] ?: '';
        } else {
            $data['paymentReimbursementReceipt'] = (string) $body['paymentReimbursementReceipt'];
        }
        $this->expenses->update($id, $data);
        $this->addLog($id, 'Reimbursement', (string) ($body['comment'] ?? ''));

        return $this->get($id);
    }

    public function printPayload(string $id): array
    {
        return $this->get($id);
    }

    protected function createAdmin(array $body): array
    {
        $data = RestTable::pick($body, [
            'gibbonFinanceBudgetCycleID', 'gibbonFinanceBudgetID', 'title', 'body', 'status',
            'cost', 'countAgainstBudget', 'purchaseBy', 'purchaseDetails',
            'paymentDate', 'paymentAmount', 'gibbonPersonIDPayment', 'paymentMethod', 'paymentID',
        ]);
        RestTable::requireFields($data, ['gibbonFinanceBudgetCycleID', 'gibbonFinanceBudgetID', 'title', 'cost', 'status', 'countAgainstBudget', 'purchaseBy']);
        if ($data['status'] === 'Paid') {
            RestTable::requireFields($data, ['paymentDate', 'paymentAmount', 'gibbonPersonIDPayment', 'paymentMethod']);
        }
        $data = RestTable::defaults($data, [
            'body' => '',
            'purchaseDetails' => '',
            'statusApprovalBudgetCleared' => 'Y',
            'paymentReimbursementReceipt' => '',
            'gibbonPersonIDCreator' => $this->session->get('gibbonPersonID'),
            'timestampCreator' => date('Y-m-d H:i:s'),
        ]);
        $row = RestTable::create($this->expenses, $data);
        $this->addLog($row['gibbonFinanceExpenseID'], $data['status'] === 'Paid' ? 'Payment' : 'Request', '');

        return $this->get($row['gibbonFinanceExpenseID']);
    }

    protected function assertCanSeeExpense(array $row): void
    {
        $personId = $this->session->get('gibbonPersonID');
        if ($this->permissions->canManageAllExpenses()) {
            return;
        }
        if (($row['gibbonPersonIDCreator'] ?? '') == $personId) {
            return;
        }
        if ($this->permissions->canManageExpenses() && $this->hasBudgetAccess($row['gibbonFinanceBudgetID'], ['Full', 'Write', 'Read'])) {
            return;
        }
        throw new ApiException('You do not have permission to view this expense.', 403);
    }

    protected function canSelfClearBudget(string $budgetId): bool
    {
        if ($this->settings->getSettingByScope('Finance', 'budgetLevelExpenseApproval') === 'N') {
            return true;
        }

        return $this->hasBudgetAccess($budgetId, ['Full']);
    }

    protected function hasBudgetAccess(string $budgetId, array $levels): bool
    {
        $access = $this->db->selectOne(
            "SELECT access FROM gibbonFinanceBudgetPerson
             WHERE gibbonFinanceBudgetID=:budget AND gibbonPersonID=:person LIMIT 1",
            ['budget' => $budgetId, 'person' => $this->session->get('gibbonPersonID')]
        );
        $value = is_array($access) ? ($access['access'] ?? '') : (string) $access;

        return in_array($value, $levels, true);
    }

    protected function addLog(string $expenseId, string $action, string $comment): void
    {
        $this->db->insert(
            "INSERT INTO gibbonFinanceExpenseLog SET gibbonFinanceExpenseID=:id, gibbonPersonID=:person, timestamp=:ts, action=:action, comment=:comment",
            [
                'id' => $expenseId,
                'person' => $this->session->get('gibbonPersonID'),
                'ts' => date('Y-m-d H:i:s'),
                'action' => $action,
                'comment' => $comment,
            ]
        );
    }
}
