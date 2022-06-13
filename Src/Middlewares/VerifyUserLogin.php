<?php

namespace Plugin\MonduPayment\Src\Middlewares;

use Plugin\MonduPayment\Src\Helpers\Response;
use JTL\Session\Frontend;

class VerifyUserLogin
{
    public static function handle()
    {
        $customer = Frontend::getCustomer();
        $customerId = $customer->kKunde;

        if (empty($customerId)) {
            return Response::json([
                'message' => 'unauthenticated',
            ], 403);
        }
    }
}
