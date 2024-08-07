<?php

namespace Plugin\MonduPayment\Src\Helpers;

use Plugin\MonduPayment\Src\Support\Http\Header;

class Response
{
    public const HTTP_OK = 200;
    public const HTTP_BAD_REQUEST = 400;
    public const HTTP_NOT_FOUND = 404;
    public const HTTP_UNPROCESSABLE_ENTITY = 422; // RFC4918
    public const HTTP_UNAUTHORIZED = 401;

    public static function json($data, $statusCode = 200)
    {
        $header = new Header();
        $header->set('Content-Type', 'application/json; charset=utf-8')
        ->statusCode($statusCode);
        echo json_encode($data);
        exit;
    }
}
