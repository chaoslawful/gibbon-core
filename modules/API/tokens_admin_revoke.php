<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

use Gibbon\Forms\Prefab\DeleteForm;
use Gibbon\Module\API\Domain\APITokenGateway;

if (isActionAccessible($guid, $connection2, '/modules/API/tokens_admin_revoke.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $gibbonAPITokenID = $_GET['gibbonAPITokenID'] ?? '';
    $token = $container->get(APITokenGateway::class)->getByID($gibbonAPITokenID);

    $page->breadcrumbs
        ->add(__('API Settings'), 'settings.php')
        ->add(__('All API Tokens'), 'tokens_admin.php')
        ->add(__('Revoke Token'));

    if (empty($token)) {
        $page->addError(__('The specified record cannot be found.'));
    } else {
        $form = DeleteForm::createForm($session->get('absoluteURL').'/modules/'.$session->get('module').'/tokens_admin_revokeProcess.php');
        $form->addHiddenValue('gibbonAPITokenID', $gibbonAPITokenID);
        echo $form->getOutput();
    }
}
