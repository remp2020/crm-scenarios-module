<?php

namespace Crm\ScenariosModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\ApiModule\Token\InternalToken;
use Crm\OneSignalModule\Events\OneSignalNotificationEvent;
use Crm\ScenariosModule\Events\BannerEvent;
use Crm\ScenariosModule\Repository\ScenariosRepository;
use Nette\Application\BadRequestException;

class ScenariosAdminPresenter extends AdminPresenter
{
    private $scenariosRepository;

    private $internalToken;

    public function __construct(
        ScenariosRepository $scenariosRepository,
        InternalToken $internalToken
    ) {
        parent::__construct();

        $this->scenariosRepository = $scenariosRepository;
        $this->internalToken = $internalToken;
    }

    /**
     * @admin-access-level read
     */
    public function renderDefault()
    {
        $scenarios = $this->scenariosRepository->all(deleted: false);
        $deletedScenarios = $this->scenariosRepository->all(deleted: true);

        $this->template->scenarios = $scenarios;
        $this->template->deletedScenarios = $deletedScenarios;
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
                'name' => $scenario->name,
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
                'name' => $scenario->name,
            ]
        ));
        $this->redirect('default');
    }

    /**
     * @admin-access-level write
     */
    public function handleDelete($id)
    {
        $scenario = $this->scenariosRepository->find($id);
        if (!$scenario) {
            throw new BadRequestException("unable to load scenario: " . $id);
        }

        $this->scenariosRepository->softDelete($scenario);

        $this->flashMessage($this->translator->translate(
            'scenarios.admin.scenarios.messages.scenario_deleted',
            [
                'name' => $scenario->name,
            ]
        ));
        $this->redirect('default');
    }

    /**
     * @admin-access-level write
     */
    public function handleRestore($id)
    {
        $scenario = $this->scenariosRepository->find($id);
        if (!$scenario) {
            throw new BadRequestException("unable to load scenario: " . $id);
        }

        $this->scenariosRepository->restoreScenario($scenario);

        $this->flashMessage($this->translator->translate(
            'scenarios.admin.scenarios.messages.scenario_restored',
            [
                'name' => $scenario->name,
            ]
        ));
        $this->redirect('default');
    }
}
