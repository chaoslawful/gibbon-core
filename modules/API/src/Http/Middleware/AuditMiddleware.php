<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

namespace Gibbon\Module\API\Http\Middleware;

use Gibbon\Module\API\Domain\APIAuditLogGateway;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AuditMiddleware implements MiddlewareInterface
{
    protected APIAuditLogGateway $auditGateway;

    public function __construct(APIAuditLogGateway $auditGateway)
    {
        $this->auditGateway = $auditGateway;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        $token = $request->getAttribute('apiToken') ?? [];
        try {
            $this->auditGateway->insert([
                'gibbonAPITokenID' => $token['gibbonAPITokenID'] ?? null,
                'gibbonPersonID' => $token['gibbonPersonID'] ?? null,
                'method' => $request->getMethod(),
                'path' => $request->getUri()->getPath(),
                'statusCode' => $response->getStatusCode(),
                'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (\Throwable $e) {
            // Never fail the request because audit logging failed.
        }

        return $response;
    }
}
