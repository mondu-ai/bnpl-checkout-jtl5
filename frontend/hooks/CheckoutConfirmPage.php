<?php

namespace Plugin\MonduPayment\Hooks;

use JTL\Shop;
use Plugin\MonduPayment\Src\Services\OrderService;

class CheckoutConfirmPage
{
    /**
     * @return void
     */
    public function execute(): void
    {
        if (!$this->isMonduPayment() || !$this->isConfirmStep()) return;

        if ($_GET['payment'] !== 'accepted' && $_SERVER['REQUEST_METHOD'] === 'GET') {
            header('Location: ' . Shop::Container()->getLinkService()->getStaticRoute('bestellvorgang.php') . '?editZahlungsart=1');
        }

        if ($_GET['payment'] == 'accepted' || !$this->isMonduOrderSessionMissing()) return;

        if ($_GET['monduCreateOrder'] === 'true') {
            $orderService = new OrderService();
            $orderData = $orderService->token($_SESSION['Zahlungsart']->cModulId);
            header('Location: ' . $orderData['hosted_checkout_url'], true, 303);
            exit;
        } else {
            header('Location: ' . Shop::Container()->getLinkService()->getStaticRoute('bestellvorgang.php') . '?monduCreateOrder=true');
            exit;
        }
    }

    /**
     * @return bool
     */
    protected function isMonduPayment(): bool
    {
        return isset($_SESSION['Zahlungsart']) && $_SESSION['Zahlungsart']->cAnbieter == 'Mondu';
    }

    /**
     * @return bool
     */
    protected function isConfirmStep(): bool
    {
        return isset($GLOBALS['step']) && $GLOBALS['step'] == 'Bestaetigung';
    }

    /**
     * @return bool
     */
    protected function isMonduOrderSessionMissing(): bool
    {
        return !isset($_SESSION['monduOrderUuid']) || empty($_SESSION['monduOrderUuid']);
    }
}

$hook = new CheckoutConfirmPage();
$hook->execute();