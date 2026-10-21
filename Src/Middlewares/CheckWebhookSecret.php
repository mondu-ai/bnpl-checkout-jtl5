<?php

namespace Plugin\MonduPayment\Src\Middlewares;

use Plugin\MonduPayment\Src\Support\Http\Request;
use Plugin\MonduPayment\Src\Helpers\Response;
use Plugin\MonduPayment\Src\Services\ConfigService;

class CheckWebhookSecret
{
    /**
     * The routes this guard protects are decided by Route::group() alone. The check must not
     * re-derive the action from request data, or a request whose query and body disagree skips it.
     */
    public static function handle()
    {
        $request = new Request;
        $configService = new ConfigService();
        // Raw values: the sanitized ones turn & ' " < > into entities and would never match.
        $data = $request->allRaw();

        if (!isset($data['webhooks_secret'])) {
            return Response::json([
                'message' => 'Webhooks secret is missing.',
            ], 422);
        }

        $expected = (string) $configService->getWebhooksSecret();
        if ($expected === '' || !is_string($data['webhooks_secret'])
            || !hash_equals($expected, $data['webhooks_secret'])) {
            return Response::json([
                'message' => 'Webhooks secret is wrong.',
            ], 422);
        }
    }
}
