<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

namespace Gibbon\Module\API\Http;

use Gibbon\Contracts\Services\Session;

class WebProcessClient
{
    public function __construct(protected Session $session)
    {
    }

    /**
     * POST a Gibbon *Process.php page as the impersonated session user.
     *
     * @return array{status:int, location:?string, body:string}
     */
    public function post(string $relativePath, array $fields, array $query = []): array
    {
        $base = rtrim((string) $this->session->get('absoluteURL'), '/');
        if ($base === '') {
            throw new ApiException('School URL is not configured, cannot call the web process.', 500);
        }

        $url = $base.'/'.ltrim($relativePath, '/');
        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?').http_build_query($query);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $cookie = session_name().'='.session_id();
        $body = http_build_query($fields);

        if (function_exists('curl_init')) {
            return $this->viaCurl($url, $body, $cookie);
        }

        return $this->viaStream($url, $body, $cookie);
    }

    public function assertSuccess(array $result, string $failMessage): void
    {
        $location = (string) ($result['location'] ?? '');
        $return = '';
        if ($location !== '' && preg_match('/[?&]return=([^&]+)/', $location, $match)) {
            $return = urldecode($match[1]);
        }

        if (str_starts_with($return, 'success')) {
            return;
        }

        $map = [
            'error0' => [403, 'The web process denied access.'],
            'error1' => [422, 'The web process rejected the submitted fields.'],
            'error7' => [422, 'The web process rejected the submitted fields.'],
            'error2' => [500, 'The web process failed while saving.'],
        ];
        if (isset($map[$return])) {
            throw new ApiException($map[$return][1], $map[$return][0]);
        }

        $status = (int) ($result['status'] ?? 0);
        if ($status >= 200 && $status < 400 && $return === '') {
            throw new ApiException($failMessage.' The web process did not return a success status.', 502);
        }

        throw new ApiException($failMessage, $status >= 400 ? $status : 502);
    }

    protected function viaCurl(string $url, string $body, string $cookie): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Cookie: '.$cookie,
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new ApiException('Unable to call the web process: '.$error, 502);
        }

        return $this->splitResponse($status, (string) $raw);
    }

    protected function viaStream(string $url, string $body, string $cookie): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\nCookie: {$cookie}\r\n",
                'content' => $body,
                'ignore_errors' => true,
                'follow_location' => 0,
                'timeout' => 45,
            ],
        ]);
        $raw = @file_get_contents($url, false, $context);
        $status = 0;
        $headerBlock = '';
        if (isset($http_response_header) && is_array($http_response_header)) {
            $headerBlock = implode("\r\n", $http_response_header);
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', $http_response_header[0] ?? '', $match)) {
                $status = (int) $match[1];
            }
        }
        if ($raw === false) {
            throw new ApiException('Unable to call the web process.', 502);
        }

        return $this->parseHeaders($status, $headerBlock, (string) $raw);
    }

    protected function splitResponse(int $status, string $raw): array
    {
        $headerSize = strpos($raw, "\r\n\r\n");
        if ($headerSize === false) {
            return ['status' => $status, 'location' => null, 'body' => $raw];
        }
        $headers = substr($raw, 0, $headerSize);
        $body = substr($raw, $headerSize + 4);

        return $this->parseHeaders($status, $headers, $body);
    }

    protected function parseHeaders(int $status, string $headers, string $body): array
    {
        $location = null;
        if (preg_match('/^Location:\s*(.+)$/mi', $headers, $match)) {
            $location = trim($match[1]);
        }

        return ['status' => $status, 'location' => $location, 'body' => $body];
    }
}
