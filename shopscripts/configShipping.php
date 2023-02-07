<?php

declare(strict_types=1);

use JTL\Alert\Alert;
use JTL\DB\ReturnType;
use JTL\Language\LanguageHelper;
use JTL\Shop;

/** @global \JTL\Backend\AdminAccount $oAccount */
/** @global \JTL\Smarty\JTLSmarty $smarty */

define('PFAD_ROOT', '/var/www/html/');
require_once PFAD_ROOT . 'admin/includes/admininclude.php';
require_once PFAD_ROOT . PFAD_ADMIN . PFAD_INCLUDES . 'versandarten_inc.php';

$NONE = [
    "cName_ger" => "DHL",
    "cName_eng" => "DHL",
    "cLieferdauer_ger" => "2-3 Werktage",
    "cLieferdauer_eng" => "2-3 Working days"
];

$LANDS = array("DE");

$db = Shop::Container()->getDB();
$defaultCurrency = $db->select('twaehrung', 'cStandard', 'Y');
$shippingType_M = null;
$step = 'uebersicht';
$shippingMethod = null;
$alertHelper = Shop::Container()->getAlertService();
$countryHelper = Shop::Container()->getCountryService();
$languages = LanguageHelper::getAllLanguages();
$getText = Shop::Container()->getGetText();

$shippingMethods = $db->query(
    'SELECT * FROM tversandart ORDER BY nSort, cName',
    ReturnType::ARRAY_OF_OBJECTS
);

$shippingMethods = array_values($shippingMethods);

