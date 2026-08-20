<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

namespace Gibbon\Module\API\Auth;

use Gibbon\Contracts\Services\Session;
use Gibbon\Domain\User\RoleGateway;
use Gibbon\Domain\User\UserGateway;
use Gibbon\Module\API\Http\ApiException;

class SessionImpersonator
{
    protected Session $session;
    protected UserGateway $userGateway;
    protected RoleGateway $roleGateway;

    public function __construct(Session $session, UserGateway $userGateway, RoleGateway $roleGateway)
    {
        $this->session = $session;
        $this->userGateway = $userGateway;
        $this->roleGateway = $roleGateway;
    }

    public function impersonate(array $tokenRow): void
    {
        $safe = $this->userGateway->getSafeUserData($tokenRow['gibbonPersonID']);
        if (empty($safe)) {
            throw new ApiException('User not found.', 401);
        }

        $this->session->set($safe);
        $this->session->set('gibbonRoleIDPrimary', $tokenRow['gibbonRoleIDPrimary']);
        $this->session->set('gibbonRoleIDCurrent', $tokenRow['gibbonRoleID']);
        $this->session->set('gibbonRoleIDCurrentCategory', $tokenRow['roleCategory']);
        $this->session->set('gibbonRoleIDAll', $this->roleGateway->selectRoleListByIDs($tokenRow['gibbonRoleIDAll'])->fetchAll());
        $this->session->set('gibbonAPITokenID', $tokenRow['gibbonAPITokenID']);

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }
}
