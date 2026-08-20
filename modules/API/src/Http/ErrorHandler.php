<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

namespace Gibbon\Module\API\Http;

use Gibbon\Contracts\Services\Session;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Throwable;

class ErrorHandler
{
    protected ResponseFactoryInterface $responseFactory;
    protected Session $session;

    public function __construct(ResponseFactoryInterface $responseFactory, Session $session)
    {
        $this->responseFactory = $responseFactory;
        $this->session = $session;
    }

    public function __invoke(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails
    ): ResponseInterface {
        $response = $this->responseFactory->createResponse();
        $path = $request->getUri()->getPath();
        $method = $request->getMethod();
        $verbose = $this->isVerbose();

        if ($exception instanceof ApiException) {
            $extra = [];
            if ($exception->getDetails()) {
                $extra['details'] = $exception->getDetails();
            }
            return Json::error($response, $exception->getMessage(), $exception->getCode() ?: 400, $extra);
        }

        if ($exception instanceof HttpNotFoundException) {
            $extra = ['type' => 'not_found', 'path' => $path, 'method' => $method];
            $hint = $this->hint($method, $path);
            if ($hint !== null) {
                $extra['hint'] = $hint;
            }
            return Json::error($response, "No route matches {$method} {$path}.", 404, $extra);
        }

        if ($exception instanceof HttpMethodNotAllowedException) {
            $allowed = method_exists($exception, 'getAllowedMethods') ? $exception->getAllowedMethods() : [];
            return Json::error($response, "Method {$method} not allowed for {$path}.", 405, [
                'type' => 'method_not_allowed',
                'path' => $path,
                'method' => $method,
                'allowed' => $allowed,
            ]);
        }

        $status = 500;
        if ($exception instanceof HttpException && method_exists($exception, 'getStatusCode')) {
            $status = (int) $exception->getStatusCode();
        }
        if ($status < 400 || $status > 599) {
            $status = 500;
        }

        $message = trim($exception->getMessage());
        if ($message === '') {
            $message = 'An unexpected error occurred.';
        }

        $extra = [
            'type' => (new \ReflectionClass($exception))->getShortName(),
        ];

        if ($verbose) {
            $extra['exception'] = get_class($exception);
            $extra['file'] = $exception->getFile();
            $extra['line'] = $exception->getLine();
        }

        return Json::error($response, $message, $status, $extra);
    }

    protected function isVerbose(): bool
    {
        $installType = (string) $this->session->get('installType');
        return $installType !== '' && $installType !== 'Production';
    }

    protected function hint(string $method, string $path): ?string
    {
        if ($method === 'GET' && preg_match('#^/v1/classes/([^/]+)/coverage/?$#', $path, $matches)) {
            return 'Use GET /v1/planner/classes/'.$matches[1].'/coverage';
        }
        if ($method === 'GET' && preg_match('#^/v1/classes/([^/]+)/slots/?$#', $path, $matches)) {
            return 'Use GET /v1/planner/classes/'.$matches[1].'/slots';
        }
        if ($method === 'GET' && preg_match('#^/v1/classes/([^/]+)/?$#', $path)) {
            return 'There is no class-detail route. List classes with GET /v1/classes.';
        }

        return 'See GET /v1/openapi.json for available routes.';
    }
}
