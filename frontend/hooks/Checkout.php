<?php

namespace Plugin\MonduPayment\Hooks;

use Exception;
use JTL\Shop;
use JTL\Link\LinkInterface;
use JTL\Session\Frontend;
use Plugin\MonduPayment\Src\Services\ConfigService;

class Checkout
{
    private $linkHelper;
    private $configService;
    private $smarty;

    public function __construct() {
        $this->linkHelper = Shop::Container()->getLinkService();
        $this->configService = new ConfigService();
        $this->smarty = Shop::Smarty();
    }
    /**
     * @param array $args_arr
     * @throws Exception
     */
    public function execute($args_arr = []): void
    {
        try {
            if ($this->configService->getOrderFlow() === ConfigService::AUTHORIZATION_FLOW) {
                $monduConfig = [
                    'state_flow' => $this->configService->getOrderFlow(),
                    'token_url' => 'mondu-api?fetch=token',
                    'payment_methods' => $this->smarty->getTemplateVars('MonduPaymentMethods')
                ];
                pq('body')->append('<div id="mondu-checkout-widget"></div>');
                pq('head')->append('<script src="' . $this->configService->getWidgetUrl() . '"></script>');
                pq('head')->append('<script>window.MONDU_CONFIG = '.json_encode($monduConfig).'</script>');
            } elseif ($this->isMonduPaymentSelected()) {
                pq('head')->append("<script>window.MONDU_CONFIG = { selected: true, token_url: 'mondu-api?fetch=token' };</script>");
                pq('head')->append('<script src="' . $this->configService->getWidgetUrl() . '"></script>');
                pq('body')->append('<div id="mondu-checkout-widget"></div>');
            }
        } catch (Exception $e) { 
        }
    }

    /**
     * @return bool
     */
    public function isMonduPaymentSelected(): bool
    {
        return array_key_exists('Zahlungsart', $_SESSION) && $_SESSION['Zahlungsart']->cAnbieter == 'Mondu';
    }

    public function getMonduTokenUrl(): string {
        return $this->getLinkByID('mondu_payment_token');
    }

    /**
     * @return string
     */
    private function getLinkByID(string $identifier): string
    {
        if ($identifier === null) {
            return null;
        }
        foreach ($this->linkHelper->getLinkGroups() as $linkGroup) {
            /** @var LinkGroupInterface $linkGroup */
            $first = $linkGroup->getLinks()->first(static function (LinkInterface $link) use ($identifier) {
                return $link->getIdentifier() === $identifier;
            });
            if ($first !== null) {
                return $first->getUrls()[1];
            }
        }

        return null;
    }
}

$hook = new Checkout();
$hook->execute();