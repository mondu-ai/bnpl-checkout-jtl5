<?php

declare(strict_types=1);

namespace Plugin\MonduPayment;

use JTL\Events\Dispatcher;
use JTL\Link\LinkInterface;
use JTL\Plugin\Bootstrapper;
use JTL\Shop;
use JTL\Smarty\JTLSmarty;
use Plugin\MonduPayment\Src\Services\InstallService;
use Plugin\MonduPayment\Src\Services\OrderServices\AbstractOrderAdditionalCostsService;
use Plugin\MonduPayment\Src\Services\OrderServices\OrderAdditionalCostsService;
use Plugin\MonduPayment\Src\Services\RoutesService;

/**
 * Class Bootstrap
 * @package Plugin\MonduPayment
 */
class Bootstrap extends Bootstrapper
{
    /**
     * @inheritdoc
     */
    public function boot(Dispatcher $dispatcher)
    {
        parent::boot($dispatcher);

        $this->registerServices();
    }

    /**
     * @inheritdoc
     */
    public function installed()
    {
        parent::installed();

        $withInstall = new InstallService;
        $withInstall->install();
    }

    /**
     * 
     * it's migrate database tables when plugin 
     */

    public function enabled()
    {
    }

    public function uninstalled(bool $deleteData = false)
    {
        if ($deleteData === true) {
            $deleteTables = new InstallService;
            $deleteTables->unInstall();
        }
    }
    /**
     * 
     * writing adminpanel routes for retriving data from database
     * @return string
     */
    public function renderAdminMenuTab(string $tabName, int $menuID, JTLSmarty $smarty): string
    {
        $request = $_REQUEST;

        $render = new AdminRender($this->getPlugin());
        return $render->renderPage($tabName, $smarty, $request);
    }

    /**
     * writing frontend routes for retrieving data from database
     */
    public function prepareFrontend(LinkInterface $link, JTLSmarty $smarty): bool
    {
        parent::prepareFrontend($link, $smarty);

        $routes = new RoutesService;
        $routes->frontEndRoutes($this->getPlugin());

        return true;
    }

    protected function registerServices(): void
    {
        $container = Shop::Container();
        try {
            $container->setSingleton(AbstractOrderAdditionalCostsService::class, function () {
                return new OrderAdditionalCostsService();
            });
        } catch (\Exception $e) {
            // Silence
        }
    }
}
