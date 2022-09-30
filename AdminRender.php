<?php

declare(strict_types=1);

namespace Plugin\MonduPayment;

use InvalidArgumentException;
use JTL\Smarty\JTLSmarty;

/**
 * Class AdminRender
 * @package Plugin\MonduPayment
 */
class AdminRender
{
    /**
     * @var path
     */
    private $plugin;

    /**
     * @var path
     */
    private $path;

    /**
     * AdminRender constructor.
     * @param Object $plugin
     */
    public function __construct(Object $plugin)
    {
        $this->plugin = $plugin;
        $this->path   = $this->plugin->getPaths()->getAdminPath() . '/templates/';
    }

    /**
     * @param string    $template
     * @param int       $menuID
     * @param Object $smarty
     * @return string
     * @throws \SmartyException
     */
    public function renderPage(string $template, JTLSmarty $smarty): string
    {
        $smarty->assign('pluginPath', $this->plugin->getPaths()->getAdminURL());

        $smarty->assign('pluginURL', $this->plugin->getPaths()->getShopURL());
        
        return $smarty->fetch($this->path . 'post/layout.tpl');
    }
}
