<?php

namespace Plugin\MonduPayment\Src\Services;

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

        Route::get('token', 'Frontend\CheckoutController@token');

        Route::post('invoice-create', 'Frontend\InvoicesController@create');

        Route::post('cancel-invoice', 'Frontend\InvoicesController@cancel');

        Route::post('cancel-order', 'Frontend\OrdersController@cancel');
        
        Route::resolve(Request::uri(), Request::type(), $pluginId);
        
    }
}
