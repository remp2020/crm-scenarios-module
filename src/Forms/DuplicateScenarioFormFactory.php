<?php

namespace Crm\ScenariosModule\Forms;

use Closure;
use Contributte\Translation\Translator;
use Crm\ApplicationModule\UI\Form;
use Crm\ScenariosModule\Models\Scenario\ScenarioDuplicator;
use Crm\ScenariosModule\Repositories\ScenariosRepository;
use Nette\Forms\Controls\TextInput;
use Tomaj\Form\Renderer\BootstrapRenderer;

class DuplicateScenarioFormFactory
{
    public Closure $onSuccess;

    public function __construct(
        private readonly ScenariosRepository $scenariosRepository,
        private readonly ScenarioDuplicator $scenarioDuplicator,
        private readonly Translator $translator,
    ) {
    }

    public function create(): Form
    {
        $form = new Form;

        $form->setTranslator($this->translator);
        $form->setRenderer(new BootstrapRenderer());
        $form->getElementPrototype()->addClass('ajax');

        $form->addText('name', 'scenarios.components.duplicate_scenario_form.name.label')
            ->setRequired('scenarios.components.duplicate_scenario_form.name.required')
            ->addRule(function (TextInput $control) {
                $existingScenario = $this->scenariosRepository->all(deleted: false)
                    ->where('name', $control->getValue())
                    ->fetch();
                return $existingScenario === null;
            }, 'scenarios.components.duplicate_scenario_form.name.unique');

        $scenarioIdField = $form->addHidden('scenario_id')
            ->setHtmlAttribute('autocomplete', 'off');

        // Ensure ID is set for JavaScript access
        $scenarioIdField->getControlPrototype()->id = $scenarioIdField->getHtmlId();

        $form->addSubmit('send', 'system.save')
            ->setHtmlAttribute('class', 'btn btn-primary');

        $form->onSuccess[] = [$this, 'formSucceeded'];

        return $form;
    }

    public function formSucceeded(Form $form, $values): void
    {
        if (empty($values['scenario_id'])) {
            $form->addError('scenarios.components.duplicate_scenario_form.error.missing_scenario_id');
            return;
        }

        $scenario = $this->scenariosRepository->find($values['scenario_id']);
        if (!$scenario) {
            $form->addError('scenarios.components.duplicate_scenario_form.error.scenario_not_found');
            return;
        }

        $newScenario = $this->scenarioDuplicator->duplicate($scenario, $values['name']);

        $this->onSuccess->__invoke($newScenario);
    }
}
