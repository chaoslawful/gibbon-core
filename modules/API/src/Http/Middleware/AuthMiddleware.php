<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

namespace Gibbon\Module\API\Http\Middleware;

use Gibbon\Module\API\Auth\PersonalAccessTokenAuthenticator;
use Gibbon\Module\API\Auth\SessionImpersonator;
use Gibbon\Module\API\Http\ApiException;
use Gibbon\Module\API\Http\Json;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use GuzzleHttp\Psr7\HttpFactory;

class AuthMiddleware implements MiddlewareInterface
{
    protected PersonalAccessTokenAuthenticator $authenticator;
    protected SessionImpersonator $impersonator;

    public function __construct(PersonalAccessTokenAuthenticator $authenticator, SessionImpersonator $impersonator)
    {
        $this->authenticator = $authenticator;
        $this->impersonator = $impersonator;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->isPublic($request)) {
            return $handler->handle($request);
        }

        try {
            $token = $this->authenticator->authenticate($request->getHeaderLine('Authorization'));
            $this->impersonator->impersonate($token);
            $request = $request->withAttribute('apiToken', $token);
        } catch (ApiException $e) {
            return Json::error($this->newResponse(), $e->getMessage(), $e->getCode() ?: 401, $e->getDetails() ? ['details' => $e->getDetails()] : []);
        }

        return $handler->handle($request);
    }

    protected function isPublic(ServerRequestInterface $request): bool
    {
        if ($request->getMethod() !== 'GET') {
            return false;
        }

        $path = rtrim($request->getUri()->getPath(), '/') ?: '/';

        return $path === '/v1/openapi.json';
    }

    protected function newResponse(): ResponseInterface
    {
        return (new HttpFactory())->createResponse();
    }
}
