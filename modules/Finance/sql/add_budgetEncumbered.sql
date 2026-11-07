-- A requested expense reserves budget only after someone has approved it.
-- Run once. Approved, ordered, and paid expenses are unchanged.

ALTER TABLE `gibbonFinanceExpense`
  ADD `budgetEncumbered` enum('Y','N') NOT NULL DEFAULT 'N' AFTER `statusApprovalBudgetCleared`;

UPDATE `gibbonFinanceExpense`
SET `budgetEncumbered`='Y'
WHERE `status`='Requested'
AND EXISTS (
  SELECT 1 FROM `gibbonFinanceExpenseLog`
  WHERE `gibbonFinanceExpenseLog`.`gibbonFinanceExpenseID`=`gibbonFinanceExpense`.`gibbonFinanceExpenseID`
  AND `gibbonFinanceExpenseLog`.`action` IN (
    'Approval - Partial - Budget',
    'Approval - Partial - School',
    'Approval - Final',
    'Approval - Exempt'
  )
);
