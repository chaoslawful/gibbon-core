<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

namespace Gibbon\Module\API\Http;

class ApiException extends \RuntimeException
{
    protected array $details = [];

    public function __construct(string $message, int $status = 400, array $details = [])
    {
        parent::__construct($message, $status);
        $this->details = $details;
    }

    public function getDetails(): array
    {
        return $this->details;
    }
}
