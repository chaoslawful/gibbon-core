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

if (isActionAccessible($guid, $connection2, '/modules/API/tokens_admin.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs
        ->add(__('API Settings'), 'settings.php')
        ->add(__('All API Tokens'));

    $tokenGateway = $container->get(APITokenGateway::class);
    $criteria = $tokenGateway->newQueryCriteria(true)
        ->sortBy('timestampCreated', 'DESC')
        ->fromPOST();

    $tokens = $tokenGateway->queryAllTokens($criteria);

    $table = DataTable::createPaginated('apiTokensAdmin', $criteria);
    $table->addColumn('preferredName', __('User'))->format(function ($row) {
        return Format::name('', $row['preferredName'], $row['surname'], 'Staff', false, true).' ('.$row['username'].')';
    });
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
                    ->setURL('/modules/API/tokens_admin_revoke.php');
            }
        });

    echo $table->render($tokens);
}
