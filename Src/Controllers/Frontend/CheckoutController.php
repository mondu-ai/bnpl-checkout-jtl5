<?php

namespace Plugin\MonduPayment\Src\Controllers\Frontend;

use Plugin\MonduPayment\Src\Helpers\BasketHelper;
use Plugin\MonduPayment\Src\Helpers\Text;
use Plugin\MonduPayment\Src\Support\Http\Request;
use Plugin\MonduPayment\Src\Helpers\Response;
use Plugin\MonduPayment\Src\Support\HttpClients\MonduClient;
use JTL\Shop;
use JTL\Session\Frontend;
use Plugin\MonduPayment\Src\Services\ConfigService;
use JTL\DB\ReturnType;
use JTL\Helpers\Tax;
use JTL\Catalog\Product\Preise;
use JTL\Cart\CartItem;
use Plugin\MonduPayment\Src\Helpers\OrderHashHelper;

class CheckoutController
{
    private MonduClient $monduClient;
    private ConfigService $configService;
    
    public function __construct()
    {
        $this->monduClient = new MonduClient();
        $this->configService = new ConfigService();
    }

    public function token(Request $request, int $pluginId)
    {
        $paymentMethod = $request->all()['payment_method'] ?? null;
        $formParams = $request->allRaw()['form_params'] ?? null;

        $orderData = $this->getOrderData($paymentMethod, $formParams);
        $order = $this->monduClient->createOrder($orderData);

        $monduOrderUuid = @$order['order']['uuid'];
        $hostedCheckoutUrl = '';

        if ($monduOrderUuid != null) {
            $_SESSION['monduOrderUuid'] = $monduOrderUuid;
            $_SESSION['monduCartHash'] = OrderHashHelper::getOrderHash($orderData);
        }


        if (isset($order['order']['hosted_checkout_url'])) {
            $hostedCheckoutUrl = $order['order']['hosted_checkout_url'];
        }

        return Response::json(
            [
                'error' => @$order['error'] ?? false,
                'token' => $monduOrderUuid,
                'hosted_checkout_url' => $hostedCheckoutUrl
            ]
        );
    }

    public function getOrderData($paymentMethod, $formParams = null)
    {
        if($formParams) {
            \parse_str($formParams, $params);
            $params = Text::filterXSS($params);

            BasketHelper::addSurcharge($this->getPaymentId($paymentMethod), $params);
        }

        $basket = BasketHelper::getBasket();

        $customer = Frontend::getCustomer();
        $shippingAddress = $_SESSION['Lieferadresse'];

        $buyerPhone = $customer->cTel ?? $customer->cMobil;

        $buyer = [];

        if (!empty($customer->cMail))
            $buyer['email'] = html_entity_decode($customer->cMail);
        
        if (!empty($customer->cVorname))
            $buyer['first_name'] = html_entity_decode($customer->cVorname);
        
        if (!empty($customer->cNachname))
            $buyer['last_name'] = html_entity_decode($customer->cNachname);
        
        if (!empty($customer->cFirma))
            $buyer['company_name'] = html_entity_decode($customer->cFirma);

        if (!empty($buyerPhone))
            $buyer['phone'] = html_entity_decode($buyerPhone);

        if (!empty($customer->cStrasse))
            $buyer['address_line1'] = html_entity_decode($customer->cStrasse . " " . $customer->cHausnummer);
        
        if (!empty($customer->cPLZ))
            $buyer['zip_code'] = $customer->cPLZ;

        $buyer['is_registered'] = $customer->kKunde != null;

        $currency = Frontend::getCurrency()->getCode();

        $url = "https://678a-2a02-27b0-5501-b0a0-d993-9949-7150-5bc1.ngrok-free.app";

        // use get_static_route bestellvorgang

        $data = [
            'currency' => $currency,
            'state_flow' => $this->configService->getOrderFlow(),
            'success_url' => $url . '/Bestellvorgang?editVersandart=1&payment=accepted',
            'cancel_url' => $url . '/Bestellvorgang?editVersandart=1&payment=cancelled',
            'declined_url' => $url . '/Bestellvorgang?editVersandart=1&payment=declined',
            'payment_method' => $this->getPaymentMethod($paymentMethod),
            'gross_amount_cents' => round($basket->total[1] * 100),
            'source' => 'widget',
            'external_reference_id' => uniqid('M_JTL_'),
            'buyer' => $buyer,
            'billing_address' => [
                'address_line1' => html_entity_decode($customer->cStrasse . " " . $customer->cHausnummer),
                'city' => html_entity_decode($customer->cOrt),
                'country_code' => $customer->cLand,
                'zip_code' => $customer->cPLZ
            ],
            'shipping_address' => [
                'address_line1' => html_entity_decode($shippingAddress->cStrasse . " " . $shippingAddress->cHausnummer),
                'city' => html_entity_decode($shippingAddress->cOrt),
                'country_code' => $shippingAddress->cLand,
                'zip_code' => $shippingAddress->cPLZ
            ],
            'lines' => [
                [
                    'buyer_fee_cents' => round($basket->surcharge[0] * 100),
                    'discount_cents' => round($basket->discount[0] * 100),
                    'shipping_price_cents' => round($basket->shipping[0] * 100),
                    'tax_cents' => round(($basket->total[1] - $basket->total[0]) * 100),
                    'line_items' => $this->getLineItems()
                ]
            ]
        ];

        $netTerm = $this->getNetTerm($paymentMethod);
        if ($netTerm != null)
            $data['net_term'] = $netTerm;

        return $data;
    }

    public function getLineItems()
    {
        $lineItems = [];

        $basket = BasketHelper::getBasket();
        $cart = Frontend::getCart();
        $cartLineItems = $cart->PositionenArr;
        
        foreach ($basket->items as $key => $basketLineItem) {
            $lineItem = $cartLineItems[$key];

            if ($lineItem->Artikel == null)
            {
                continue;
            }
            
            $lineItems[] = [
                'external_reference_id' => strval($lineItem->kArtikel),
                'quantity' => $lineItem->nAnzahl,
                'title' => html_entity_decode($lineItem->Artikel->cName),
                'net_price_cents' => round(round($basketLineItem->amount[0] * $basketLineItem->quantity, 2) * 100),
                'net_price_per_item_cents' => round($lineItem->fPreisEinzelNetto * 100)
            ];
        }

        return $lineItems;
    }

    public function getPaymentMethod($cModulId)
    {
        try { 
            $paymentMethodModul = $cModulId;
            $paymentMethod = $this->configService->getPaymentMethodByKPlugin($paymentMethodModul);

            if (isset($paymentMethod)) {
                return $paymentMethod;
            }

            return 'invoice';
        } catch (Exception $e) 
        {
            return 'invoice';
        } 
    }

    public function getNetTerm($cModulId)
    {
        try { 
            $paymentMethodModul = $cModulId;
            $netTerm = $this->configService->getNetTermByKPlugin($paymentMethodModul);

            if (isset($netTerm)) {
                return (int) $netTerm;
            }

            return null;
        } catch (Exception $e) 
        {
            return null;
        } 
    }

    public function getPayment($cModulId)
    {
        return Shop::Container()->getDB()->queryPrepared(
            'SELECT cName, kZahlungsart
                FROM tzahlungsart
                WHERE cModulId = :moduleID',
            ['moduleID' => $cModulId],
            ReturnType::SINGLE_OBJECT
        );
    }

    public function getPaymentId($cModulId): int
    {
        $payment = $this->getPayment($cModulId);

        return (int)($payment->kZahlungsart ?? 0);
    }
}
