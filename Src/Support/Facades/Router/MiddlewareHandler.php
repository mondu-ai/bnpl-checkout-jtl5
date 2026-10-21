<?php

namespace Plugin\MonduPayment\Src\Support\Facades\Router;

class MiddlewareHandler
{
    private const MIDDLEWARES_NAMESPACE = 'Plugin\\MonduPayment\\Src\\Middlewares';
    private const METHOD = 'handle';

    /**
     * A middleware rejects a request by ending it (Response::json() exits); its return value is ignored.
     */
    public static function call(string $middleware)
    {
        $class = self::MIDDLEWARES_NAMESPACE .'\\'. $middleware;
        if (!class_exists($class) || !method_exists($class, self::METHOD)) {
            // An unresolvable guard must not let the request through.
            throw new \RuntimeException("Middleware {$middleware} cannot be resolved.");
        }

        return call_user_func([$class, self::METHOD]);
    }
}
