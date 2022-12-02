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


/**
* Class MonduPayment.
*/
class MonduPayment extends Method
{

    public function preparePaymentProcess($order): void
    {
        parent::preparePaymentProcess($order);
        
        $configService = new ConfigService();
        $monduClient = new MonduClient();
        
        $monduClient->updateExternalInfo([
            'uuid' => $_SESSION['monduOrderUuid'],
            'external_reference_id' => $order->cBestellNr
        ]);

        $monduOrder = new MonduOrder();
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
    }

    public function createInvoice(int $orderID, int $languageID): object
    {
       parent::createInvoice($orderID, $languageID);
    }
}