<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

namespace Gibbon\Module\API\Support;

use Gibbon\Module\API\Http\ApiException;

class RestTable
{
    public static function pick(array $body, array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $body)) {
                $value = $body[$key];
                if (is_array($value)) {
                    $value = implode(',', array_filter($value, fn ($item) => $item !== '' && $item !== null));
                }
                $out[$key] = $value;
            }
        }

        return $out;
    }

    public static function defaults(array $data, array $defaults): array
    {
        foreach ($defaults as $key => $value) {
            if (!array_key_exists($key, $data) || $data[$key] === null) {
                $data[$key] = $value;
            }
        }

        return $data;
    }

    public static function emptyToNull(array $data, array $keys): array
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && $data[$key] === '') {
                $data[$key] = null;
            }
        }

        return $data;
    }

    public static function requireFields(array $data, array $required): void
    {
        $missing = [];
        foreach ($required as $key) {
            if (!array_key_exists($key, $data) || $data[$key] === '' || $data[$key] === null) {
                $missing[] = $key;
            }
        }
        if ($missing) {
            throw new ApiException('Missing required fields: '.implode(', ', $missing).'.', 422);
        }
    }

    public static function requireRow($gateway, $id, string $message): array
    {
        $row = $gateway->getByID($id);
        if (empty($row)) {
            throw new ApiException($message, 404);
        }

        return $row;
    }

    public static function create($gateway, array $data, string $failMessage = 'Unable to create record.')
    {
        $id = $gateway->insert($data);
        if (empty($id)) {
            throw new ApiException($failMessage, 500);
        }

        return $gateway->getByID($id);
    }

    public static function update($gateway, $id, array $data, string $notFound): array
    {
        self::requireRow($gateway, $id, $notFound);
        if (!empty($data)) {
            $gateway->update($id, $data);
        }

        return $gateway->getByID($id);
    }

    public static function delete($gateway, $id, string $notFound): void
    {
        self::requireRow($gateway, $id, $notFound);
        $gateway->delete($id);
    }
}
