<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

namespace Gibbon\Module\API\Domain;

use Gibbon\Domain\QueryableGateway;
use Gibbon\Domain\Traits\TableAware;

class APIAuditLogGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonAPIAuditLog';
    private static $primaryKey = 'gibbonAPIAuditLogID';
    private static $searchableColumns = [];
}
