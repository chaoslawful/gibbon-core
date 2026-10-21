<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

use Gibbon\Data\Validator;

require_once '../../gibbon.php';
require_once __DIR__.'/moduleFunctions.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$address = $_POST['address'] ?? '';
$URL = $session->get('absoluteURL').'/index.php'.($address !== '' ? '?q='.urlencode($address) : '');
$withReturn = function ($code) use ($URL) {
    return $URL.(strpos($URL, '?') === false ? '?' : '&').'return='.$code;
};

if (isActionAccessible($guid, $connection2, '/modules/System Admin/update.php') == false) {
    header('Location: '.$withReturn('error0'));
    exit;
}

try {
    applyExpenseVoucherSchema($connection2);
} catch (PDOException $e) {
    $session->set('voucherSchemaError', $e->getMessage());
    header('Location: '.$withReturn('errorVoucherSchema'));
    exit;
}

header('Location: '.$withReturn('successVoucherSchema'));
