<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Engine;

use Crm\ApplicationModule\Models\Scenario\TriggerManager;
use Nette\Utils\Json;

class JobParamsRedactor
{
    public const REDACTED = 'REDACTED';

    // always redacted, even if no trigger declares them
    private const DEFAULT_SENSITIVE_PARAMS = ['pass', 'password'];

    public function __construct(
        private readonly TriggerManager $triggerManager,
    ) {
    }

    public function redact(array $params): array
    {
        return $this->redactKeys($params, $this->sensitiveParams());
    }

    /**
     * Returns the JSON untouched when nothing had to be redacted.
     */
    public function redactJson(string $json): ?string
    {
        if ($json === '') {
            return $json;
        }

        $params = Json::decode($json, forceArrays: true);
        if (!is_array($params)) {
            return $json;
        }

        $redacted = $this->redact($params);
        if ($redacted === $params) {
            return $json;
        }

        return Json::encode($redacted);
    }

    private function redactKeys(array $params, array $sensitiveParams): array
    {
        foreach ($params as $name => $value) {
            if (in_array($name, $sensitiveParams, true)) {
                $params[$name] = self::REDACTED;
            } elseif (is_array($value)) {
                $params[$name] = $this->redactKeys($value, $sensitiveParams);
            }
        }

        return $params;
    }

    private function sensitiveParams(): array
    {
        return array_values(array_unique([
            ...self::DEFAULT_SENSITIVE_PARAMS,
            ...$this->triggerManager->getSensitiveParams(),
        ]));
    }
}
