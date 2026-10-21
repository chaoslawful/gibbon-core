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

include '../../gibbon.php';
include './moduleFunctions.php';

$voucherAction = $_POST['voucherAction'] ?? '';
$gibbonFinanceExpenseID = $_POST['gibbonFinanceExpenseID'] ?? '';
$gibbonFinanceBudgetCycleID = $_GET['gibbonFinanceBudgetCycleID'] ?? '';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/Finance/expenses_manage.php'
    .'&gibbonFinanceBudgetCycleID='.urlencode($gibbonFinanceBudgetCycleID)
    .'&status2='.urlencode($_GET['status2'] ?? '')
    .'&gibbonFinanceBudgetID2='.urlencode($_GET['gibbonFinanceBudgetID2'] ?? '')
    .'&voucherStatus2='.urlencode($_GET['voucherStatus2'] ?? '');

if ($gibbonFinanceBudgetCycleID == '' || $voucherAction == '' || $gibbonFinanceExpenseID == '') {
    echo 'Fatal error loading this page!';
    exit;
}

$scope = canBulkUpdateExpenseVouchers($connection2, $guid, $session->get('gibbonPersonID'));
if (isActionAccessible($guid, $connection2, '/modules/Finance/expenses_manage.php') == false || $scope === false || !expenseVoucherSchemaReady($connection2)) {
    header('Location: '.$URL.'&return=error0');
    exit;
}

$allowedActions = ['voucherIssue', 'voucherPrint', 'voucherSubmit', 'voucherReissue', 'voucherAssign'];
$reason = trim((string) ($_POST['voucherReissueReason'] ?? ''));
$assign = expenseVoucherAssignInput($_POST['voucherAssignChoice'] ?? '');
if (!in_array($voucherAction, $allowedActions, true) || !ctype_digit((string) $gibbonFinanceExpenseID)) {
    header('Location: '.$URL.'&return=error1');
    exit;
}
if ($voucherAction === 'voucherReissue' && $reason === '') {
    header('Location: '.$URL.'&return=error1');
    exit;
}
if ($voucherAction === 'voucherAssign' && $assign === null) {
    header('Location: '.$URL.'&return=error1');
    exit;
}

try {
    $result = applyExpenseVoucherBulk($connection2, [$gibbonFinanceExpenseID], $voucherAction, $session->get('gibbonPersonID'), [
        'reason' => $reason,
        'assignStatus' => is_array($assign) ? $assign['assignStatus'] : '',
        'assignReissue' => is_array($assign) ? $assign['assignReissue'] : 'N',
    ], $scope);
} catch (PDOException $e) {
    header('Location: '.$URL.'&return=error2');
    exit;
}

$returnCode = ($result['updated'] > 0) ? 'successVoucher' : 'warningVoucher';
header('Location: '.$URL.'&return='.$returnCode
    .'&voucherUpdated='.(int) $result['updated']
    .'&voucherSkipped='.(int) $result['skipped']);
