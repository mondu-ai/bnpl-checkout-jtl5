<?php

namespace Plugin\MonduPayment\Src\Middlewares;

use Plugin\MonduPayment\Src\Support\Facades\Authentication\CsrfAuthentication;
use Plugin\MonduPayment\Src\Support\Http\Request;
use Plugin\MonduPayment\Src\Support\Http\Header;
use Plugin\MonduPayment\Src\Helpers\Response;

class VerifyAjaxCsrfToken
{
    public static function handle()
    {
        if ((Request::type() === 'POST') || (Request::type() === 'PUT') || (Request::type() === 'DELETE')) {
            if (!(Header::has('jtl_token') && CsrfAuthentication::validate_token(Header::get('jtl_token')))) {
                return Response::json([
                    'message' => 'unauthenticated',
                ], 403);
            }
        }
    }
}
