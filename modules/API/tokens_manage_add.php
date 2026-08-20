<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

use Gibbon\Domain\System\SettingGateway;
use Gibbon\Domain\User\RoleGateway;
use Gibbon\Forms\Form;

if (isActionAccessible($guid, $connection2, '/modules/API/tokens_manage_add.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs
        ->add(__('Manage API Tokens'), 'tokens_manage.php')
        ->add(__('Add Token'));

    $roleGateway = $container->get(RoleGateway::class);
    $roles = $roleGateway->selectAllRolesByPerson($session->get('gibbonPersonID'))->fetchAll();
    $roleOptions = [];
    foreach ($roles as $role) {
        $roleOptions[$role['gibbonRoleID']] = __($role['name']);
    }

    if (empty($roleOptions)) {
        $page->addError(__('You do not have any roles available to lock to a token.'));
        return;
    }

    $expiryDays = (int) $container->get(SettingGateway::class)->getSettingByScope('API', 'tokenExpiryDays');

    $form = Form::create('addToken', $session->get('absoluteURL').'/modules/'.$session->get('module').'/tokens_manage_addProcess.php');
    $form->addHiddenValue('address', $session->get('address'));

    $row = $form->addRow();
        $row->addLabel('name', __('Name'))->description(__('A label you will recognise later, for example Cursor – Teacher.'));
        $row->addTextField('name')->required()->maxLength(100);

    $row = $form->addRow();
        $row->addLabel('gibbonRoleID', __('Role'))->description(__('This token can only use the selected role. Create another token for a different role.'));
        $row->addSelect('gibbonRoleID')
            ->fromArray($roleOptions)
            ->required()
            ->placeholder()
            ->selected($session->get('gibbonRoleIDCurrent'));

    $row = $form->addRow();
        $row->addLabel('expiresInDays', __('Expires In (Days)'))->description(__('Use 0 for no expiry. Default comes from API settings.'));
        $row->addNumber('expiresInDays')->required()->minimum(0)->maximum(3650)->setValue($expiryDays);

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();
}
