-- Invoice voucher progress, separate from staff reimbursement.
-- Run once on the live database. Existing paid expenses stay untracked.

ALTER TABLE `gibbonFinanceExpense`
  ADD `voucherStatus` enum('Pending Invoice','Pending Print','Pending Submission','Submitted') DEFAULT NULL AFTER `paymentReimbursementStatus`,
  ADD `voucherReissueCount` int UNSIGNED NOT NULL DEFAULT 0 AFTER `voucherStatus`;

ALTER TABLE `gibbonFinanceExpenseLog`
  MODIFY `action` enum(
    'Request',
    'Approval - Partial - Budget',
    'Approval - Partial - School',
    'Approval - Final',
    'Approval - Exempt',
    'Rejection',
    'Cancellation',
    'Order',
    'Payment',
    'Reimbursement Request',
    'Reimbursement Completion',
    'Comment',
    'Voucher - Started',
    'Voucher - Invoice Issued',
    'Voucher - Printed',
    'Voucher - Submitted',
    'Voucher - Reissue',
    'Voucher - Assign'
  ) NOT NULL;
