<?php

namespace Plugin\MonduPayment\Src\Support\Facades\Router;

use Plugin\MonduPayment\Src\Exceptions\RouteNotFoundException;

class Route
{
    /**
     * plugin routes
     *
     * @var array
     */
    private static array $routes = [];

    /**
     * @var array|null
     */
    private static array $setMiddlewares = [];

    /**
     * @var array|null
     */
    private static array $middlewares;

    /**
     * get request
     *
     * @param  $route
     * @param  $action
     * @return void
     */
    public static function get($route, $action): void
    {
        self::register($route, 'GET', $action);
    }

    /**
     * post request
     *
     * @param  $route
     * @param  $action
     * @return void
     */
    public static function post($route, $action): void
    {
        self::register($route, 'POST', $action);
    }

    /**
     * put request
     *
     * @param  $route
     * @param  $action
     * @return void
     */
    public static function put($route, $action): void
    {
        self::register($route, 'PUT', $action);
    }

    /**
     * patch request
     *
     * @param  $route
     * @param  $action
     * @return void
     */
    public static function patch($route, $action): void
    {
        self::register($route, 'PATCH', $action);
    }

    /**
     * delete request
     *
     * @param  $route
     * @param  $action
     * @return void
     */
    public static function delete($route, $action): void
    {
        self::register($route, 'DELETE', $action);
    }

    /**
     * register routes
     *
     * @param string $route
     * @param string $Request
     * @param  $action
     * @return void
     */
    public static function register(string $route, string $requestType, $action): void
    {
        self::$routes[$requestType][$route] = $action;
        self::$middlewares[$requestType][$route] = array_merge(...self::$setMiddlewares);
    }

    /**
     * resolve routes
     *
     * @param [string] $route
     * @param [string] $Request
     * @return RouteHandler
     */
    public static function resolve(string $fetch, string $requestType, ?int $pluginId = null)
    {
        if (!!stripos($fetch, '?') === false) {
            return;
        }

        $fetch = explode('?', $fetch)[1] ?? null;
        if (!$fetch) {
            return;
        }

        if (stripos($fetch, 'return') === 0) {
            $fetch = explode('=', $fetch)[1];
            $route = explode('&', $fetch)[0];
            $action = self::$routes[$requestType][$route] ?? null;
            if (!$action) {
                throw new RouteNotFoundException();
            }
            return RouteHandler::call($action, $pluginId, self::$middlewares[$requestType][$route]);
        }

        if (stripos($fetch, 'redirect') === 0) {
            $fetch = explode('=', $fetch)[1];
            $route = explode('&', $fetch)[0];

            $action = self::$routes[$requestType][$route] ?? null;
        
            if (!$action) {
                throw new RouteNotFoundException();
            }
            return RouteHandler::call($action, $pluginId, self::$middlewares[$requestType][$route]);
        }

        if (!!stripos($fetch, '&') === true) {
            $fetch = explode('&', $fetch)[1];
        } else {
            $route = explode('=', $fetch)[1];
            if ((int)$pluginId === (int)$route) {
                return;
            }
        }
        $route = explode('=', $fetch)[1];
     
        $action = self::$routes[$requestType][$route] ?? null;

        if (!$action) {
            throw new RouteNotFoundException();
        }
        return RouteHandler::call($action, $pluginId);
    }

    public static function execute($controllerMethod, int $pluginId = null)
    {
        return RouteHandler::call($controllerMethod, $pluginId);
    }

    public static function  routes_list(): array
    {
        return self::$routes;
    }

    public static function group(array $middlewares, callable $callback)
    {
        self::$setMiddlewares[] = $middlewares;

        call_user_func($callback);

        array_pop(self::$setMiddlewares);
    }
}
