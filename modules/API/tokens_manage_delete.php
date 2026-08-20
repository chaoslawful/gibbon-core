<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

use Gibbon\Forms\Prefab\DeleteForm;
use Gibbon\Module\API\Domain\APITokenGateway;

if (isActionAccessible($guid, $connection2, '/modules/API/tokens_manage_delete.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $gibbonAPITokenID = $_GET['gibbonAPITokenID'] ?? '';
    $tokenGateway = $container->get(APITokenGateway::class);
    $token = $tokenGateway->getByID($gibbonAPITokenID);

    $page->breadcrumbs
        ->add(__('Manage API Tokens'), 'tokens_manage.php')
        ->add(__('Revoke Token'));

    if (empty($token) || $token['gibbonPersonID'] != $session->get('gibbonPersonID')) {
        $page->addError(__('The specified record cannot be found.'));
    } else {
        $form = DeleteForm::createForm($session->get('absoluteURL').'/modules/'.$session->get('module').'/tokens_manage_deleteProcess.php');
        $form->addHiddenValue('gibbonAPITokenID', $gibbonAPITokenID);
        echo $form->getOutput();
    }
}
