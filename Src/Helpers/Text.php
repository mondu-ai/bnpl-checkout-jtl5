<?php

namespace Plugin\MonduPayment\Src\Helpers;

use JTL\Checkout\Zahlungsart;
use JTL\Session\Frontend;
use JTL\Shop;

class Text
{
    public static function filterXSS($input, int $search = 0)
    {
        if (\is_array($input)) {
            foreach ($input as &$a) {
                $a = self::filterXSS($a);
            }

            return $input;
        }
        $input  = (string)$input;
        $string = \trim(\strip_tags($input));
        $string = $search === 1
            ? \str_replace(['\\\'', '\\'], '', $string)
            : \str_replace(['\"', '\\\'', '\\', '"', '\''], '', $string);

        if ($search === 1 && \mb_strlen($string) > 10) {
            $string = \mb_substr(\str_replace(['(', ')', ';'], '', $string), 0, 50);
        }

        return $string;
    }
}