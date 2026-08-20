<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

use Gibbon\Module\API\Domain\APITokenGateway;

include '../../gibbon.php';

$container->get(\Gibbon\Services\ModuleLoader::class)->registerModuleNamespace('API');

$gibbonAPITokenID = $_POST['gibbonAPITokenID'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/API/tokens_admin_revoke.php&gibbonAPITokenID='.$gibbonAPITokenID;
$URLDelete = $session->get('absoluteURL').'/index.php?q=/modules/API/tokens_admin.php';

if (isActionAccessible($guid, $connection2, '/modules/API/tokens_admin_revoke.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
}

$tokenGateway = $container->get(APITokenGateway::class);
$token = $tokenGateway->getByID($gibbonAPITokenID);
if (empty($token)) {
    $URL .= '&return=error2';
    header("Location: {$URL}");
    exit;
}

$tokenGateway->revoke($gibbonAPITokenID);

header("Location: {$URLDelete}&return=success0");
