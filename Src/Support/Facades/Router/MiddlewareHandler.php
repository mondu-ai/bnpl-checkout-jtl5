<?php

namespace Plugin\MonduPayment\Src\Support\Facades\Router;

class MiddlewareHandler
{
    private const MIDDLEWARES_NAMESPACE = 'Plugin\\MonduPayment\\Src\\Middlewares';
    private const METHOD = 'handle';

    public static function call(string $middleware)
    {
        if (is_string($middleware)) {
            $middleware = self::MIDDLEWARES_NAMESPACE .'\\'. $middleware;
            if (class_exists($middleware)) {
                if (method_exists($middleware, self::METHOD)) {
                    return call_user_func([$middleware, self::METHOD]);
                }
            }
        }
    }
}