<?php

namespace Plugin\MonduPayment\Src\Controllers\Frontend;

use Plugin\MonduPayment\Src\Support\Http\Request;
use Plugin\MonduPayment\Src\Helpers\Response;
use Plugin\MonduPayment\Src\Support\HttpClients\MonduClient;
use JTL\Shop;
use JTL\Cart\CartHelper;
use JTL\Session\Frontend;
use Plugin\MonduPayment\Src\Support\Debug\Debugger;

class CheckoutController
{
    private MonduClient $monduClient;
    
    public function __construct()
    {
        $this->monduClient = new MonduClient();
    }

    public function token(Request $request, int $pluginId)
    {
        $order = $this->monduClient->createOrder($this->getOrderData());

        $monduOrderUuid = @$order['order']['uuid'];

        if ($monduOrderUuid != null) {
            $_SESSION['monduOrderUuid'] = $monduOrderUuid;
        }

        return Response::json(
            [
                'error' => @$order['error'] ?? false,
                'token' => $monduOrderUuid
            ]
        );
    }

    public function getOrderData()
    {
        $cart = Frontend::getCart();
        $cartHelper = new CartHelper();
        $cartTotals = $cartHelper->getTotal();

        $customer = Frontend::getCustomer();
        $shippingAddress = $_SESSION['Lieferadresse'];

        $taxAmount = 0;
        $taxPositions = $cart->gibSteuerpositionen();

        foreach ($taxPositions as $taxPosition) {
            $taxAmount += $taxPosition->fBetrag;
        }

        // Remove shipping tax from taxAmount
        if ($cartTotals->shipping[1] != 0) {
          $taxAmount -= $cartTotals->shipping[1] - $cartTotals->shipping[0];
        }

        // Add discount tax from taxAmount
        if ($cartTotals->discount[1] != 0) {
          $taxAmount += $cartTotals->discount[1] - $cartTotals->discount[0];
        }

        $buyerPhone = $customer->cTel ?? $customer->cMobil;

        return [
            'currency' => 'EUR',
            'external_reference_id' => uniqid('M_JTL_'),
            'buyer' => [
                'email' => $customer->cMail,
                'first_name' => $customer->cVorname,
                'last_name' => $customer-> cNachname,
                'company_name' => $customer->cFirma,
                'phone' => $buyerPhone == '' ? null : $buyerPhone,
                'address_line1' => $customer->cStrasse,
                'zip_code' => $customer->cPLZ,
                'is_registered' => $customer->kKunde != null
            ],
            'billing_address' => [
                'address_line1' => $customer->cStrasse,
                'city' => $customer->cOrt,
                'country_code' => $customer->cLand,
                'zip_code' => $customer->cPLZ
            ],
            'shipping_address' => [
                'address_line1' => $shippingAddress->cStrasse,
                'city' => $shippingAddress->cOrt,
                'country_code' => $shippingAddress->cLand,
                'zip_code' => $shippingAddress->cPLZ
            ],
            'lines' => [
                [
                    'discount_cents' => round($cartTotals->discount[1] * 100),
                    'shipping_price_cents' => round($cartTotals->shipping[1] * 100),
                    'tax_cents' => round($taxAmount * 100),
                    'line_items' => $this->getLineItems()
                ]
            ]
        ];
    }

    public function getLineItems()
    {
        $lineItems = [];

        $cartLineItems = Frontend::getCart()->PositionenArr;


        foreach ($cartLineItems as $lineItem) {
            if ($lineItem->Artikel == null)
            {
                continue;
            }
            
            $lineItems[] = [
                'external_reference_id' => strval($lineItem->kArtikel),
                'quantity' => $lineItem->nAnzahl,
                'title' => $lineItem->Artikel->cName,
                'net_price_cents' => round(round($lineItem->fPreis, 2) * $lineItem->nAnzahl * 100),
                'net_price_per_item_cents' => round($lineItem->fPreis * 100)
            ];
        }

        return $lineItems;
    }
}
