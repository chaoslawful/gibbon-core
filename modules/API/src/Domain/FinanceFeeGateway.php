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

class FinanceFeeGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonFinanceFee';
    private static $primaryKey = 'gibbonFinanceFeeID';
    private static $searchableColumns = ['name', 'nameShort'];
}
