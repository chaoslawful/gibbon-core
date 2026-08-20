<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

namespace Gibbon\Module\API\Controllers;

use Gibbon\Contracts\Services\Session;
use Gibbon\Module\API\Http\Json;
use Gibbon\Module\API\OpenApi\Spec;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class OpenApiController
{
    protected Session $session;

    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    public function show(Request $request, Response $response): Response
    {
        $base = rtrim((string) $this->session->get('absoluteURL'), '/');

        return Json::write($response, Spec::document($base));
    }
}
