<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

namespace Gibbon\Module\API\Auth;

use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\API\Domain\APITokenGateway;
use Gibbon\Module\API\Http\ApiException;

class PersonalAccessTokenAuthenticator
{
    protected APITokenGateway $tokenGateway;
    protected SettingGateway $settingGateway;

    public function __construct(APITokenGateway $tokenGateway, SettingGateway $settingGateway)
    {
        $this->tokenGateway = $tokenGateway;
        $this->settingGateway = $settingGateway;
    }

    public function authenticate(?string $header): array
    {
        $enabled = $this->settingGateway->getSettingByScope('API', 'apiEnabled');
        if ($enabled !== 'Y') {
            throw new ApiException('API is disabled.', 503);
        }

        if (empty($header) || stripos($header, 'Bearer ') !== 0) {
            throw new ApiException('Missing or invalid Authorization header. Use Bearer <token>.', 401);
        }

        $plain = trim(substr($header, 7));
        if ($plain === '' || strpos($plain, 'gib_pat_') !== 0) {
            throw new ApiException('Invalid access token.', 401);
        }

        $row = $this->tokenGateway->getActiveByHash(hash('sha256', $plain));
        if (empty($row)) {
            throw new ApiException('Invalid access token.', 401);
        }

        if (!empty($row['revokedAt'])) {
            throw new ApiException('This token has been revoked.', 401);
        }

        if (!empty($row['expiresAt']) && strtotime($row['expiresAt']) < time()) {
            throw new ApiException('This token has expired.', 401);
        }

        if (($row['personStatus'] ?? '') !== 'Full' || ($row['canLogin'] ?? '') !== 'Y') {
            throw new ApiException('This user cannot log in.', 401);
        }

        if (($row['canLoginRole'] ?? 'Y') !== 'Y') {
            throw new ApiException('This role cannot log in.', 401);
        }

        $roleIDs = array_filter(array_map('trim', explode(',', $row['gibbonRoleIDAll'] ?? '')));
        $lockedRole = str_pad((string) intval($row['gibbonRoleID']), 3, '0', STR_PAD_LEFT);
        $roleIDs = array_map(function ($id) {
            return str_pad((string) intval($id), 3, '0', STR_PAD_LEFT);
        }, $roleIDs);

        if (!in_array($lockedRole, $roleIDs, true)) {
            throw new ApiException('The locked role is no longer assigned to this user.', 401);
        }

        $limit = (int) $this->settingGateway->getSettingByScope('API', 'rateLimitPerMinute');
        if ($limit > 0) {
            $count = $this->tokenGateway->countRecentRequests($row['gibbonAPITokenID'], 60);
            if ($count >= $limit) {
                throw new ApiException('Rate limit exceeded.', 429);
            }
        }

        $this->tokenGateway->markUsed($row['gibbonAPITokenID']);

        return $row;
    }
}
