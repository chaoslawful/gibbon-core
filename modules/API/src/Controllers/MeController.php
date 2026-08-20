<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

namespace Gibbon\Module\API\Controllers;

use Gibbon\Contracts\Services\Session;
use Gibbon\Module\API\Auth\PermissionMapper;
use Gibbon\Module\API\Http\Json;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class MeController
{
    protected Session $session;
    protected PermissionMapper $permissions;

    public function __construct(Session $session, PermissionMapper $permissions)
    {
        $this->session = $session;
        $this->permissions = $permissions;
    }

    public function show(Request $request, Response $response): Response
    {
        $token = $request->getAttribute('apiToken') ?? [];

        return Json::write($response, [
            'gibbonPersonID' => $this->session->get('gibbonPersonID'),
            'username' => $this->session->get('username'),
            'preferredName' => $this->session->get('preferredName'),
            'surname' => $this->session->get('surname'),
            'gibbonRoleID' => $this->session->get('gibbonRoleIDCurrent'),
            'roleName' => $token['roleName'] ?? null,
            'roleCategory' => $this->session->get('gibbonRoleIDCurrentCategory'),
            'gibbonSchoolYearID' => $this->session->get('gibbonSchoolYearID'),
            'token' => [
                'gibbonAPITokenID' => $token['gibbonAPITokenID'] ?? null,
                'name' => $token['name'] ?? null,
                'type' => $token['type'] ?? 'pat',
                'expiresAt' => $token['expiresAt'] ?? null,
            ],
            'capabilities' => $this->permissions->capabilities(),
        ]);
    }
}
