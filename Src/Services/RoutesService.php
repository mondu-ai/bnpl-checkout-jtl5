<?php

namespace Plugin\MonduPayment\Src\Services;

use Plugin\MonduPayment\Src\Exceptions\RouteNotFoundException;
use Plugin\MonduPayment\Src\Helpers\Response;
use Plugin\MonduPayment\Src\Support\Facades\Router\Route;
use Plugin\MonduPayment\Src\Support\Http\Request;

class RoutesService
{
    public function adminRoutes($plugin)
    {
        $pluginId = $plugin->getId();
    }

    public function frontEndRoutes($plugin)
    {
        $pluginId = $plugin->getId();

        Route::post('token', 'Frontend\CheckoutController@token');

        Route::group(['CheckWebhookSecret'], function () {
            Route::post('invoice-create', 'Frontend\InvoicesController@create');
            Route::post('cancel-invoice', 'Frontend\InvoicesController@cancel');
            Route::post('cancel-order', 'Frontend\OrdersController@cancel');
        });

        Route::group(['CheckMonduSignature'], function () {
            Route::post('webhook', 'Frontend\WebhookController@index');
        });

        try {
            Route::resolve(Request::uri(), Request::type(), $pluginId);
        } catch (RouteNotFoundException $e) {
            Response::json(
                [
                    'message' => 'Route not Found'
                ],
                Response::HTTP_NOT_FOUND
            );
        }
    }
}
