<?php

namespace Plugin\MonduPayment\Src\Middlewares;

use Plugin\MonduPayment\Src\Support\Http\Request;
use Plugin\MonduPayment\Src\Helpers\Response;
use Plugin\MonduPayment\Src\Services\ConfigService;

class CheckMonduSignature
{
    public static function handle()
    {
        $request = new Request();
        $configService = new ConfigService();
        $body = $request->getBody();
        $monduSignature = $request->header('X-Mondu-Signature');
        $localSecret = $configService->getWebhooksSecret();
        if (!self::validateSignature($body, $monduSignature, $localSecret)) {
            Response::json([
                'message' => 'Signature mismatch',
            ], Response::HTTP_UNAUTHORIZED);
        }
    }

    private static function validateSignature($body, $monduSignature, $localSecret) {
        $localSignature = hash_hmac('sha256', $body, $localSecret);

        if ($monduSignature !== $localSignature) {
            return false;
        }

        return true;
    }

    private static function isAllowed($data)
    {
        if (isset($data['return'])){
            if (in_array($data['return'], ['webhook']))
                return true;
        }

        return false;
    }
}
