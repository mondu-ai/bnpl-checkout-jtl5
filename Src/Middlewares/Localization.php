<?php

namespace Plugin\MonduPayment\Src\Middlewares;

use Plugin\MonduPayment\Src\Support\Facades\Localization\Translate;
use JTL\Shop;

class Localization
{
    public static function handle()
    {
        $smarty        = Shop::Smarty();

        $translations = Translate::getTranslations('frontend');

        $smarty->assign('translations', $translations);
    }
}
