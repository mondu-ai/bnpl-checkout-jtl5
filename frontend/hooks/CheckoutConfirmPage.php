<?php

namespace Plugin\MonduPayment\Hooks;

use JTL\Shop;

class CheckoutConfirmPage
{
    public function execute(): void
    {
        if (isset($_SESSION['Zahlungsart']) && $_SESSION['Zahlungsart']->cAnbieter == 'Mondu') {
            if (isset($GLOBALS['step']) && $GLOBALS['step'] == 'Bestaetigung') {
                if (!isset($_SESSION['monduOrderUuid']) || empty($_SESSION['monduOrderUuid'])) {
                  $linkHelper = Shop::Container()->getLinkService();

                  header('Location: ' . $linkHelper->getStaticRoute('bestellvorgang.php') . '?editZahlungsart=1', true, 303);
                  exit;
                }
            }
        }
    }
}

$hook = new CheckoutConfirmPage();
$hook->execute();