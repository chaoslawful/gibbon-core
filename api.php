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

/**
 * REST API front controller. Authenticates via Bearer PAT, not browser cookies.
 */
define('GIBBON_API', true);
ini_set('session.use_cookies', '0');
ini_set('session.use_only_cookies', '0');

require_once __DIR__.'/gibbon.php';

use Gibbon\Services\ModuleLoader;
use Gibbon\Module\API\Http\Kernel;

header('X-Content-Type-Options: nosniff');

$container->get(ModuleLoader::class)->registerModuleNamespace('API');

(new Kernel($container))->run();
