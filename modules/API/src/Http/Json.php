<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

namespace Gibbon\Module\API\Http;

use Psr\Http\Message\ResponseInterface;

class Json
{
    public static function write(ResponseInterface $response, $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus($status);
    }

    public static function file(ResponseInterface $response, string $absolutePath, string $downloadName, string $contentType, int $size): ResponseInterface
    {
        $stream = \GuzzleHttp\Psr7\Utils::streamFor(\GuzzleHttp\Psr7\Utils::tryFopen($absolutePath, 'rb'));
        $filename = str_replace(['"', "\r", "\n"], '', $downloadName);

        return $response
            ->withBody($stream)
            ->withHeader('Content-Type', $contentType !== '' ? $contentType : 'application/octet-stream')
            ->withHeader('Content-Disposition', 'attachment; filename="'.$filename.'"')
            ->withHeader('Content-Length', (string) $size)
            ->withStatus(200);
    }

    public static function error(ResponseInterface $response, string $message, int $status = 400, array $extra = []): ResponseInterface
    {
        if ($status < 400 || $status > 599) {
            $status = 500;
        }

        $payload = array_merge([
            'error' => $message,
            'status' => $status,
        ], $extra);

        return self::write($response, $payload, $status);
    }

    public static function empty(ResponseInterface $response, int $status = 204): ResponseInterface
    {
        return $response->withStatus($status);
    }
}
