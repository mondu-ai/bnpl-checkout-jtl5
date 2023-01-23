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
            $monduConfig = [
                'state_flow' => $this->configService->getOrderFlow(),
                'token_url' => 'mondu-api?fetch=token',
                'payment_methods' => $this->smarty->getTemplateVars('MonduPaymentMethods')
            ];
            pq('body')->append('<div id="mondu-checkout-widget"></div>');
            pq('head')->append('<script src="' . $this->configService->getWidgetUrl() . '"></script>');
            pq('head')->append('<script>window.MONDU_CONFIG = '.json_encode($monduConfig).'</script>');
        } catch (Exception $e) { 
        }
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