<?php

namespace Gibbon\Domain\Finance;

use Gibbon\Domain\QueryableGateway;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\DataSet;

class ExpenseGateway extends QueryableGateway
{
    use TableAware;
    private static $primaryKey = 'gibbonFinanceExpenseID';
    private static $tableName = 'gibbonFinanceExpense';
    private static $searchableColumns = [];

    public function queryExpenseLogByID(QueryCriteria $criteria, $gibbonFinanceExpenseID)
    {
        $query = $this
            ->newQuery()
            ->cols([
                'gibbonPerson.preferredName',
                'gibbonPerson.title',
                'gibbonPerson.surname',
                'gibbonFinanceExpenseLog.timestamp',
                'gibbonFinanceExpenseLog.comment',
                'gibbonFinanceExpenseLog.action'
            ])
            ->from('gibbonFinanceExpenseLog')
            ->innerJoin('gibbonPerson', 'gibbonFinanceExpenseLog.gibbonPersonID = gibbonPerson.gibbonPersonID')
            ->where('gibbonFinanceExpenseLog.gibbonFinanceExpenseID = :gibbonFinanceExpenseID')
            ->bindValue('gibbonFinanceExpenseID', $gibbonFinanceExpenseID);

        return $this->runQuery($query, $criteria);
    }

    public function queryExpensesByBudgetCycleID(QueryCriteria $criteria, $gibbonFinanceBudgetCycleID, $gibbonPersonID = null)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonFinanceExpense.gibbonFinanceExpenseID',
                'gibbonFinanceExpense.gibbonPersonIDCreator',
                'gibbonFinanceExpense.status',
                'gibbonFinanceExpense.title',
                'gibbonFinanceExpense.paymentReimbursementStatus',
                'gibbonFinanceExpense.voucherStatus',
                'gibbonFinanceExpense.voucherReissueCount',
                'gibbonFinanceExpense.timestampCreator',
                'gibbonFinanceExpense.paymentDate',
                'gibbonFinanceExpense.cost',
                // XXX: added by wxz
                'gibbonFinanceExpense.selfPaymentDate',
                'gibbonFinanceExpense.paymentAmount',
                // XXX: ends here
                'gibbonFinanceExpense.purchaseBy',
                'gibbonFinanceBudget.name AS budget',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                "FIND_IN_SET(gibbonFinanceExpense.status, 'Pending,Issued,Paid,Refunded,Cancelled') AS defaultSortOrder"
            ])
            ->innerJoin('gibbonFinanceBudget', 'gibbonFinanceExpense.gibbonFinanceBudgetID = gibbonFinanceBudget.gibbonFinanceBudgetID')
            ->innerJoin('gibbonPerson', 'gibbonPerson.gibbonPersonID=gibbonFinanceExpense.gibbonPersonIDCreator')
            ->where('gibbonFinanceExpense.gibbonFinanceBudgetCycleID = :gibbonFinanceBudgetCycleID')
            ->bindValue('gibbonFinanceBudgetCycleID', $gibbonFinanceBudgetCycleID);

        if (!empty($gibbonPersonID)) {
            $query->where('gibbonFinanceExpense.gibbonPersonIDCreator = :gibbonPersonID')
                ->bindValue('gibbonPersonID', $gibbonPersonID);
        }

        $criteria->addFilterRules([
            'budget' => function ($query, $gibbonFinanceBudgetID) {
                return $query
                    ->where('gibbonFinanceExpense.gibbonFinanceBudgetID = :gibbonFinanceBudgetID')
                    ->bindValue('gibbonFinanceBudgetID', $gibbonFinanceBudgetID);
            },
            'status' => function ($query, $status) {
                return $query
                    ->where('gibbonFinanceExpense.status = :status')
                    ->bindValue('status', $status);
            },
            'creator' => function ($query, $gibbonPersonIDCreator) {
                return $query
                    ->where('gibbonFinanceExpense.gibbonPersonIDCreator = :gibbonPersonIDCreator')
                    ->bindValue('gibbonPersonIDCreator', $gibbonPersonIDCreator);
            },
            'voucher' => function ($query, $voucher) {
                if ($voucher === 'Untracked') {
                    return $query
                        ->where("gibbonFinanceExpense.status = 'Paid'")
                        ->where('gibbonFinanceExpense.voucherStatus IS NULL');
                }
                if ($voucher === 'Pending Invoice First') {
                    return $query
                        ->where("gibbonFinanceExpense.voucherStatus = 'Pending Invoice'")
                        ->where('gibbonFinanceExpense.voucherReissueCount = 0');
                }
                if ($voucher === 'Pending Invoice Reissue') {
                    return $query
                        ->where("gibbonFinanceExpense.voucherStatus = 'Pending Invoice'")
                        ->where('gibbonFinanceExpense.voucherReissueCount > 0');
                }

                return $query
                    ->where('gibbonFinanceExpense.voucherStatus = :voucherStatus')
                    ->bindValue('voucherStatus', $voucher);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function getExpenseByBudgetID($gibbonFinanceBudgetCycleID, $gibbonFinanceExpenseID)
    {
        $data = ['gibbonFinanceBudgetCycleID' => $gibbonFinanceBudgetCycleID, 'gibbonFinanceExpenseID' => $gibbonFinanceExpenseID];
        $sql = "SELECT gibbonFinanceExpense.*, gibbonFinanceBudget.name AS budget, surname, preferredName, 'Full' AS access
				FROM gibbonFinanceExpense
				JOIN gibbonFinanceBudget ON (gibbonFinanceExpense.gibbonFinanceBudgetID=gibbonFinanceBudget.gibbonFinanceBudgetID)
				JOIN gibbonPerson ON (gibbonFinanceExpense.gibbonPersonIDCreator=gibbonPerson.gibbonPersonID)
				WHERE gibbonFinanceBudgetCycleID=:gibbonFinanceBudgetCycleID AND gibbonFinanceExpenseID=:gibbonFinanceExpenseID AND gibbonFinanceExpense.status='Approved'";

        return $this->db()->selectOne($sql, $data);
    }
}
