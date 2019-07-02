<?php

namespace Crm\ScenariosModule\Presenters;

use Crm\ApiModule\Token\InternalToken;
use Crm\ApplicationModule\Components\VisualPaginator;
use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\ScenariosModule\Repository\ScenariosRepository;
use Nette\Http\Request;

class ScenariosAdminPresenter extends AdminPresenter
{
    private $request;

    private $scenariosRepository;

    private $accessToken;

    /**
     * @var InternalToken
     */
    private $internalToken;


    public function __construct(
        Request $request,
        ScenariosRepository $scenariosRepository,
        InternalToken $internalToken
    ) {
        parent::__construct();
        $this->request = $request;
        $this->scenariosRepository = $scenariosRepository;
        $this->internalToken = $internalToken;
    }

    public function renderDefault()
    {
        $products = $this->scenariosRepository->all();

        $filteredCount = $this->template->filteredCount = $products->count('*');

        $vp = new VisualPaginator();
        $this->addComponent($vp, 'scenarios_vp');
        $paginator = $vp->getPaginator();
        $paginator->setItemCount($filteredCount);
        $paginator->setItemsPerPage($this->onPage);

        $this->template->vp = $vp;
        $this->template->scenarios = $products->limit($paginator->getLength(), $paginator->getOffset());
    }

    public function renderEdit($id)
    {
        $scenario = $this->scenariosRepository->find($id);
        if (!$scenario) {
            $this->flashMessage($this->translator->translate('scenarios.admin.scenarios.messages.scenario_not_found'));
            $this->redirect('default');
        }
        $this->template->scenario = $scenario;
    }

    public function renderNew()
    {
    }

    public function renderEmbed($id)
    {
        $this->template->apiHost = $this->getHttpRequest()->getUrl()->getBaseUrl() . "/api/v1";
        $this->template->apiToken = 'Bearer ' . $this->internalToken->tokenValue();
        $this->template->scenario = $this->scenariosRepository->find($id);
    }
}
