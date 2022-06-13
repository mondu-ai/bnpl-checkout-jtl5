<?php

namespace Plugin\MonduPayment\Src\Middlewares;

use Plugin\MonduPayment\Src\Support\Http\Header;
use Plugin\MonduPayment\Src\Helpers\Redirect;
use Plugin\MonduPayment\Src\Support\Http\Server;

class VerifyAjaxRequest
{
    public static function handle()
    {
        if (!!stripos(Server::previous_url(), 'paypal')) {
            return;
        }
        if (!(Header::has('Content-Type') && Header::get('Content-Type') === 'application/json')) {
            Redirect::to('/404');
        }
    }     
}
