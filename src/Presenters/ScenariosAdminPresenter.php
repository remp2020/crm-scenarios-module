<?php

namespace Crm\ScenariosModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\ApiModule\Token\InternalToken;
use Crm\ApplicationModule\Components\PreviousNextPaginator;
use Crm\OneSignalModule\Events\OneSignalNotificationEvent;
use Crm\ScenariosModule\Events\BannerEvent;
use Crm\ScenariosModule\Repository\ScenariosRepository;
use Nette\Application\BadRequestException;
use Nette\Application\LinkGenerator;

class ScenariosAdminPresenter extends AdminPresenter
{
    private $scenariosRepository;

    private $internalToken;

    private $bannerEnabled;

    private $linkGenerator;

    public function __construct(
        ScenariosRepository $scenariosRepository,
        InternalToken $internalToken,
        LinkGenerator $linkGenerator
    ) {
        parent::__construct();

        $this->scenariosRepository = $scenariosRepository;
        $this->internalToken = $internalToken;
        $this->linkGenerator = $linkGenerator;
    }

    /**
     * @admin-access-level read
     */
    public function renderDefault()
    {
        $scenarios = $this->scenariosRepository->all();

        $pnp = new PreviousNextPaginator();
        $this->addComponent($pnp, 'paginator');
        $paginator = $pnp->getPaginator();
        $paginator->setItemsPerPage($this->onPage);

        $scenarios = $scenarios->limit($paginator->getLength(), $paginator->getOffset())->fetchAll();
        $pnp->setActualItemCount(count($scenarios));

        $this->template->scenarios = $scenarios;
    }

    /**
     * @admin-access-level write
     */
    public function renderEdit($id)
    {
        $scenario = $this->scenariosRepository->find($id);
        if (!$scenario) {
            $this->flashMessage($this->translator->translate('scenarios.admin.scenarios.messages.scenario_not_found'));
            $this->redirect('default');
        }
        $this->template->scenario = $scenario;
    }

    /**
     * @admin-access-level write
     */
    public function renderNew()
    {
    }

    /**
     * @admin-access-level write
     */
    public function renderEmbed($id)
    {
        // Enable Banner element in ScenarioBuilder if BannerEvent has handlers (so it can be processed)
        // DO NOT move this to constructor, listener might not have been added yet
        $this->template->bannerEnabled = $this->emitter->hasListeners(BannerEvent::class);

        $this->template->pushNotificationEnabled = false;
        if (class_exists(OneSignalNotificationEvent::class)) {
            // Enable Push notification element in ScenarioBuilder if OneSignalNotificationEvent has handlers (so it can be processed)
            // DO NOT move this to constructor, listener might not have been added yet
            $this->template->pushNotificationEnabled = $this->emitter->hasListeners(OneSignalNotificationEvent::class);
        }

        $this->template->crmHost = $this->getHttpRequest()->getUrl()->getBaseUrl();
        $this->template->apiToken = 'Bearer ' . $this->internalToken->tokenValue();

        $this->template->scenario = $this->scenariosRepository->find($id);
    }

    /**
     * @admin-access-level write
     */
    public function handleEnable($id)
    {
        $scenario = $this->scenariosRepository->find($id);
        if (!$scenario) {
            throw new BadRequestException("unable to load scenario: " . $id);
        }
        $this->scenariosRepository->setEnabled($scenario);
        $this->flashMessage($this->translator->translate(
            'scenarios.admin.scenarios.messages.scenario_enabled',
            [
                '%name%' => $scenario->name,
            ]
        ));
        $this->redirect('default');
    }

    /**
     * @admin-access-level write
     */
    public function handleDisable($id)
    {
        $scenario = $this->scenariosRepository->find($id);
        if (!$scenario) {
            throw new BadRequestException("unable to load scenario: " . $id);
        }
        $this->scenariosRepository->setEnabled($scenario, false);
        $this->flashMessage($this->translator->translate(
            'scenarios.admin.scenarios.messages.scenario_disabled',
            [
                '%name%' => $scenario->name,
            ]
        ));
        $this->redirect('default');
    }
}
