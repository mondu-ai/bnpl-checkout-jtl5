<?php

namespace Plugin\MonduPayment\Src\Controllers\Frontend;

use Plugin\MonduPayment\Src\Support\Http\Request;
use Plugin\MonduPayment\Src\Helpers\Response;
use Plugin\MonduPayment\Src\Support\HttpClients\MonduClient;
use JTL\Shop;
use JTL\Cart\CartHelper;
use JTL\Session\Frontend;
use Plugin\MonduPayment\Src\Support\Debug\Debugger;
use JTL\Plugin\Helper;
use Plugin\MonduPayment\Src\Services\ConfigService;
use JTL\Helpers\Tax;
use JTL\Catalog\Product\Preise;
use JTL\Cart\CartItem;

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

        // Remove surcharge tax from taxAmount
        if ($cartTotals->surcharge[1] != 0) {
            $taxAmount -= $cartTotals->surcharge[1] - $cartTotals->surcharge[0];
          }

        // Add discount tax from taxAmount
        if ($cartTotals->discount[1] != 0) {
          $taxAmount += $cartTotals->discount[1] - $cartTotals->discount[0];
        }

        $buyerPhone = $customer->cTel ?? $customer->cMobil;

        return [
            'currency' => 'EUR',
            'payment_method' => $this->getPaymentMethod(),
            'net_term' => $this->getNetTerm(),
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
                    'buyer_fee_cents' => round($cartTotals->surcharge[1] * 100),
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

        $this->fixSummationRounding();

        $cart = Frontend::getCart();
        $cartLineItems = $cart->PositionenArr;
        
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

    public function getPaymentMethod()
    {
        try { 
            $paymentMethodModul = $_SESSION['Zahlungsart']->cModulId;
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

    public function getNetTerm()
    {
        try { 
            $paymentMethodModul = $_SESSION['Zahlungsart']->cModulId;
            $paymentMethod = $this->configService->getNetTermByKPlugin($paymentMethodModul);

            if (isset($paymentMethod)) {
                return $paymentMethod;
            }

            return '';
        } catch (Exception $e) 
        {
            return '';
        } 
    }

    /**
     * use summation rounding to even out discrepancies between total basket sum and sum of basket position totals
     * Note: This is JTL 5 native function found in Cart.php
     * Note: JTL 5 by default does not fix summation rounding until totals are calculated
     * @param int $precision
     */

    public function fixSummationRounding(int $precision = 2): void
    {
        $cart = Frontend::getCart();

        $cumulatedDelta    = 0;
        $cumulatedDeltaNet = 0;
        foreach (Frontend::getCurrencies() as $currency) {
            $currencyName = $currency->getName();
            foreach ($cart->PositionenArr as $i => $item) {
                $grossAmount        = Tax::getGross(
                    $item->fPreis * $item->nAnzahl,
                    CartItem::getTaxRate($item),
                    12
                );
                $netAmount          = $item->fPreis * $item->nAnzahl;
                $roundedGrossAmount = Tax::getGross(
                    $item->fPreis * $item->nAnzahl + $cumulatedDelta,
                    CartItem::getTaxRate($item),
                    $precision
                );
                $roundedNetAmount   = \round($item->fPreis * $item->nAnzahl + $cumulatedDeltaNet, $precision);

                if ($i !== 0 && $item->nPosTyp === \C_WARENKORBPOS_TYP_ARTIKEL) {
                    if ($grossAmount != 0) {
                        $item->fPreis = $roundedGrossAmount;
                    }
                    if ($netAmount != 0) {
                        $item->fPreis = $roundedNetAmount;
                    }
                }
                $cumulatedDelta    += ($grossAmount - $roundedGrossAmount);
                $cumulatedDeltaNet += ($netAmount - $roundedNetAmount);
            }
        }
    }
}
