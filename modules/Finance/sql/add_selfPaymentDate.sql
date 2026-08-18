-- Split out-of-pocket date from school reimbursement / bank transfer date.
-- Run once on the live database.

ALTER TABLE `gibbonFinanceExpense`
  ADD `selfPaymentDate` date DEFAULT NULL AFTER `paymentDate`;

-- Existing self-purchase records stored the out-of-pocket date in paymentDate.
UPDATE `gibbonFinanceExpense`
SET `selfPaymentDate` = `paymentDate`
WHERE `purchaseBy` = 'Self'
  AND `paymentDate` IS NOT NULL
  AND `selfPaymentDate` IS NULL;

-- In-flight reimbursement requests must have paymentDate filled by the officer.
UPDATE `gibbonFinanceExpense`
SET `paymentDate` = NULL
WHERE `purchaseBy` = 'Self'
  AND `paymentReimbursementStatus` = 'Requested';
