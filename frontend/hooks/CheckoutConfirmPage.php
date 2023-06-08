<?php

namespace Plugin\MonduPayment\Hooks;

use JTL\Shop;
use Plugin\MonduPayment\Src\Services\OrderService;

class CheckoutConfirmPage
{
    public function execute(): void
    {
        if (isset($_SESSION['Zahlungsart']) && $_SESSION['Zahlungsart']->cAnbieter == 'Mondu') {
            if (isset($GLOBALS['step']) && $GLOBALS['step'] == 'Bestaetigung') {
                if (!isset($_SESSION['monduOrderUuid']) || empty($_SESSION['monduOrderUuid'])) {
                    $orderService = new OrderService();
                    $orderData = $orderService->token($_SESSION['Zahlungsart']->kZahlungsart);

                    header('Location: ' . $orderData['hosted_checkout_url'], true, 303);
                    exit;
                }
            }
        }
    }
}

$hook = new CheckoutConfirmPage();
$hook->execute();