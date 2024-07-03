<?php

namespace Plugin\MonduPayment\Src\Services;

use Plugin\MonduPayment\Src\Helpers\BasketHelper;
use Plugin\MonduPayment\Src\Services\OrderServices\AbstractOrderAdditionalCostsService;
use Plugin\MonduPayment\Src\Support\HttpClients\MonduClient;
use JTL\Shop;
use JTL\Session\Frontend;
use JTL\DB\ReturnType;
use Plugin\MonduPayment\Src\Helpers\OrderHashHelper;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class OrderService
{
    private MonduClient $monduClient;
    private ConfigService $configService;

    private ?AbstractOrderAdditionalCostsService $orderAdditionalCostsService;

    public function __construct()
    {
        $this->monduClient = new MonduClient();
        $this->configService = new ConfigService();

        try {
            /**
             * @var $orderAdditionalCostsService AbstractOrderAdditionalCostsService
             */
            $this->orderAdditionalCostsService = Shop::Container()->get(AbstractOrderAdditionalCostsService::class);
        } catch (NotFoundExceptionInterface | ContainerExceptionInterface $e) {
            $this->orderAdditionalCostsService = null;
        }
    }

    public function token($paymentMethod)
    {
        $orderData = $this->getOrderData($paymentMethod);
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

        return [
                'error' => @$order['error'] ?? false,
                'token' => $monduOrderUuid,
                'hosted_checkout_url' => $hostedCheckoutUrl
            ];
    }

    public function getOrderData($paymentMethod)
    {
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

        $data = [
            'currency' => $currency,
            'state_flow' => $this->configService->getOrderFlow(),
            'success_url' => $this->getPaymentSuccessURL(),
            'cancel_url' => $this->getPaymentCancelURL(),
            'declined_url' => $this->getPaymentDeclineURL(),
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
                    'buyer_fee_cents' => (int) $this->orderAdditionalCostsService?->getAdditionalCostsCentsFromOrder($basket),
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
        } catch (Exception $e) {
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
        } catch (Exception $e) {
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

    public function getCheckoutURL(): string
    {
        return Shop::Container()->getLinkService()->getStaticRoute('bestellvorgang.php');
    }

    public function getPaymentSuccessURL(): string
    {
        return $this->getCheckoutURL() . '?payment=accepted';
    }

    public function getPaymentCancelURL(): string
    {
        return $this->getCheckoutURL() . '?editZahlungsart=1&payment=cancelled';
    }

    public function getPaymentDeclineURL(): string
    {
        return $this->getCheckoutURL() . '?editZahlungsart=1&payment=declined';
    }
}
