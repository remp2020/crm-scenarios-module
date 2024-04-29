<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Commands;

use Crm\ApplicationModule\Models\Database\ActiveRow;
use Crm\ScenariosModule\Repositories\TriggersRepository;
use Nette\Application\LinkGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ListScenariosToReviewCommand extends Command
{
    // Format: 'trigger type' => [event_key=>description][]
    private const RECOMMENDED_TRIGGERS_TO_REVIEW = [
        'event' => [
            'address_changed' => "Event 'Address changed' was originally fired by 'address-changed' and 'new-address' event.\nIn the new implementation, it's fired only by 'address-changed' event and we added a new event 'New address' corresponding to 'new-address' event.",
            'family_request_created' => "Event 'family_request_created', was listed by mistake and wasn't working. It's recommended to remove it.",
            'family_request_accepted' => "Event 'family_request_accepted' was listed by mistake and wasn't working. It's recommended to remove it.",
            'before_recurrent_payment_charge' => "Event 'before_recurrent_payment_charge' was listed by mistake in 'Event' trigger and was working only as 'Before Event' trigger.\nIt's recommended to remove it from 'Event' trigger.",
        ],
    ];

    public function __construct(
        private readonly TriggersRepository $triggersRepository,
        private readonly LinkGenerator $crmLinkGenerator,
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('scenarios:list_scenarios_to_review')
            ->setDescription('Will list scenarios recommended to manual review after migration to Trigger manager.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $recommendedTriggersToReviewCount = 0;

        $table = new Table($output);
        $table->setHeaderTitle('Scenarios recommended to manual review');
        $table->setHeaders(['ID', 'Scenario name', 'Node name', 'Edit link', 'Description']);

        $triggers = $this->triggersRepository->all();
        foreach ($triggers as $trigger) {
            $recommendationDescription = $this->getRecommendationDescription($trigger);
            if ($recommendationDescription === null) {
                continue;
            }

            $recommendedTriggersToReviewCount++;

            $scenarioEditLink = $this->crmLinkGenerator->link('Scenarios:ScenariosAdmin:edit', [
                'id' => $trigger->scenario->id,
            ]);

            $table->addRow([
                $trigger->scenario->id,
                $trigger->scenario->name,
                $trigger->name,
                $scenarioEditLink,
                $recommendationDescription,
            ]);
        }

        if ($recommendedTriggersToReviewCount === 0) {
            $table->addRow(['-', 'No scenarios recommended to manual review', '-', '-', '-']);
        }

        $table->render();

        return Command::SUCCESS;
    }

    private function getRecommendationDescription(ActiveRow $scenarioTrigger): ?string
    {
        foreach (self::RECOMMENDED_TRIGGERS_TO_REVIEW as $type => $eventKeys) {
            if ($scenarioTrigger->type !== $type) {
                continue;
            }

            if (!array_key_exists($scenarioTrigger->event_code, $eventKeys)) {
                continue;
            }

            return $eventKeys[$scenarioTrigger->event_code];
        }

        return null;
    }
}
