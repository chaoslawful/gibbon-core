<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

use Gibbon\Forms\Form;

require_once __DIR__ . '/moduleFunctions.php';

$gibbonFinanceExpenseID = $_GET['gibbonFinanceExpenseID'] ?? '';
$voucherAction = $_GET['voucherAction'] ?? '';
$returnParams = [
    'gibbonFinanceBudgetCycleID' => $_GET['gibbonFinanceBudgetCycleID'] ?? '',
    'status2' => $_GET['status2'] ?? '',
    'gibbonFinanceBudgetID2' => $_GET['gibbonFinanceBudgetID2'] ?? '',
    'voucherStatus2' => $_GET['voucherStatus2'] ?? '',
];

if (isActionAccessible($guid, $connection2, '/modules/Finance/expenses_manage.php') == false) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$scope = canBulkUpdateExpenseVouchers($connection2, $guid, $session->get('gibbonPersonID'));
if ($scope === false || !expenseVoucherSchemaReady($connection2) || $gibbonFinanceExpenseID == '' || $returnParams['gibbonFinanceBudgetCycleID'] == '') {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$allowedActions = ['voucherIssue', 'voucherPrint', 'voucherSubmit', 'voucherReissue', 'voucherAssign'];
if (!in_array($voucherAction, $allowedActions, true) || !ctype_digit((string) $gibbonFinanceExpenseID)) {
    $page->addError(__('You have not specified one or more required parameters.'));
    return;
}

$data = ['gibbonFinanceExpenseID' => $gibbonFinanceExpenseID];
$sql = 'SELECT gibbonFinanceExpenseID, title, purchaseDetails, status, voucherStatus, voucherReissueCount, gibbonPersonIDCreator FROM gibbonFinanceExpense WHERE gibbonFinanceExpenseID=:gibbonFinanceExpenseID';
if ($scope === 'own') {
    $sql .= ' AND gibbonPersonIDCreator=:gibbonPersonIDCreator';
    $data['gibbonPersonIDCreator'] = $session->get('gibbonPersonID');
}
$result = $connection2->prepare($sql);
$result->execute($data);
$expense = $result->fetch();

if (empty($expense)) {
    $page->addError(__('The specified record cannot be found.'));
    return;
}
if (!expenseVoucherActionApplies($expense, $voucherAction)) {
    $page->addError(__('No expenses were updated because their voucher progress did not match this action.'));
    return;
}

$titles = [
    'voucherIssue' => __('Mark Invoice Issued'),
    'voucherPrint' => __('Mark Printed'),
    'voucherSubmit' => __('Mark Submitted to Finance'),
    'voucherReissue' => __('Return for Reissue'),
    'voucherAssign' => __('Record Untracked Orders'),
];

$form = Form::create('expenseVoucher', $session->get('absoluteURL').'/modules/Finance/expenses_manage_voucherProcess.php?'.http_build_query($returnParams));
$form->setTitle($titles[$voucherAction]);
$form->addHiddenValue('address', $session->get('address'));
$form->addHiddenValue('gibbonFinanceExpenseID', $gibbonFinanceExpenseID);
$form->addHiddenValue('voucherAction', $voucherAction);

$form->addRow()->addContent('<b>'.htmlPrep($expense['title']).'</b>');
$purchaseDetails = trim($expense['purchaseDetails'] ?? '');
if ($purchaseDetails !== '') {
    $form->addRow()->addContent('<div style="white-space:pre-wrap">'.htmlPrep($purchaseDetails).'</div>');
}

$confirm = expenseVoucherConfirmMessage($voucherAction);
if ($confirm !== '') {
    $form->addRow()->addContent($confirm);
}

if ($voucherAction === 'voucherReissue') {
    $row = $form->addRow();
        $row->addLabel('voucherReissueReason', __('Reason for reissue'));
        $row->addTextArea('voucherReissueReason')->setRows(3)->required();
}

if ($voucherAction === 'voucherAssign') {
    $row = $form->addRow();
        $row->addLabel('voucherAssignChoice', __('Where is the invoice now?'))
            ->description(__('This only records paid orders that are not tracked yet. Choosing returned for reissue sets the reissue count to 1.'));
        $row->addRadio('voucherAssignChoice')
            ->fromArray(expenseVoucherAssignChoices())
            ->required();
}

$form->addRow()->addSubmit();

echo $form->getOutput();
