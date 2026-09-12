<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

$name        = 'API';
$description = 'Personal access tokens and a REST API so authorised agents can read and write Gibbon data using the signed-in user’s role permissions.';
$entryURL    = 'tokens_manage.php';
$type        = 'Additional';
$category    = 'Admin';
$version     = '1.3.06';
$author      = 'Gibbon Foundation';
$url         = 'https://gibbonedu.org';

$moduleTables[] = "CREATE TABLE `gibbonAPIClient` (
  `gibbonAPIClientID` int(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `clientID` varchar(64) NOT NULL,
  `clientSecretHash` varchar(128) NOT NULL,
  `redirectURI` text NOT NULL,
  `active` enum('Y','N') NOT NULL DEFAULT 'Y',
  `timestampCreated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`gibbonAPIClientID`),
  UNIQUE KEY `clientID` (`clientID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8";

$moduleTables[] = "CREATE TABLE `gibbonAPIAuthorizationCode` (
  `gibbonAPIAuthorizationCodeID` int(12) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
  `gibbonAPIClientID` int(10) UNSIGNED ZEROFILL NOT NULL,
  `gibbonPersonID` int(10) UNSIGNED ZEROFILL NOT NULL,
  `gibbonRoleID` int(3) UNSIGNED ZEROFILL NOT NULL,
  `codeHash` varchar(128) NOT NULL,
  `redirectURI` text NOT NULL,
  `expiresAt` datetime NOT NULL,
  `timestampCreated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`gibbonAPIAuthorizationCodeID`),
  KEY `codeHash` (`codeHash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8";

$moduleTables[] = "CREATE TABLE `gibbonAPIToken` (
  `gibbonAPITokenID` int(12) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
  `gibbonPersonID` int(10) UNSIGNED ZEROFILL NOT NULL,
  `gibbonRoleID` int(3) UNSIGNED ZEROFILL NOT NULL,
  `gibbonAPIClientID` int(10) UNSIGNED ZEROFILL DEFAULT NULL,
  `type` enum('pat','oauth_access') NOT NULL DEFAULT 'pat',
  `name` varchar(100) NOT NULL,
  `tokenPrefix` varchar(24) NOT NULL,
  `tokenHash` varchar(128) NOT NULL,
  `expiresAt` datetime DEFAULT NULL,
  `lastUsedAt` datetime DEFAULT NULL,
  `revokedAt` datetime DEFAULT NULL,
  `timestampCreated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`gibbonAPITokenID`),
  UNIQUE KEY `tokenHash` (`tokenHash`),
  KEY `gibbonPersonID` (`gibbonPersonID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8";

$moduleTables[] = "CREATE TABLE `gibbonAPIAuditLog` (
  `gibbonAPIAuditLogID` int(14) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
  `gibbonAPITokenID` int(12) UNSIGNED ZEROFILL DEFAULT NULL,
  `gibbonPersonID` int(10) UNSIGNED ZEROFILL DEFAULT NULL,
  `method` varchar(10) NOT NULL,
  `path` varchar(255) NOT NULL,
  `statusCode` smallint NOT NULL,
  `ipAddress` varchar(45) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`gibbonAPIAuditLogID`),
  KEY `gibbonAPITokenID` (`gibbonAPITokenID`),
  KEY `timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8";

$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('API', 'apiEnabled', 'API Enabled', 'Allow external agents to call the REST API with a personal access token.', 'Y')";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('API', 'tokenExpiryDays', 'Token Expiry (Days)', 'Default lifetime of a new personal access token. Use 0 for no expiry.', '90')";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('API', 'rateLimitPerMinute', 'Rate Limit Per Minute', 'Maximum REST requests per token per minute. Use 0 to disable.', '120')";

$actionRows[] = [
    'name'                      => 'Manage API Tokens',
    'precedence'                => '0',
    'category'                  => 'API',
    'description'               => 'Create and revoke personal access tokens for the current user.',
    'URLList'                   => 'tokens_manage.php, tokens_manage_add.php, tokens_manage_delete.php',
    'entryURL'                  => 'tokens_manage.php',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'Y',
    'defaultPermissionStudent'  => 'Y',
    'defaultPermissionParent'   => 'Y',
    'defaultPermissionSupport'  => 'Y',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'Y',
    'categoryPermissionParent'  => 'Y',
    'categoryPermissionOther'   => 'Y',
];

$actionRows[] = [
    'name'                      => 'Manage API Settings',
    'precedence'                => '0',
    'category'                  => 'API',
    'description'               => 'Enable or disable the API and set token defaults. Revoke any user’s tokens.',
    'URLList'                   => 'settings.php, tokens_admin.php, tokens_admin_revoke.php',
    'entryURL'                  => 'settings.php',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];
