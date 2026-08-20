<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

use Gibbon\Data\Validator;
use Gibbon\Domain\User\RoleGateway;
use Gibbon\Module\API\Domain\APITokenGateway;

include '../../gibbon.php';

$container->get(\Gibbon\Services\ModuleLoader::class)->registerModuleNamespace('API');

$_POST = $container->get(Validator::class)->sanitize($_POST);

$URL = $session->get('absoluteURL').'/index.php?q=/modules/API/tokens_manage_add.php';

if (isActionAccessible($guid, $connection2, '/modules/API/tokens_manage_add.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
}

$name = trim($_POST['name'] ?? '');
$gibbonRoleID = $_POST['gibbonRoleID'] ?? '';
$expiresInDays = isset($_POST['expiresInDays']) ? (int) $_POST['expiresInDays'] : 90;

if ($name === '' || $gibbonRoleID === '') {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
}

$role = $container->get(RoleGateway::class)->getAvailableUserRoleByID($session->get('gibbonPersonID'), $gibbonRoleID);
if (empty($role)) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
}

$plain = 'gib_pat_'.bin2hex(random_bytes(32));
$expiresAt = $expiresInDays > 0 ? date('Y-m-d H:i:s', strtotime('+'.$expiresInDays.' days')) : null;

$container->get(APITokenGateway::class)->insert([
    'gibbonPersonID' => $session->get('gibbonPersonID'),
    'gibbonRoleID' => $gibbonRoleID,
    'gibbonAPIClientID' => null,
    'type' => 'pat',
    'name' => $name,
    'tokenPrefix' => substr($plain, 0, 16),
    'tokenHash' => hash('sha256', $plain),
    'expiresAt' => $expiresAt,
]);

$session->set('apiTokenOnce', $plain);

$URL = $session->get('absoluteURL').'/index.php?q=/modules/API/tokens_manage.php&return=success0';
header("Location: {$URL}");
