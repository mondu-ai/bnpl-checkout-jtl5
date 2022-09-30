<?php

namespace Plugin\MonduPayment\Src\Middlewares;

use Plugin\MonduPayment\Src\Support\Facades\Authentication\CsrfAuthentication;
use Plugin\MonduPayment\Src\Support\Facades\Localization\Translate;
use Plugin\MonduPayment\Src\Helpers\ArrayValidator;
use Plugin\MonduPayment\Src\Support\Http\Request;
use Plugin\MonduPayment\Src\Validations\Alerts;

class VerifyFormCsrfToken
{
    public static function handle()
    {
        if ((Request::type() === 'POST') || (Request::type() === 'PUT') || (Request::type() === 'DELETE')) {
            $request = new Request();
            $requestData = $request->all();
            $arrayValidator = new ArrayValidator($requestData);
            if ($arrayValidator->array_keys_exists('jtl_token')) {
                if (!CsrfAuthentication::validate_token($requestData['jtl_token'])) {
                    Alerts::show('danger', ['message' => Translate::translate('messages', 'unauthenticated')]);
                }
            } else {
                Alerts::show('danger', ['message' => Translate::translate('messages', 'unauthenticated')]);
            }
        }
    }
}
