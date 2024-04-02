<?php
namespace Plugin\MonduPayment\PaymentMethod;

use JTL\Alert\Alert;
use JTL\Plugin\Payment\Method;
use JTL\Shop;
use Plugin\MonduPayment\Src\Services\OrderService;
use Plugin\MonduPayment\Src\Support\HttpClients\MonduClient;
use Plugin\MonduPayment\Src\Models\MonduOrder;
use Plugin\MonduPayment\Src\Services\ConfigService;
use Plugin\MonduPayment\Src\Helpers\OrderHashHelper;
use Plugin\MonduPayment\Src\Controllers\Frontend\CheckoutController;

/**
* Class MonduPayment.
*/
class MonduPayment extends Method
{
    public const STATE_CONFIRMED = 'confirmed';
    public const STATE_DECLINED = 'declined';
    public const STATE_CANCELED = 'canceled';
    public const STATE_PENDING = 'pending';
    public const STATE_COMPLETE = 'complete';
    public const STATE_SHIPPED = 'shipped';

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

        $this->confirmOrder($order);
    }

    public function createInvoice(int $orderID, int $languageID): object
    {
       return parent::createInvoice($orderID, $languageID);
    }

    private function confirmOrder($order)
    {
        $orderService = new OrderService();
        $orderData = $orderService->getOrderData($order->Zahlungsart->cModulId);

        if(OrderHashHelper::getOrderHash($orderData) !== $_SESSION['monduCartHash']) {
            $this->handleFail($order->kBestellung);
            return;
        }

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

    private function afterApiRequest($order)
    {
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
            'state' => $monduOrderApi['order']['state'],
            'external_reference_id' => $order->cBestellNr,
            'order_uuid' => $_SESSION['monduOrderUuid'],
            'authorized_net_term' => $authorizedNetTerm
        ]);

        if ($configService->shouldMarkOrderAsPaid()) {
            $payValue = $order->fGesamtsumme;
            $hash = $this->generateHash($order);

            $this->deletePaymentHash($hash);
            $this->addIncomingPayment($order, (object)[
                'fBetrag'  => $payValue,
                'cZahler'  => 'Mondu',
                'cHinweis' => $_SESSION['monduOrderUuid'],
            ]);

            if ($monduOrderApi['order']['state'] === MonduPayment::STATE_CONFIRMED) {
                $this->setOrderStatusToPaid($order);
            } else if ($monduOrderApi['order']['state'] === MonduPayment::STATE_PENDING) {
                $upd                = new \stdClass();
                $upd->cStatus       = \BESTELLUNG_STATUS_IN_BEARBEITUNG;
                Shop::Container()->getDB()->update('tbestellung', 'kBestellung', (int) $order->kBestellung, $upd);
            }
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
            case 'ger':
                return 'Bei der Bearbeitung Ihrer Anfrage an Mondu ist ein Fehler aufgetreten. Bitte versuchen Sie es erneut.';
            default:
                return 'There was an error processing your request with Mondu. Please try again.';
        }
    }
}
