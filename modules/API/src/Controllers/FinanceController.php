<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

namespace Gibbon\Module\API\Controllers;

use Gibbon\Module\API\Http\Json;
use Gibbon\Module\API\Services\FinanceAdminService;
use Gibbon\Module\API\Services\FinanceExpenseService;
use Gibbon\Module\API\Services\FinanceFeeCatalogService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class FinanceController
{
    public function __construct(
        protected FinanceAdminService $admin,
        protected FinanceExpenseService $expenses,
        protected FinanceFeeCatalogService $fees
    ) {
    }

    protected function body(Request $request): array
    {
        $body = $request->getParsedBody();

        return is_array($body) ? $body : [];
    }

    public function cycles(Request $request, Response $response): Response
    {
        return Json::write($response, ['data' => $this->admin->listCycles()]);
    }

    public function storeCycle(Request $request, Response $response): Response
    {
        return Json::write($response, $this->admin->createCycle($this->body($request)), 201);
    }

    public function showCycle(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->admin->getCycle($args['id']));
    }

    public function updateCycle(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->admin->updateCycle($args['id'], $this->body($request)));
    }

    public function destroyCycle(Request $request, Response $response, array $args): Response
    {
        $this->admin->deleteCycle($args['id']);

        return Json::empty($response);
    }

    public function allocations(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, ['data' => $this->admin->listAllocations($args['id'])]);
    }

    public function saveAllocations(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->admin->saveAllocations($args['id'], $this->body($request)));
    }

    public function budgets(Request $request, Response $response): Response
    {
        return Json::write($response, ['data' => $this->admin->listBudgets()]);
    }

    public function showBudget(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->admin->getBudget($args['id']));
    }

    public function storeBudget(Request $request, Response $response): Response
    {
        return Json::write($response, $this->admin->createBudget($this->body($request)), 201);
    }

    public function updateBudget(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->admin->updateBudget($args['id'], $this->body($request)));
    }

    public function destroyBudget(Request $request, Response $response, array $args): Response
    {
        $this->admin->deleteBudget($args['id']);

        return Json::empty($response);
    }

    public function storeBudgetStaff(Request $request, Response $response, array $args): Response
    {
        $body = $this->body($request);

        return Json::write($response, $this->admin->addBudgetStaff($args['id'], (string) ($body['gibbonPersonID'] ?? ''), (string) ($body['access'] ?? 'Read')), 201);
    }

    public function destroyBudgetStaff(Request $request, Response $response, array $args): Response
    {
        $this->admin->removeBudgetStaff($args['id']);

        return Json::empty($response);
    }

    public function approvers(Request $request, Response $response): Response
    {
        return Json::write($response, ['data' => $this->admin->listApprovers()]);
    }

    public function storeApprover(Request $request, Response $response): Response
    {
        return Json::write($response, $this->admin->createApprover($this->body($request)), 201);
    }

    public function updateApprover(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->admin->updateApprover($args['id'], $this->body($request)));
    }

    public function destroyApprover(Request $request, Response $response, array $args): Response
    {
        $this->admin->deleteApprover($args['id']);

        return Json::empty($response);
    }

    public function expenses(Request $request, Response $response): Response
    {
        return Json::write($response, $this->expenses->list($request->getQueryParams()));
    }

    public function showExpense(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->expenses->get($args['id']));
    }

    public function storeExpense(Request $request, Response $response): Response
    {
        return Json::write($response, $this->expenses->create($this->body($request)), 201);
    }

    public function approveExpense(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->expenses->approve($args['id'], $this->body($request)));
    }

    public function reimburseExpense(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->expenses->reimburse($args['id'], $this->body($request)));
    }

    public function printExpense(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->expenses->printPayload($args['id']));
    }

    public function pettyCash(Request $request, Response $response): Response
    {
        return Json::write($response, ['data' => $this->admin->listPettyCash($request->getQueryParams())]);
    }

    public function storePettyCash(Request $request, Response $response): Response
    {
        return Json::write($response, $this->admin->createPettyCash($this->body($request)), 201);
    }

    public function updatePettyCash(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->admin->updatePettyCash($args['id'], $this->body($request)));
    }

    public function destroyPettyCash(Request $request, Response $response, array $args): Response
    {
        $this->admin->deletePettyCash($args['id']);

        return Json::empty($response);
    }

    public function actionPettyCash(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->admin->actionPettyCash($args['id'], $this->body($request)));
    }

    public function feeCategories(Request $request, Response $response): Response
    {
        return Json::write($response, ['data' => $this->fees->listCategories()]);
    }

    public function showFeeCategory(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->fees->getCategory($args['id']));
    }

    public function storeFeeCategory(Request $request, Response $response): Response
    {
        return Json::write($response, $this->fees->createCategory($this->body($request)), 201);
    }

    public function updateFeeCategory(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->fees->updateCategory($args['id'], $this->body($request)));
    }

    public function destroyFeeCategory(Request $request, Response $response, array $args): Response
    {
        $this->fees->deleteCategory($args['id']);

        return Json::empty($response);
    }

    public function feeItems(Request $request, Response $response): Response
    {
        return Json::write($response, ['data' => $this->fees->listFees($request->getQueryParams())]);
    }

    public function showFee(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->fees->getFee($args['id']));
    }

    public function storeFee(Request $request, Response $response): Response
    {
        return Json::write($response, $this->fees->createFee($this->body($request)), 201);
    }

    public function updateFee(Request $request, Response $response, array $args): Response
    {
        return Json::write($response, $this->fees->updateFee($args['id'], $this->body($request)));
    }
}
