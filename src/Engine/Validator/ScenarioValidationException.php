<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Engine\Validator;

use Exception;

class ScenarioValidationException extends Exception
{
    public function __construct(
        string $message,
        public readonly string $triggerId,
        public readonly string $elementId,
    ) {
        parent::__construct($message);
    }
}
