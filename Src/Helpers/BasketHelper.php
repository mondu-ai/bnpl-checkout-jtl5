<?php

namespace Plugin\MonduPayment\Src\Helpers;

use JTL\Cart\CartHelper;
use JTL\Checkout\Zahlungsart;
use JTL\Session\Frontend;
use JTL\Shop;
use stdClass;

class BasketHelper
{
    public static function addSurcharge($paymentId = 0, $formParams = null): void
    {
        require_once \PFAD_ROOT . \PFAD_INCLUDES . 'bestellvorgang_inc.php';

        $paymentId = $paymentId <= 0 && isset($_SESSION['Zahlungsart'], $_SESSION['Zahlungsart']->kZahlungsart)
            ? (int)$_SESSION['Zahlungsart']->kZahlungsart
            : $paymentId;

        $shippingId = isset($_SESSION['Versandart'], $_SESSION['Versandart']->kVersandart)
            ? (int)$_SESSION['Versandart']->kVersandart
            : (int)$formParams['Versandart'];

        if ($shippingId <= 0 || $paymentId <= 0) {
            return;
        }
        $formParams['kVerpackung'] = $formParams['kVerpackung'] ?? [];
        if (!\versandartKorrekt($shippingId, $formParams)) {
            return;
        }

        $paymentMethod = new Zahlungsart();
        $paymentMethod->load($paymentId);

        if ((int)$paymentMethod->kZahlungsart <= 0) {
            return;
        }

        $surcharge = Shop::Container()->getDB()->selectSingleRow(
            'tversandartzahlungsart',
            'kVersandart',
            $shippingId,
            'kZahlungsart',
            $paymentId
        );

        if ($surcharge !== null && \is_object($surcharge)) {
            $basket = BasketHelper::getBasket();

            if (isset($surcharge->cAufpreisTyp) && $surcharge->cAufpreisTyp === 'prozent') {
                $amount = ($basket->total[1] * $surcharge->fAufpreis) / 100.0;
            } else {
                $amount = $surcharge->fAufpreis ?? 0;
            }

            $name = $paymentMethod->cName;

            if (isset($_SESSION['Zahlungsart'])) {
                $name                                  = $_SESSION['Zahlungsart']->angezeigterName;
                $_SESSION['Zahlungsart']->fAufpreis    = $surcharge->fAufpreis;
                $_SESSION['Zahlungsart']->cAufpreisTyp = $surcharge->cAufpreisTyp;
            }

            if ($amount !== 0) {
                Frontend::getCart()->erstelleSpezialPos(
                    $name,
                    1,
                    $amount,
                    Frontend::getCart()->gibVersandkostenSteuerklasse(),
                    \C_WARENKORBPOS_TYP_ZAHLUNGSART
                );
            }
        }

        if (!\function_exists('plausiNeukundenKupon')) {
            require_once \PFAD_ROOT . \PFAD_INCLUDES . 'bestellvorgang_inc.php';
        }

        \plausiNeukundenKupon();
    }

    public static function getBasket($helper = null): stdClass
    {
        if ($helper === null) {
            $helper = new CartHelper();
        }

        $cart     = $helper->getTotal(4);
        $rounding = static function ($prop) {
            return [
                CartHelper::NET   => \round($prop[CartHelper::NET], 2),
                CartHelper::GROSS => \round($prop[CartHelper::GROSS], 2),
            ];
        };

        $product = [
            CartHelper::NET   => 0,
            CartHelper::GROSS => 0,
        ];

        foreach ($cart->items as $i => $p) {
            $p->amount = $rounding($p->amount);

            $product[CartHelper::NET]   += $p->amount[CartHelper::NET] * $p->quantity;
            $product[CartHelper::GROSS] += $p->amount[CartHelper::GROSS] * $p->quantity;
        }

        $cart->article   = $rounding($product);
        $cart->shipping  = $rounding($cart->shipping);
        $cart->discount  = $rounding($cart->discount);
        $cart->surcharge = $rounding($cart->surcharge);
        $cart->total     = $rounding($cart->total);

        $calculated = [
            CartHelper::NET   => 0,
            CartHelper::GROSS => 0,
        ];

        $calculated[CartHelper::NET]   = $cart->article[CartHelper::NET]
            + $cart->shipping[CartHelper::NET]
            - $cart->discount[CartHelper::NET]
            + $cart->surcharge[CartHelper::NET];
        $calculated[CartHelper::GROSS] = $cart->article[CartHelper::GROSS]
            + $cart->shipping[CartHelper::GROSS]
            - $cart->discount[CartHelper::GROSS]
            + $cart->surcharge[CartHelper::GROSS];

        $calculated = $rounding($calculated);

        $difference = [
            CartHelper::NET   => $cart->total[CartHelper::NET] - $calculated[CartHelper::NET],
            CartHelper::GROSS => $cart->total[CartHelper::GROSS] - $calculated[CartHelper::GROSS],
        ];

        $difference = $rounding($difference);

        $addDifference = static function ($difference, $type) use (&$cart) {
            if ($difference[$type] < 0.0) {
                if ($cart->shipping[$type] >= $difference[$type] * -1) {
                    $cart->shipping[$type] += $difference[$type];
                } else {
                    $cart->discount[$type] += $difference[$type] * -1;
                }
            } else {
                $cart->surcharge[$type] += $difference[$type];
            }
        };

        $addDifference($difference, CartHelper::NET);
        $addDifference($difference, CartHelper::GROSS);

        return $cart;
    }
}
