<?php

namespace Plugin\MonduPayment\Hooks;

use Exception;
use JTL\Shop;

class Checkout
{
    private $smarty;

    public function __construct() {
        $this->smarty = Shop::Smarty();
    }

    /**
     * @param array $args_arr
     *
     * @throws Exception
     */
    public function execute(array $args_arr = []): void
    {
        try {
            $monduConfig = [
                'payment_methods' => $this->smarty->getTemplateVars('MonduPaymentMethods')
            ];
            pq('head')->append('<script>window.MONDU_CONFIG = '.json_encode($monduConfig).'</script>');
        } catch (Exception $e) { 
        }
    }
}

$hook = new Checkout();
$hook->execute();