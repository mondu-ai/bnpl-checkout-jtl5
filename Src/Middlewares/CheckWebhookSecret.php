<?php

namespace Plugin\MonduPayment\Src\Middlewares;

use Plugin\MonduPayment\Src\Support\Http\Request;
use Plugin\MonduPayment\Src\Helpers\Response;
use Plugin\MonduPayment\Src\Services\ConfigService;


class CheckWebhookSecret
{
    public static function handle()
    {
        $request = new Request;
        $configService = new ConfigService();

        $data = $request->all();
        
        if (self::isAllowed($data)) {
            if (isset($data['webhooks_secret']))
            {
                $ws = $data['webhooks_secret'];
    
                if ($ws != $configService->getWebhooksSecret()){
                    return Response::json([
                        'message' => 'Webhooks secret is wrong.',
                    ], 422);
                }
            } else {
              return Response::json([
                  'message' => 'Webhooks secret is missing.',
              ], 422);
            }
        }
    }

    public static function isAllowed($data) 
    {

        if (isset($data['return'])){
            if (in_array($data['return'], ['invoice-create', 'cancel-invoice', 'cancel-order']))
                return true;    
        }

        return false;
    }
}
