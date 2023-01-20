<?php
namespace Plugin\MonduPayment\PaymentMethod;

use JTL\Alert\Alert;
use JTL\Mail\Mail\Mail;
use JTL\Mail\Mailer;
use JTL\Plugin\Payment\Method;
use JTL\Session\Frontend;
use JTL\Shop;
use PHPMailer\PHPMailer\Exception;
use stdClass;
use JTL\Checkout\Bestellung;
use Plugin\MonduPayment\Src\Support\HttpClients\MonduClient;
use Plugin\MonduPayment\Src\Models\MonduOrder;
use Plugin\MonduPayment\Src\Services\ConfigService;
use JTL\Cart\Cart;
use Plugin\MonduPayment\Src\Helpers\OrderHashHelper;
use Plugin\MonduPayment\Src\Controllers\Frontend\CheckoutController;


/**
* Class MonduPayment.
*/
class MonduPayment extends Method
{
    public function isValidIntern($args_arr = []): bool
    {
      if ($this->duringCheckout) {
          return false;
      }
    
      return parent::isValidIntern($args_arr);
    }

    public function preparePaymentProcess($order): void
    {
        parent::preparePaymentProcess($order);

        $configService = ConfigService::getInstance();

        $this->confirmOrder($order);
    }

    public function createInvoice(int $orderID, int $languageID): object
    {
       parent::createInvoice($orderID, $languageID);
    }

    private function confirmOrder($order)
    {
        $checkoutController = new CheckoutController();
        $orderData = $checkoutController->getOrderData($order->Zahlungsart->cModulId);

        if(OrderHashHelper::getOrderHash($orderData) !== $_SESSION['monduCartHash']) {
            $this->handleFail($order->kBestellung);
            return;
        }

        $configService = ConfigService::getInstance();
        $monduClient = new MonduClient();

        $monduOrder = $monduClient->confirmOrder([
            'uuid' => $_SESSION['monduOrderUuid'],
            'external_reference_id' => $order->cBestellNr
        ]);

        if(!empty($monduOrder['error'])) {
            $this->handleFail($order->kBestellung);
            return;
        }

        $this->afterApiRequest($order);
    }

    private function afterApiRequest($order) {
        $monduOrder = new MonduOrder();
        $configService = ConfigService::getInstance();

        $monduOrder->create([
            'order_id' => $order->kBestellung,
            'state' => 'created',
            'external_reference_id' => $order->cBestellNr,
            'order_uuid' => $_SESSION['monduOrderUuid']
        ]);

        if ($configService->shouldMarkOrderAsPaid())
        {
            $payValue  = $order->fGesamtsumme;
            $hash = $this->generateHash($order);

            $this->deletePaymentHash($hash);
            $this->addIncomingPayment($order, (object)[
                'fBetrag'  => $payValue,
                'cZahler'  => 'Mondu',
                'cHinweis' => $_SESSION['monduOrderUuid'],
            ]);

            $this->setOrderStatusToPaid($order);
        }

        unset($_SESSION['monduOrderUuid']);
        unset($_SESSION['monduCartHash']);
    }

    private function handleFail($orderId) {
        Shop::Container()->getAlertService()->addAlert(
            Alert::TYPE_ERROR,
            'Mondu wasnt able to confirm the order, please try again',
            'paymentFailed'
        );
        $this->cancelOrder($orderId);
        unset($_SESSION['monduOrderUuid']);
        unset($_SESSION['monduCartHash']);
    }
}
