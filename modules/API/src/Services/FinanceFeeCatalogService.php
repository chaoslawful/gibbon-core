<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

namespace Gibbon\Module\API\Services;

use Gibbon\Contracts\Database\Connection;
use Gibbon\Contracts\Services\Session;
use Gibbon\Domain\Finance\FinanceFeeCategoryGateway;
use Gibbon\Domain\Finance\FinanceGateway;
use Gibbon\Module\API\Auth\PermissionMapper;
use Gibbon\Module\API\Domain\FinanceFeeGateway;
use Gibbon\Module\API\Http\ApiException;
use Gibbon\Module\API\Support\RestTable;

class FinanceFeeCatalogService
{
    public function __construct(
        protected PermissionMapper $permissions,
        protected Connection $db,
        protected Session $session,
        protected FinanceFeeCategoryGateway $categories,
        protected FinanceFeeGateway $fees,
        protected FinanceGateway $finance
    ) {
    }

    public function listCategories(): array
    {
        $this->permissions->assertCanManageFeeCategories();

        return $this->categories->selectBy([])->fetchAll();
    }

    public function getCategory(string $id): array
    {
        $this->permissions->assertCanManageFeeCategories();

        return RestTable::requireRow($this->categories, $id, 'Fee category not found.');
    }

    public function createCategory(array $body): array
    {
        $this->permissions->assertCanManageFeeCategories();
        $data = RestTable::pick($body, ['name', 'nameShort', 'active', 'description']);
        RestTable::requireFields($data, ['name', 'nameShort', 'active']);
        $data = RestTable::defaults($data, ['description' => '', 'active' => 'Y']);
        $data['gibbonPersonIDCreator'] = $this->session->get('gibbonPersonID');
        $data['timestampCreator'] = date('Y-m-d H:i:s');

        return RestTable::create($this->categories, $data);
    }

    public function updateCategory(string $id, array $body): array
    {
        $this->permissions->assertCanManageFeeCategories();
        $this->assertEditableCategory($id);
        $data = RestTable::pick($body, ['name', 'nameShort', 'active', 'description']);
        $data['gibbonPersonIDUpdate'] = $this->session->get('gibbonPersonID');
        $data['timestampUpdate'] = date('Y-m-d H:i:s');

        return RestTable::update($this->categories, $id, $data, 'Fee category not found.');
    }

    public function deleteCategory(string $id): void
    {
        $this->permissions->assertCanManageFeeCategories();
        $this->assertEditableCategory($id);
        RestTable::requireRow($this->categories, $id, 'Fee category not found.');
        $this->fees->updateWhere(
            ['gibbonFinanceFeeCategoryID' => $id],
            ['gibbonFinanceFeeCategoryID' => '0001']
        );
        $this->db->update(
            'UPDATE gibbonFinanceInvoiceFee SET gibbonFinanceFeeCategoryID=1 WHERE gibbonFinanceFeeCategoryID=:id',
            ['id' => $id]
        );
        RestTable::delete($this->categories, $id, 'Fee category not found.');
    }

    public function listFees(array $query): array
    {
        $this->permissions->assertCanManageFees();
        $yearId = $query['gibbonSchoolYearID'] ?? '';
        if ($yearId === '') {
            throw new ApiException('gibbonSchoolYearID is required.', 422);
        }
        $criteria = $this->finance->newQueryCriteria()->sortBy('name')->pageSize(0);
        $criteria->filterBy('gibbonSchoolYearID', $yearId);
        if (!empty($query['active'])) {
            $criteria->filterBy('status', $query['active']);
        }
        if (!empty($query['search'])) {
            $criteria->filterBy('search', $query['search']);
        }

        return $this->finance->queryFees($criteria)->toArray();
    }

    public function getFee(string $id): array
    {
        $this->permissions->assertCanManageFees();
        $row = RestTable::requireRow($this->fees, $id, 'Fee not found.');
        $category = $this->categories->getByID($row['gibbonFinanceFeeCategoryID']);
        $row['category'] = $category['name'] ?? null;

        return $row;
    }

    public function createFee(array $body): array
    {
        $this->permissions->assertCanManageFees();
        $data = RestTable::pick($body, [
            'gibbonSchoolYearID', 'name', 'nameShort', 'active', 'description',
            'gibbonFinanceFeeCategoryID', 'fee',
        ]);
        $data['gibbonSchoolYearID'] = $data['gibbonSchoolYearID'] ?? $this->session->get('gibbonSchoolYearID');
        RestTable::requireFields($data, ['gibbonSchoolYearID', 'name', 'nameShort', 'active', 'gibbonFinanceFeeCategoryID', 'fee']);
        RestTable::requireRow($this->categories, $data['gibbonFinanceFeeCategoryID'], 'Fee category not found.');
        $data = RestTable::defaults($data, ['description' => '', 'active' => 'Y']);
        $data['gibbonPersonIDCreator'] = $this->session->get('gibbonPersonID');
        $data['timestampCreator'] = date('Y-m-d H:i:s');

        return $this->getFee(RestTable::create($this->fees, $data)['gibbonFinanceFeeID']);
    }

    public function updateFee(string $id, array $body): array
    {
        $this->permissions->assertCanManageFees();
        RestTable::requireRow($this->fees, $id, 'Fee not found.');
        $data = RestTable::pick($body, [
            'gibbonSchoolYearID', 'name', 'nameShort', 'active', 'description',
            'gibbonFinanceFeeCategoryID', 'fee',
        ]);
        if (isset($data['gibbonFinanceFeeCategoryID'])) {
            RestTable::requireRow($this->categories, $data['gibbonFinanceFeeCategoryID'], 'Fee category not found.');
        }
        $data['gibbonPersonIDUpdate'] = $this->session->get('gibbonPersonID');
        $data['timestampUpdate'] = date('Y-m-d H:i:s');
        RestTable::update($this->fees, $id, $data, 'Fee not found.');

        return $this->getFee($id);
    }

    protected function assertEditableCategory(string $id): void
    {
        if ((int) $id === 1) {
            throw new ApiException('The built-in Other category cannot be edited or deleted.', 422);
        }
    }
}
