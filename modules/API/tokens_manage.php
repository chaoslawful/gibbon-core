<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

use Gibbon\Module\API\Domain\APITokenGateway;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;

if (isActionAccessible($guid, $connection2, '/modules/API/tokens_manage.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs->add(__('Manage API Tokens'));

    $page->addWarning(__('Treat personal access tokens like passwords. They let an agent act as you with the role you select. Copy a new token immediately; Gibbon will not show it again.'));

    if ($session->has('apiTokenOnce')) {
        $tokenOnce = $session->get('apiTokenOnce');
        $page->addSuccess(__('Your new token is: {token}', ['token' => '<code>'.htmlPrep($tokenOnce).'</code>']).'<br/>'.__('Copy it now. It will not be shown again.'));
        $session->forget('apiTokenOnce');
    }

    $absoluteURL = $session->get('absoluteURL');
    $page->addMessage(__('REST endpoint: {url}', ['url' => '<code>'.$absoluteURL.'/api.php/v1</code>']).'<br/>'.__('OpenAPI document: {url}', ['url' => '<code>'.$absoluteURL.'/api.php/v1/openapi.json</code>']));

    $tokenGateway = $container->get(APITokenGateway::class);
    $criteria = $tokenGateway->newQueryCriteria(true)
        ->sortBy('timestampCreated', 'DESC')
        ->fromPOST();

    $tokens = $tokenGateway->queryTokensByPerson($criteria, $session->get('gibbonPersonID'));

    $table = DataTable::createPaginated('apiTokens', $criteria);
    $table->addHeaderAction('add', __('Add'))
        ->setURL('/modules/API/tokens_manage_add.php')
        ->displayLabel();

    $table->addColumn('name', __('Name'));
    $table->addColumn('roleName', __('Role'))->translatable();
    $table->addColumn('tokenPrefix', __('Token'))->format(function ($row) {
        return htmlPrep($row['tokenPrefix']).'…';
    });
    $table->addColumn('expiresAt', __('Expires'))->format(function ($row) {
        return empty($row['expiresAt']) ? __('Never') : Format::dateTime($row['expiresAt']);
    });
    $table->addColumn('lastUsedAt', __('Last Used'))->format(function ($row) {
        return empty($row['lastUsedAt']) ? __('Never') : Format::dateTime($row['lastUsedAt']);
    });
    $table->addColumn('status', __('Status'))->format(function ($row) {
        if (!empty($row['revokedAt'])) {
            return __('Revoked');
        }
        if (!empty($row['expiresAt']) && strtotime($row['expiresAt']) < time()) {
            return __('Expired');
        }
        return __('Active');
    });

    $table->addActionColumn()
        ->addParam('gibbonAPITokenID')
        ->format(function ($row, $actions) {
            if (empty($row['revokedAt'])) {
                $actions->addAction('delete', __('Revoke'))
                    ->setURL('/modules/API/tokens_manage_delete.php');
            }
        });

    echo $table->render($tokens);
}
