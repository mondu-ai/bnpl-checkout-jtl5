<?php

namespace Plugin\MonduPayment\Hooks;

use Exception;
use JTL\Shop;
use JTL\Link\LinkInterface;


class Checkout
{

    private $linkHelper;

    public function __construct() {
        $this->linkHelper = Shop::Container()->getLinkService();
    }
    /**
     * @param array $args_arr
     * @throws Exception
     */
    public function execute($args_arr = []): void
    {
        try {

            # echo '<pre>';
            # var_dump($_SESSION);
            # echo '</pre>';

            if ($this->isMonduPaymentSelected()) {
                pq('head')->append('<script src="http://localhost:3002/dist/widget.js"></script>');
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