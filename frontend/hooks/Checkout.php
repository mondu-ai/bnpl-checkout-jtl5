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

    public function __construct() {
        $this->linkHelper = Shop::Container()->getLinkService();
        $this->configService = new ConfigService(); 
    }
    /**
     * @param array $args_arr
     * @throws Exception
     */
    public function execute($args_arr = []): void
    {
        try {
            $paymentMethodName = $_SESSION['Zahlungsart']->cName ?? '';
            $_SESSION['mondu_payment_method'] = $this->getMonduPaymentMethod($paymentMethodName);

            if ($this->isMonduPaymentSelected()) {
                pq('head')->append('<script src="' . $this->configService->getWidgetUrl() . '"></script>');
                pq('head')->append("<script>window.MONDU_CONFIG = { selected: true, token_url: 'mondu-api?fetch=token' };</script>");
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

    public function getMonduPaymentMethod($name): string {
        switch($name) {
            case 'Mondu SEPA-Lastschrift':
                return 'direct_debit';
            case 'Mondu Ratenzahlung':
                return 'installment';
            default:
                return 'invoice';
        }
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