foreach ($shippingMethods as $shippingMethod) {
    $shippingMethodId = $shippingMethod->kVersandart;

    $step = 'neue Versandart';
    $shippingMethod_M = $db->select('tversandart', 'kVersandart', $shippingMethodId);

    $VersandartZahlungsarten = $db->selectAll(
        'tversandartzahlungsart',
        'kVersandart',
        $shippingMethodId,
        '*',
        'kZahlungsart'
    );

    $VersandartStaffeln = $db->selectAll(
        'tversandartstaffel',
        'kVersandart',
        $shippingMethodId,
        '*',
        'fBis'
    );

    $shippingType_M = getShippingTypes((int) $shippingMethod_M->kVersandberechnung);
    $shippingMethod_M->cVersandklassen = trim($shippingMethod_M->cVersandklassen);

    $zahlungsarten = $db->selectAll(
        'tzahlungsart',
        ['nActive', 'nNutzbar'],
        [1, 1],
        '*',
        'cAnbieter, nSort, cName, cModulId'
    );

    $monduPaymentMethods = array_filter($zahlungsarten, function ($paymentMethod) {
        return $paymentMethod->cAnbieter === "Mondu";
    });

    $monduPaymentMethodIds = array_map(function ($paymentMethod) {
        return $paymentMethod->kZahlungsart;
    }, $monduPaymentMethods);

    $shippingMethod = new stdClass();
    $shippingMethod->cName = htmlspecialchars(
        $shippingMethod_M->cName,
        ENT_COMPAT | ENT_HTML401,
        JTL_CHARSET
    );
    $shippingMethod->kVersandberechnung = (int) $shippingMethod_M->kVersandberechnung;
    $shippingMethod->cAnzeigen = $shippingMethod_M->cAnzeigen;
    $shippingMethod->cBild = $shippingMethod_M->cBild;
    $shippingMethod->nSort = (int) $shippingMethod_M->nSort;
    $shippingMethod->nMinLiefertage = (int) $shippingMethod_M->nMinLiefertage;
    $shippingMethod->nMaxLiefertage = (int) $shippingMethod_M->nMaxLiefertage;
    $shippingMethod->cNurAbhaengigeVersandart = $shippingMethod_M->cNurAbhaengigeVersandart;
    $shippingMethod->cSendConfirmationMail = $shippingMethod_M->cSendConfirmationMail ?? 'Y';
    $shippingMethod->cIgnoreShippingProposal = $shippingMethod_M->cIgnoreShippingProposal ?? 'N';
    $shippingMethod->eSteuer = $shippingMethod_M->eSteuer;
    $shippingMethod->fPreis = (float) str_replace(',', '.', $shippingMethod_M->fPreis ?? 0);
    $shippingMethod->fVersandkostenfreiAbX = 0;
    $shippingMethod->fDeckelung = 0;

    $shippingMethod->cLaender = '';

    $Laender = $LANDS;
    if (is_array($Laender)) {
        foreach ($Laender as $Land) {
            $shippingMethod->cLaender .= $Land . ' ';
        }
    }

    $VersandartZahlungsarten = [];

    foreach ($monduPaymentMethodIds as $kZahlungsart) {
        $versandartzahlungsart = new stdClass();
        $versandartzahlungsart->kZahlungsart = $kZahlungsart;

        $versandartzahlungsart->fAufpreis = null;
        $versandartzahlungsart->cAufpreisTyp = null;

        $VersandartZahlungsarten[] = $versandartzahlungsart;
    }

    $VersandartStaffeln = [];
    $upperLimits = [];
    $staffelDa = true;
    $shippingFreeValid = true;
    $fMaxVersandartStaffelBis = 0;
    if (
        $shippingType_M->cModulId === 'vm_versandberechnung_gewicht_jtl'
        || $shippingType_M->cModulId === 'vm_versandberechnung_warenwert_jtl'
        || $shippingType_M->cModulId === 'vm_versandberechnung_artikelanzahl_jtl'
    ) {
        $staffelDa = false;
    }

    $shippingMethod->cKundengruppen = '';
    $NONE['kKundengruppe'] = [-1];

    if (is_array($NONE['kKundengruppe'])) {
        if (in_array(-1, $NONE['kKundengruppe'])) {
            $shippingMethod->cKundengruppen = '-1';
        } else {
            $shippingMethod->cKundengruppen = ';' . implode(';', $NONE['kKundengruppe']) . ';';
        }
    }

    $shippingMethod->cVersandklassen = '-1';

    if (
        count($LANDS) >= 1
        && count($monduPaymentMethodIds) >= 1
        && $shippingMethod->cName
        && $staffelDa
        && $shippingFreeValid
    ) {
        $kVersandart = 0;
        if ($shippingMethodId === 0) {
            $kVersandart = $db->insert('tversandart', $shippingMethod);
            $alertHelper->addAlert(
                Alert::TYPE_SUCCESS,
                sprintf(__('successShippingMethodCreate'), $shippingMethod->cName),
                'successShippingMethodCreate'
            );
        } else {
            //updaten
            $kVersandart = $shippingMethodId;
            $db->update('tversandart', 'kVersandart', $kVersandart, $shippingMethod);
            $db->delete('tversandartzahlungsart', 'kVersandart', $kVersandart);
            $db->delete('tversandartstaffel', 'kVersandart', $kVersandart);
            $alertHelper->addAlert(
                Alert::TYPE_SUCCESS,
                sprintf(__('successShippingMethodChange'), $shippingMethod->cName),
                'successShippingMethodChange'
            );
        }

        if ($kVersandart > 0) {
            foreach ($VersandartZahlungsarten as $versandartzahlungsart) {
                $versandartzahlungsart->kVersandart = $kVersandart;
                $res = $db->insert('tversandartzahlungsart', $versandartzahlungsart);
            }

            foreach ($VersandartStaffeln as $versandartstaffel) {
                $versandartstaffel->kVersandart = $kVersandart;
                $db->insert('tversandartstaffel', $versandartstaffel);
            }
            $versandSprache = new stdClass();

            $versandSprache->kVersandart = $kVersandart;
            foreach ($languages as $language) {
                $code = $language->getCode();

                $versandSprache->cISOSprache = $code;
                $versandSprache->cName = $shippingMethod->cName;
                if ($NONE['cName_' . $code]) {
                    $versandSprache->cName = htmlspecialchars(
                        $NONE['cName_' . $code],
                        ENT_COMPAT | ENT_HTML401,
                        JTL_CHARSET
                    );
                }

                $versandSprache->cLieferdauer = '';
                if ($NONE['cLieferdauer_' . $code]) {
                    $versandSprache->cLieferdauer = htmlspecialchars(
                        $NONE['cLieferdauer_' . $code],
                        ENT_COMPAT | ENT_HTML401,
                        JTL_CHARSET
                    );
                }

                $versandSprache->cHinweistext = '';
                $versandSprache->cHinweistextShop = '';
                $db->delete('tversandartsprache', ['kVersandart', 'cISOSprache'], [$kVersandart, $code]);
                $db->insert('tversandartsprache', $versandSprache);
            }
            $step = 'uebersicht';
        }
        Shop::Container()->getCache()->flushTags([CACHING_GROUP_OPTION, CACHING_GROUP_ARTICLE]);
    }
}
