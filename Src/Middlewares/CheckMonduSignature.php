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
        // An HMAC keyed with an empty secret can be computed by anyone from the body alone.
        if (!is_string($localSecret) || $localSecret === '' || !is_string($monduSignature)) {
            return false;
        }

        $localSignature = hash_hmac('sha256', (string) $body, $localSecret);

        return hash_equals($localSignature, $monduSignature);
    }
}
