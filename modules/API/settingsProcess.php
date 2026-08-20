<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

use Gibbon\Data\Validator;
use Gibbon\Domain\System\SettingGateway;

include '../../gibbon.php';

$container->get(\Gibbon\Services\ModuleLoader::class)->registerModuleNamespace('API');

$_POST = $container->get(Validator::class)->sanitize($_POST);

$URL = $session->get('absoluteURL').'/index.php?q=/modules/API/settings.php';

if (isActionAccessible($guid, $connection2, '/modules/API/settings.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
}

$apiEnabled = $_POST['apiEnabled'] ?? 'N';
$tokenExpiryDays = $_POST['tokenExpiryDays'] ?? '90';
$rateLimitPerMinute = $_POST['rateLimitPerMinute'] ?? '120';

if (!in_array($apiEnabled, ['Y', 'N'], true) || !is_numeric($tokenExpiryDays) || !is_numeric($rateLimitPerMinute)) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
}

$settingGateway = $container->get(SettingGateway::class);
$settingGateway->updateSettingByScope('API', 'apiEnabled', $apiEnabled);
$settingGateway->updateSettingByScope('API', 'tokenExpiryDays', (string) max(0, (int) $tokenExpiryDays));
$settingGateway->updateSettingByScope('API', 'rateLimitPerMinute', (string) max(0, (int) $rateLimitPerMinute));

$URL .= '&return=success0';
header("Location: {$URL}");
