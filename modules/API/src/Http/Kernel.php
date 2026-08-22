<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

namespace Gibbon\Module\API\Http;

use Gibbon\Contracts\Services\Session;
use Gibbon\Module\API\Controllers\CourseController;
use Gibbon\Module\API\Controllers\MeController;
use Gibbon\Module\API\Controllers\OpenApiController;
use Gibbon\Module\API\Controllers\PlannerCoverageController;
use Gibbon\Module\API\Controllers\PlannerLessonController;
use Gibbon\Module\API\Controllers\TimetableController;
use Gibbon\Module\API\Controllers\TimetableSlotController;
use Gibbon\Module\API\Http\Middleware\AuditMiddleware;
use Gibbon\Module\API\Http\Middleware\AuthMiddleware;
use Gibbon\Services\ModuleLoader;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\ServerRequest;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Factory\AppFactory;
use Slim\App;

class Kernel
{
    protected ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function run(): void
    {
        $httpFactory = new HttpFactory();
        AppFactory::setResponseFactory($httpFactory);
        $app = AppFactory::create($httpFactory);

        $app->addBodyParsingMiddleware();
        $this->registerRoutes($app);
        $app->add($this->container->get(AuditMiddleware::class));
        $app->add($this->container->get(AuthMiddleware::class));
        $this->registerErrorHandler($app);

        $app->run($this->normalizeRequest());
    }

    protected function normalizeRequest(): ServerRequestInterface
    {
        $request = ServerRequest::fromGlobals();
        $path = $request->getUri()->getPath();
        $pathInfo = $_SERVER['PATH_INFO'] ?? '';

        if ($pathInfo !== '' && strpos($path, $pathInfo) === false) {
            $path = rtrim($path, '/').$pathInfo;
        }

        if (preg_match('#/api\.php(/.*)?$#', $path, $matches)) {
            $apiPath = $matches[1] ?? '/';
        } elseif (preg_match('#/api(/.*)$#', $path, $matches)) {
            $apiPath = $matches[1];
        } else {
            $apiPath = $path;
        }

        if ($apiPath === '') {
            $apiPath = '/';
        }

        return $request->withUri($request->getUri()->withPath($apiPath));
    }

    protected function registerRoutes(App $app): void
    {
        $c = $this->container;
        $c->get(ModuleLoader::class)->registerModuleNamespace('Attendance');

        $app->get('/v1/openapi.json', function ($request, $response) use ($c) {
            return $c->get(OpenApiController::class)->show($request, $response);
        });
        $app->get('/v1/me', function ($request, $response) use ($c) {
            return $c->get(MeController::class)->show($request, $response);
        });
        $app->get('/v1/school-year', function ($request, $response) use ($c) {
            return $c->get(CourseController::class)->schoolYear($request, $response);
        });
        $app->get('/v1/courses', function ($request, $response) use ($c) {
            return $c->get(CourseController::class)->courses($request, $response);
        });
        $app->get('/v1/classes', function ($request, $response) use ($c) {
            return $c->get(CourseController::class)->classes($request, $response);
        });
        $app->get('/v1/spaces', function ($request, $response) use ($c) {
            return $c->get(CourseController::class)->spaces($request, $response);
        });

        $app->get('/v1/planner/lessons', function ($request, $response) use ($c) {
            return $c->get(PlannerLessonController::class)->index($request, $response);
        });
        $app->post('/v1/planner/lessons', function ($request, $response) use ($c) {
            return $c->get(PlannerLessonController::class)->store($request, $response);
        });
        $app->get('/v1/planner/lessons/{id}', function ($request, $response, $args) use ($c) {
            return $c->get(PlannerLessonController::class)->show($request, $response, $args);
        });
        $app->patch('/v1/planner/lessons/{id}', function ($request, $response, $args) use ($c) {
            return $c->get(PlannerLessonController::class)->update($request, $response, $args);
        });
        $app->delete('/v1/planner/lessons/{id}', function ($request, $response, $args) use ($c) {
            return $c->get(PlannerLessonController::class)->destroy($request, $response, $args);
        });
        $app->get('/v1/planner/classes/{classId}/slots', function ($request, $response, $args) use ($c) {
            return $c->get(PlannerCoverageController::class)->slots($request, $response, $args);
        });
        $app->get('/v1/planner/classes/{classId}/coverage', function ($request, $response, $args) use ($c) {
            return $c->get(PlannerCoverageController::class)->coverage($request, $response, $args);
        });
        $app->get('/v1/planner/units', function ($request, $response) use ($c) {
            return $c->get(PlannerLessonController::class)->units($request, $response);
        });

        $app->get('/v1/timetables', function ($request, $response) use ($c) {
            return $c->get(TimetableController::class)->index($request, $response);
        });
        $app->get('/v1/timetables/{id}', function ($request, $response, $args) use ($c) {
            return $c->get(TimetableController::class)->show($request, $response, $args);
        });
        $app->get('/v1/timetables/{id}/days', function ($request, $response, $args) use ($c) {
            return $c->get(TimetableController::class)->days($request, $response, $args);
        });
        $app->get('/v1/timetables/{id}/days/{dayId}/rows', function ($request, $response, $args) use ($c) {
            return $c->get(TimetableController::class)->rows($request, $response, $args);
        });
        $app->get('/v1/timetables/{id}/days/{dayId}/slots', function ($request, $response, $args) use ($c) {
            return $c->get(TimetableSlotController::class)->index($request, $response, $args);
        });
        $app->post('/v1/timetables/{id}/days/{dayId}/slots', function ($request, $response, $args) use ($c) {
            return $c->get(TimetableSlotController::class)->store($request, $response, $args);
        });
        $app->get('/v1/timetables/{id}/dates', function ($request, $response, $args) use ($c) {
            return $c->get(TimetableController::class)->dates($request, $response, $args);
        });
        $app->patch('/v1/timetable-slots/{id}', function ($request, $response, $args) use ($c) {
            return $c->get(TimetableSlotController::class)->update($request, $response, $args);
        });
        $app->delete('/v1/timetable-slots/{id}', function ($request, $response, $args) use ($c) {
            return $c->get(TimetableSlotController::class)->destroy($request, $response, $args);
        });

        RouteMap::register($app, $c);
    }

    protected function registerErrorHandler(App $app): void
    {
        $handler = new ErrorHandler(
            $app->getResponseFactory(),
            $this->container->get(Session::class)
        );
        $errorMiddleware = $app->addErrorMiddleware(true, true, true);
        $errorMiddleware->setDefaultErrorHandler($handler);
        $errorMiddleware->setErrorHandler(HttpNotFoundException::class, $handler);
        $errorMiddleware->setErrorHandler(HttpMethodNotAllowedException::class, $handler);
    }
}
