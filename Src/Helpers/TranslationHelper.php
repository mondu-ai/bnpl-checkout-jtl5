<?php declare(strict_types=1);

namespace Plugin\MonduPayment\Src\Helpers;

final class TranslationHelper
{
  public static function getLocaleFromISO(string $isoCode): string
    {
        static $locales = [
            'ENG' => 'en-GB',
            'GER' => 'de-DE',
            'DUT' => 'nl-NL',
            'FRE' => 'fr-FR',
            'FRA' => 'fr-FR'
        ];

        return $locales[\strtoupper($isoCode)] ?? 'de-DE';
    }
}