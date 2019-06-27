<?php

namespace Crm\ScenariosModule\Presenters;

use Crm\ApplicationModule\Components\VisualPaginator;
use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\ScenariosModule\Repository\ScenariosRepository;
use Nette\Http\Request;

class ScenariosAdminPresenter extends AdminPresenter
{
    private $request;

    private $scenariosRepository;

    public function __construct(
        Request $request,
        ScenariosRepository $scenariosRepository
    ) {
        parent::__construct();
        $this->request = $request;
        $this->scenariosRepository = $scenariosRepository;
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

    public function renderShow($id)
    {
        // TODO

        //$product = $this->productsRepository->find($id);
        //if (!$product) {
        //    $this->flashMessage($this->translator->translate('products.admin.products.messages.product_not_found'));
        //    $this->redirect('default');
        //}
        //
        //$levels = [0, 0.01, 3, 6, 10, 20, 50, 100, 200, 300];
        //$this->template->amountSpentDistributionLevels = $levels;
        //$this->template->amountSpentDistribution = $this->productsRepository->userAmountSpentDistribution($levels, $product->id);
        //
        //$levels = [0, 1, 3, 5, 8, 13, 21, 34];
        //$this->template->paymentCountDistributionLevels = $levels;
        //$this->template->paymentCountDistribution = $this->productsRepository->userPaymentCountsDistribution($levels, $product->id);
        //
        //$levels = [0, 1, 3, 5, 8, 13, 21, 34];
        //$this->template->shopCountsDistributionLevels = $levels;
        //$this->template->shopCountsDistribution = $this->productsRepository->productShopCountsDistribution($levels, $product->id);
        //
        //$levels = [0, 7, 14, 31, 93, 186, 365, 99999];
        //$this->template->shopDaysDistribution = $this->productsRepository->productDaysFromLastOrderDistribution($levels, $product->id);
        //
        //$this->template->product = $product;
        //
        //$this->template->soldCount = $this->getProductSalesCount($product);
    }

    public function renderEdit($id)
    {
        // TODO

        //$product = $this->productsRepository->find($id);
        //if (!$product) {
        //    $this->flashMessage($this->translator->translate('products.admin.products.messages.product_not_found'));
        //    $this->redirect('default');
        //}
        //$this->template->product = $product;
    }
}
