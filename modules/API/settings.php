<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

use Gibbon\Domain\System\SettingGateway;
use Gibbon\Forms\Form;
use Gibbon\Http\Url;

if (isActionAccessible($guid, $connection2, '/modules/API/settings.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs->add(__('API Settings'));

    $settingGateway = $container->get(SettingGateway::class);

    echo '<p>'.__('Control whether external agents can call the REST API, and the default lifetime of new personal access tokens.').'</p>';
    echo '<p><a href="'.Url::fromModuleRoute('API', 'tokens_admin').'">'.__('View and revoke all tokens').'</a></p>';

    $form = Form::create('apiSettings', $session->get('absoluteURL').'/modules/'.$session->get('module').'/settingsProcess.php');
    $form->addHiddenValue('address', $session->get('address'));

    $setting = $settingGateway->getSettingByScope('API', 'apiEnabled', true);
    $row = $form->addRow();
        $row->addLabel('apiEnabled', __($setting['nameDisplay']))->description(__($setting['description']));
        $row->addYesNo('apiEnabled')->required()->selected($setting['value']);

    $setting = $settingGateway->getSettingByScope('API', 'tokenExpiryDays', true);
    $row = $form->addRow();
        $row->addLabel('tokenExpiryDays', __($setting['nameDisplay']))->description(__($setting['description']));
        $row->addNumber('tokenExpiryDays')->required()->minimum(0)->maximum(3650)->setValue($setting['value']);

    $setting = $settingGateway->getSettingByScope('API', 'rateLimitPerMinute', true);
    $row = $form->addRow();
        $row->addLabel('rateLimitPerMinute', __($setting['nameDisplay']))->description(__($setting['description']));
        $row->addNumber('rateLimitPerMinute')->required()->minimum(0)->maximum(10000)->setValue($setting['value']);

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();
}
