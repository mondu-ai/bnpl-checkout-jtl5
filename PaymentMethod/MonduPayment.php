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

        $state = $monduOrderApi['order']['state'] ?? null;

        if ($state === null) {
            // getOrder response did not contain order.state: the order is held from the Wawi
            // (fail-closed) and will only release on an order/confirmed webhook. Log it so an
            // empty/failed Mondu response is diagnosable instead of the order being silently stuck.
            Shop::Container()->getLogService()->warning(
                'Mondu: could not read order state from getOrder response for order ' . $order->cBestellNr
                . '; holding it from the Wawi until an order/confirmed webhook arrives.'
            );
        }

        if ($configService->shouldMarkOrderAsPaid()) {
            $payValue = $order->fGesamtsumme;
            $hash = $this->generateHash($order);

            $this->deletePaymentHash($hash);
            $this->addIncomingPayment($order, (object)[
                'fBetrag'  => $payValue,
                'cZahler'  => 'Mondu',
                'cHinweis' => $_SESSION['monduOrderUuid'],
            ]);

            if ($state === MonduPayment::STATE_CONFIRMED) {
                $this->setOrderStatusToPaid($order);
            }
        }

        // Prevent Wawi sync for every order that is not confirmed yet, regardless of
        // the "mark order as paid" setting. The order is released for the Wawi only
        // when Mondu confirms it (order/confirmed webhook -> unlockOrderForWawiSync).
        // This stops created-but-cancelled/unconfirmed orders from being transmitted.
        if ($state !== MonduPayment::STATE_CONFIRMED) {
            $upd            = new \stdClass();
            // Hold the order back from the Wawi for every non-confirmed state.
            $upd->cAbgeholt = 'M';

            // Only reflect a status the state actually justifies: pending -> In Bearbeitung,
            // declined/canceled -> Storno (consistent with WebhookController::MONDU_JTL_MAPPING).
            // Unknown/empty states are just held from the Wawi without forcing a misleading status.
            if ($state === MonduPayment::STATE_PENDING) {
                $upd->cStatus = \BESTELLUNG_STATUS_IN_BEARBEITUNG;
            } elseif ($state === MonduPayment::STATE_DECLINED || $state === MonduPayment::STATE_CANCELED) {
                $upd->cStatus = \BESTELLUNG_STATUS_STORNO;
            }

            Shop::Container()->getDB()->update('tbestellung', 'kBestellung', (int) $order->kBestellung, $upd);
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
