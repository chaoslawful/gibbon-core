<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

namespace Gibbon\Module\API\Domain;

use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;
use Gibbon\Domain\Traits\TableAware;

class APITokenGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonAPIToken';
    private static $primaryKey = 'gibbonAPITokenID';
    private static $searchableColumns = ['gibbonAPIToken.name', 'gibbonPerson.username', 'gibbonPerson.surname', 'gibbonPerson.preferredName'];

    public function getActiveByHash(string $tokenHash): array
    {
        $sql = "SELECT gibbonAPIToken.*, gibbonPerson.status AS personStatus, gibbonPerson.canLogin,
                       gibbonPerson.gibbonRoleIDPrimary, gibbonPerson.gibbonRoleIDAll, gibbonPerson.username,
                       gibbonPerson.surname, gibbonPerson.preferredName, gibbonRole.name AS roleName,
                       gibbonRole.category AS roleCategory, gibbonRole.canLoginRole
                FROM gibbonAPIToken
                JOIN gibbonPerson ON (gibbonPerson.gibbonPersonID=gibbonAPIToken.gibbonPersonID)
                JOIN gibbonRole ON (gibbonRole.gibbonRoleID=gibbonAPIToken.gibbonRoleID)
                WHERE gibbonAPIToken.tokenHash=:tokenHash
                LIMIT 1";

        $row = $this->db()->selectOne($sql, ['tokenHash' => $tokenHash]);

        return is_array($row) ? $row : [];
    }

    public function queryTokensByPerson(QueryCriteria $criteria, $gibbonPersonID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonAPIToken.gibbonAPITokenID',
                'gibbonAPIToken.name',
                'gibbonAPIToken.type',
                'gibbonAPIToken.tokenPrefix',
                'gibbonAPIToken.expiresAt',
                'gibbonAPIToken.lastUsedAt',
                'gibbonAPIToken.revokedAt',
                'gibbonAPIToken.timestampCreated',
                'gibbonRole.gibbonRoleID',
                'gibbonRole.name AS roleName',
            ])
            ->innerJoin('gibbonRole', 'gibbonRole.gibbonRoleID=gibbonAPIToken.gibbonRoleID')
            ->where('gibbonAPIToken.gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID);

        return $this->runQuery($query, $criteria);
    }

    public function queryAllTokens(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonAPIToken.gibbonAPITokenID',
                'gibbonAPIToken.name',
                'gibbonAPIToken.type',
                'gibbonAPIToken.tokenPrefix',
                'gibbonAPIToken.expiresAt',
                'gibbonAPIToken.lastUsedAt',
                'gibbonAPIToken.revokedAt',
                'gibbonAPIToken.timestampCreated',
                'gibbonPerson.gibbonPersonID',
                'gibbonPerson.surname',
                'gibbonPerson.preferredName',
                'gibbonPerson.username',
                'gibbonRole.name AS roleName',
            ])
            ->innerJoin('gibbonPerson', 'gibbonPerson.gibbonPersonID=gibbonAPIToken.gibbonPersonID')
            ->innerJoin('gibbonRole', 'gibbonRole.gibbonRoleID=gibbonAPIToken.gibbonRoleID');

        return $this->runQuery($query, $criteria);
    }

    public function markUsed($gibbonAPITokenID)
    {
        $sql = "UPDATE gibbonAPIToken SET lastUsedAt=:lastUsedAt WHERE gibbonAPITokenID=:gibbonAPITokenID";
        return $this->db()->update($sql, [
            'lastUsedAt' => date('Y-m-d H:i:s'),
            'gibbonAPITokenID' => $gibbonAPITokenID,
        ]);
    }

    public function revoke($gibbonAPITokenID)
    {
        $sql = "UPDATE gibbonAPIToken SET revokedAt=:revokedAt WHERE gibbonAPITokenID=:gibbonAPITokenID AND revokedAt IS NULL";
        return $this->db()->update($sql, [
            'revokedAt' => date('Y-m-d H:i:s'),
            'gibbonAPITokenID' => $gibbonAPITokenID,
        ]);
    }

    public function countRecentRequests($gibbonAPITokenID, int $seconds): int
    {
        $sql = "SELECT COUNT(*) FROM gibbonAPIAuditLog
                WHERE gibbonAPITokenID=:gibbonAPITokenID
                AND timestamp >= :since";

        return (int) $this->db()->selectOne($sql, [
            'gibbonAPITokenID' => $gibbonAPITokenID,
            'since' => date('Y-m-d H:i:s', time() - $seconds),
        ]);
    }
}
