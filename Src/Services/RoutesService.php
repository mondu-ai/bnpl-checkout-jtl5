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

        Route::group(['VerifyFormCsrfToken'], function () {
            Route::get('token', 'Frontend\CheckoutController@token');
        });
        
        Route::resolve(Request::uri(), Request::type(), $pluginId);
        
    }
}
