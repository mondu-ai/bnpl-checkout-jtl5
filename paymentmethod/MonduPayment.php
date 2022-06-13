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

/**
* Class MonduPayment.
*/
class MonduPayment extends Method
{

    public function preparePaymentProcess($order): void
    {
        parent::preparePaymentProcess($order);
        
        $monduClient = new MonduClient();
        
        $monduClient->updateExternalInfo([
            'uuid' => $_SESSION['monduOrderUuid'],
            'external_reference_id' => $order->cBestellNr
        ]);


        unset($_SESSION['monduOrderUuid']);
    }
}