<?php
namespace Plugin\MonduPayment\PaymentMethod;

use JTL\Alert\Alert;
use JTL\Mail\Mail\Mail;
use JTL\Mail\Mailer;
use JTL\Plugin\Payment\Method;
use JTL\Session\Frontend;
use JTL\Shop;
use PHPMailer\PHPMailer\Exception;
use Plugin\MonduPayment\Src\Services\OrderService;
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
        $checkoutController = new OrderService();
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

        $monduClient = new MonduClient();
        $authorizedNetTerm = 0;
        $monduOrderApi = $monduClient->getOrder($_SESSION['monduOrderUuid']); 

        if (!empty($monduOrderApi['order']['authorized_net_term'])) {
            $authorizedNetTerm = $monduOrderApi['order']['authorized_net_term'];
        }

        $monduOrder->create([
            'order_id' => $order->kBestellung,
            'state' => 'created',
            'external_reference_id' => $order->cBestellNr,
            'order_uuid' => $_SESSION['monduOrderUuid'],
            'authorized_net_term' => $authorizedNetTerm
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
            $this->getErrorMessage(),
            'paymentFailed'
        );

        $monduClient = new MonduClient();
        $monduClient->cancelOrder(['order_uuid' => $_SESSION['monduOrderUuid']]);

        $this->cancelOrder($orderId);
        unset($_SESSION['monduOrderUuid']);
        unset($_SESSION['monduCartHash']);
    }

    private function getErrorMessage()
    {
        $lang = Shop::Lang()->getIso();

        switch($lang) {
            case 'eng':
                return 'There was an error processing your request with Mondu. Please try again.';
            case 'ger':
                return 'Bei der Bearbeitung Ihrer Anfrage an Mondu ist ein Fehler aufgetreten. Bitte versuchen Sie es erneut.';
            default:
                return 'There was an error processing your request with Mondu. Please try again.';
        }
    }
}
