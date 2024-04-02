<?php

declare(strict_types=1);

namespace Plugin\MonduPayment;

use JTL\Plugin\PluginInterface;
use JTL\Shop;
use JTL\Smarty\JTLSmarty;
use Plugin\MonduPayment\Src\Helpers\Response;
use Plugin\MonduPayment\Src\Support\HttpClients\MonduClient;

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
    public function __construct(PluginInterface $plugin)
    {
        $this->plugin = $plugin;
        $this->path   = $this->plugin->getPaths()->getAdminPath() . '/templates/';
    }

    /**
     * @param string $tabName
     * @param JTLSmarty $smarty
     * @param $request
     * @return string
     * @throws \SmartyException
     */
    public function renderPage(string $tabName, JTLSmarty $smarty, $request): string
    {
        $monduRequestType = !empty($request['request_type']) ? $request['request_type'] : null;

        if ($monduRequestType === 'registerWebhooks') {
            $this->handleRegisterWebhooksRequest();
        }

        $smarty->assign('pluginPath', $this->plugin->getPaths()->getAdminURL());

        $smarty->assign('pluginURL', $this->plugin->getPaths()->getShopURL());

        if ($tabName === 'Info') {
            return $smarty
                ->assign('postUrl', Shop::getURL() . '/' . \PFAD_ADMIN . 'plugin.php?kPlugin=' . $this->plugin->getID())
                ->fetch($this->path . 'mondu_info.tpl');
        }

        return '';
    }

    private function handleRegisterWebhooksRequest()
    {
        $monduClient = new MonduClient();
        $webhookTopics = ['order', 'invoice'];
        $webhookSecret = $monduClient->getWebhookKeys();

        if (isset($webhookSecret['error'])) {
            Response::json(
                $webhookSecret,
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
            exit;
        }

        Shop::Container()->getDB()->update('tplugineinstellungen' , 'cName' , 'webhooks_secret', (object) ['cWert' => $webhookSecret['webhook_secret']]);

        foreach ($webhookTopics as $topic) {
            $requestData = [
                'topic' => $topic,
                'address' => Shop::getURL() . '/mondu-api?return=webhook'
            ];

            $response = $monduClient->registerWebhooks($requestData);

            if (isset($response['error'])) {
                Response::json(
                    $response,
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }
        }

        Response::json(
            ['success' => true, 'webhooks_secret' => $webhookSecret['webhook_secret']]
        );
    }
}
