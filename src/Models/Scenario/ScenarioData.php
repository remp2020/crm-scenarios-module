<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Models\Scenario;

final readonly class ScenarioData
{
    public function __construct(
        public string $name,
        public array $triggers,
        public array $elements,
        public array $visual,
        public bool $enabled,
    ) {
    }

    public static function fromArray(array $scenario): self
    {
        return new self(
            name: $scenario['name'],
            triggers: $scenario['triggers'],
            elements: $scenario['elements'],
            visual: $scenario['visual'],
            enabled: $scenario['enabled'] ?? false,
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'triggers' => json_decode(json_encode($this->triggers)),
            'elements' => json_decode(json_encode($this->elements)),
            'visual' => $this->visual,
            'enabled' => $this->enabled,
        ];
    }
}
