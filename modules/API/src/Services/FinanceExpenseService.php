<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

namespace Gibbon\Module\API\Services;

use Gibbon\Comms\NotificationSender;
use Gibbon\Contracts\Database\Connection;
use Gibbon\Contracts\Services\Session;
use Gibbon\Domain\Finance\ExpenseGateway;
use Gibbon\Domain\Finance\FinanceBudgetCycleGateway;
use Gibbon\Domain\Finance\FinanceExpenseApproverGateway;
use Gibbon\Domain\Finance\FinanceGateway;
use Gibbon\Domain\System\NotificationGateway;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\API\Auth\PermissionMapper;
use Gibbon\Module\API\Http\ApiException;
use Gibbon\Module\API\Support\RestTable;
use Gibbon\Services\Format;

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
        protected FinanceExpenseApproverGateway $approvers,
        protected NotificationGateway $notifications,
        protected NotificationSender $notifier
    ) {
    }

    public const LIST_STATUSES = [
        'Requested',
        'Approved',
        'Rejected',
        'Cancelled',
        'Ordered',
        'Paid',
    ];

    public function list(array $query): array
    {
        $this->permissions->assertCanRequestOrManageExpenses();
        $cycleId = $query['gibbonFinanceBudgetCycleID'] ?? '';
        if ($cycleId === '') {
            throw new ApiException('gibbonFinanceBudgetCycleID is required.', 422);
        }
        RestTable::requireRow($this->cycles, $cycleId, 'Budget cycle not found.');

        $status = trim((string) ($query['status'] ?? ''));
        $budgetId = trim((string) ($query['gibbonFinanceBudgetID'] ?? ''));
        if ($status !== '' && !in_array($status, self::LIST_STATUSES, true)) {
            throw new ApiException('status must be one of: '.implode(', ', self::LIST_STATUSES).'.', 422);
        }
        if ($budgetId !== '') {
            RestTable::requireRow($this->budgets, $budgetId, 'Budget not found.');
        }

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
        if ($status !== '') {
            $sql .= ' AND gibbonFinanceExpense.status=:status';
            $params['status'] = $status;
        }
        if ($budgetId !== '') {
            $sql .= ' AND gibbonFinanceExpense.gibbonFinanceBudgetID=:budget';
            $params['budget'] = $budgetId;
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

    public function createApproval(string $id, array $body): array
    {
        if (!$this->permissions->canManageExpenses()) {
            throw new ApiException('You do not have permission to approve expenses.', 403);
        }
        $row = RestTable::requireRow($this->expenses, $id, 'Expense not found.');
        $this->assertCanDecideExpense($row);
        $this->assertApprovalSettingsReady();

        $decision = strtolower(trim((string) ($body['decision'] ?? '')));
        $comment = (string) ($body['comment'] ?? '');
        if (!in_array($decision, ['approve', 'reject', 'comment'], true)) {
            throw new ApiException('decision must be approve, reject or comment.', 422);
        }
        if (($row['status'] ?? '') !== 'Requested' && $decision !== 'comment') {
            throw new ApiException('Only requested expenses can be approved or rejected.', 422);
        }

        $this->loadFinanceFunctions();
        $action = $this->actionForDecision($decision, $row);
        if ($decision === 'approve' && !$this->personCanApprove($row)) {
            throw new ApiException('You are not the current approver for this expense.', 403);
        }

        $this->db->beginTransaction();
        try {
            $logId = $this->addLog($id, $action, $comment);
            if ($decision === 'reject') {
                $this->expenses->update($id, ['status' => 'Rejected']);
            } elseif ($decision === 'approve') {
                $this->applyApproval($id, $action);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e instanceof ApiException ? $e : new ApiException('Unable to save the approval.', 500);
        }

        try {
            $this->notifications->archiveNotificationForPersonAction(
                $this->session->get('gibbonPersonID'),
                "/index.php?q=/modules/Finance/expenses_manage_approve.php&gibbonFinanceExpenseID=$id"
            );
            $this->notifyAfterDecision($decision, $this->get($id));
        } catch (\Throwable $e) {
            // Match the web process: notification failure does not undo the approval.
        }

        $log = $this->db->selectOne(
            'SELECT * FROM gibbonFinanceExpenseLog WHERE gibbonFinanceExpenseLogID=:id',
            ['id' => $logId]
        );

        return is_array($log) ? $log + ['expense' => $this->get($id)] : ['expense' => $this->get($id)];
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

    protected function assertCanDecideExpense(array $row): void
    {
        if ($this->permissions->canManageAllExpenses()) {
            return;
        }
        if ($this->hasBudgetAccess($row['gibbonFinanceBudgetID'], ['Full'])) {
            return;
        }
        throw new ApiException('You do not have permission to approve this expense.', 403);
    }

    protected function assertApprovalSettingsReady(): void
    {
        $type = $this->settings->getSettingByScope('Finance', 'expenseApprovalType');
        $budgetLevel = $this->settings->getSettingByScope('Finance', 'budgetLevelExpenseApproval');
        if ($type === '' || $budgetLevel === '') {
            throw new ApiException('Expense approval settings are not configured.', 422);
        }
        $approvers = $this->approvers->selectExpenseApprovers();
        if ($approvers->rowCount() < 1) {
            throw new ApiException('Expense approval settings are not configured.', 422);
        }
    }

    protected function actionForDecision(string $decision, array $row): string
    {
        if ($decision === 'reject') {
            return 'Rejection';
        }
        if ($decision === 'comment') {
            return 'Comment';
        }
        if (($row['statusApprovalBudgetCleared'] ?? '') === 'N') {
            return 'Approval - Partial - Budget';
        }
        $approver = $this->db->selectOne(
            "SELECT gibbonFinanceExpenseApprover.gibbonPersonID
             FROM gibbonFinanceExpenseApprover
             JOIN gibbonPerson ON gibbonFinanceExpenseApprover.gibbonPersonID=gibbonPerson.gibbonPersonID
             WHERE gibbonPerson.status='Full' AND gibbonFinanceExpenseApprover.gibbonPersonID=:person LIMIT 1",
            ['person' => $this->session->get('gibbonPersonID')]
        );
        if (empty($approver)) {
            throw new ApiException('You are not a school expense approver.', 403);
        }

        return 'Approval - Partial - School';
    }

    protected function personCanApprove(array $row): bool
    {
        global $guid, $connection2;

        return approvalRequired(
            $guid,
            $this->session->get('gibbonPersonID'),
            $row['gibbonFinanceExpenseID'],
            $row['gibbonFinanceBudgetCycleID'],
            $connection2,
            false
        ) === true;
    }

    protected function applyApproval(string $id, string $action): void
    {
        global $guid, $connection2;

        if ($action === 'Approval - Partial - Budget') {
            $this->expenses->update($id, ['statusApprovalBudgetCleared' => 'Y']);
        }

        $completion = checkLogForApprovalComplete($guid, $id, $connection2);
        if ($completion === false || $completion === 'none') {
            throw new ApiException('The approval could not be completed.', 500);
        }
        if ($completion === 'budget') {
            $this->expenses->update($id, ['statusApprovalBudgetCleared' => 'Y']);
            return;
        }
        if ($completion === 'school') {
            $this->addLog($id, 'Approval - Final', '');
            $this->expenses->update($id, ['status' => 'Approved']);
        }
    }

    protected function notifyAfterDecision(string $decision, array $row): void
    {
        $id = $row['gibbonFinanceExpenseID'];
        $cycleId = $row['gibbonFinanceBudgetCycleID'];
        $view = "/index.php?q=/modules/Finance/expenses_manage_view.php&gibbonFinanceExpenseID=$id&gibbonFinanceBudgetCycleID=$cycleId&status2=&gibbonFinanceBudgetID2=".$row['gibbonFinanceBudgetID'];

        if ($decision === 'reject') {
            $this->notifier->addNotification(
                $row['gibbonPersonIDCreator'],
                sprintf(__('Your expense request for "%1$s" in budget "%2$s" has been rejected.'), $row['title'], $row['budget']),
                'Finance',
                $view
            );
            $this->notifier->sendNotifications();
            return;
        }
        if ($decision === 'comment') {
            $personName = Format::name('', $this->session->get('preferredName'), $this->session->get('surname'), 'Staff', false, true);
            $this->notifier->addNotification(
                $row['gibbonPersonIDCreator'],
                __('{person} has commented on your expense request for {title} in budget {budgetName}.', [
                    'person' => $personName,
                    'title' => $row['title'],
                    'budgetName' => $row['budget'],
                ]),
                'Finance',
                $view
            );
            $this->notifier->sendNotifications();
            return;
        }

        global $guid, $connection2;
        if (($row['status'] ?? '') === 'Approved') {
            $extra = '';
            $officer = $this->settings->getSettingByScope('Finance', 'purchasingOfficer');
            if ($officer && ($row['purchaseBy'] ?? '') === 'School') {
                $this->notifier->addNotification(
                    $officer,
                    sprintf(__('A newly approved expense (%1$s) needs to be purchased from budget "%2$s".'), $row['title'], $row['budget']),
                    'Finance',
                    $view
                );
                $this->notifier->sendNotifications();
                $extra = '. '.__('The Purchasing Officer has been alerted, and will purchase the item on your behalf.');
            }
            $this->notifier->addNotification(
                $row['gibbonPersonIDCreator'],
                sprintf(__('Your expense request for "%1$s" in budget "%2$s" has been fully approved.').$extra, $row['title'], $row['budget']),
                'Finance',
                $view
            );
            $this->notifier->sendNotifications();
            return;
        }

        setExpenseNotification($guid, $id, $cycleId, $connection2);
    }

    protected function loadFinanceFunctions(): void
    {
        require_once __DIR__.'/../../../Finance/moduleFunctions.php';
    }

    protected function addLog(string $expenseId, string $action, string $comment): string
    {
        $id = $this->db->insert(
            "INSERT INTO gibbonFinanceExpenseLog SET gibbonFinanceExpenseID=:id, gibbonPersonID=:person, timestamp=:ts, action=:action, comment=:comment",
            [
                'id' => $expenseId,
                'person' => $this->session->get('gibbonPersonID'),
                'ts' => date('Y-m-d H:i:s'),
                'action' => $action,
                'comment' => $comment,
            ]
        );
        if (empty($id)) {
            throw new ApiException('Unable to write expense log.', 500);
        }

        return (string) $id;
    }
}
