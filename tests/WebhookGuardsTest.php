<?php

/**
 * Framework-free test for the two webhook guards, CheckWebhookSecret and CheckMonduSignature.
 *
 * JTL's plugin config and the exiting Response::json() are replaced by recording stubs, so the
 * guards' decisions can be observed without a shop. The real Request reads $_GET and $_POST.
 * Run:  php tests/WebhookGuardsTest.php   (exit code 0 = all passed)
 */

namespace JTL\Plugin {
    class Helper
    {
        public static array $config = [];

        public static function getPluginById($id)
        {
            return new class {
                public function getConfig()
                {
                    return new class {
                        public function getValue($key)
                        {
                            return Helper::$config[$key] ?? null;
                        }
                    };
                }
            };
        }
    }
}

namespace Plugin\MonduPayment\Src\Helpers {
    class Response
    {
        public const HTTP_UNAUTHORIZED = 401;

        public static ?int $status = null;

        public static function json($data, $statusCode = 200)
        {
            self::$status = $statusCode;
        }
    }
}

namespace {
    require __DIR__ . '/../Src/Support/Http/Request.php';
    require __DIR__ . '/../Src/Services/ConfigService.php';
    require __DIR__ . '/../Src/Middlewares/CheckWebhookSecret.php';
    require __DIR__ . '/../Src/Middlewares/CheckMonduSignature.php';

    use JTL\Plugin\Helper;
    use Plugin\MonduPayment\Src\Helpers\Response;
    use Plugin\MonduPayment\Src\Middlewares\CheckMonduSignature;
    use Plugin\MonduPayment\Src\Middlewares\CheckWebhookSecret;
    use Plugin\MonduPayment\Src\Support\Http\Request;

    $failures = 0;
    $passed = 0;

    $check = function (string $name, $expected, $actual) use (&$failures, &$passed) {
        if ($expected === $actual) {
            $passed++;
            printf("ok    %s\n", $name);
        } else {
            $failures++;
            printf("FAIL  %s expected=%s got=%s\n", $name, var_export($expected, true), var_export($actual, true));
        }
    };

    // Request keeps its data in static arrays; start every case from an empty request.
    $resetRequest = function () {
        foreach (['data', 'rawData'] as $name) {
            $property = new ReflectionProperty(Request::class, $name);
            $property->setAccessible(true);
            $property->setValue(null, []);
        }
    };

    // Returns the status the guard rejected with, or null when it let the request through.
    $runSecretGuard = function ($configuredSecret, array $query, array $body = []) use ($resetRequest) {
        Helper::$config = ['webhooks_secret' => $configuredSecret];
        $resetRequest();
        $_GET = $query;
        $_POST = $body;
        Response::$status = null;
        CheckWebhookSecret::handle();
        return Response::$status;
    };

    $secret = 'b3f1c2d4e5a6978812345678abcdef01';

    $check('secret guard: matching secret passes', null,
        $runSecretGuard($secret, ['return' => 'cancel-order', 'webhooks_secret' => $secret]));
    $check('secret guard: wrong secret is rejected', 422,
        $runSecretGuard($secret, ['return' => 'cancel-order', 'webhooks_secret' => 'nope']));
    $check('secret guard: missing secret is rejected', 422,
        $runSecretGuard($secret, ['return' => 'cancel-order'], ['order_number' => '1']));
    // Body return=x overrides the query's return=cancel-order, the old isAllowed() then skipped the check.
    $check('secret guard: body return value does not switch the check off', 422,
        $runSecretGuard($secret, ['return' => 'cancel-order'], ['order_number' => '1', 'return' => 'x']));
    // ?redirect=cancel-order never sets return at all.
    $check('secret guard: request without return is still checked', 422,
        $runSecretGuard($secret, ['redirect' => 'cancel-order'], ['order_number' => '1']));
    $check('secret guard: unconfigured secret rejects an empty one', 422,
        $runSecretGuard(null, ['return' => 'cancel-order', 'webhooks_secret' => '']));
    $check('secret guard: unconfigured secret rejects a request without one', 422,
        $runSecretGuard(null, ['return' => 'cancel-order']));
    $check('secret guard: numeric looking secrets are compared as strings', 422,
        $runSecretGuard('0e123', ['return' => 'cancel-order', 'webhooks_secret' => '0e456']));
    $check('secret guard: secret with HTML special characters matches', null,
        $runSecretGuard('a&b"c<d', ['return' => 'cancel-order', 'webhooks_secret' => 'a&b"c<d']));
    $check('secret guard: secret sent as an array is rejected', 422,
        $runSecretGuard($secret, ['return' => 'cancel-order', 'webhooks_secret' => [$secret]]));

    $runSignatureGuard = function ($configuredSecret, ?string $signature) use ($resetRequest) {
        Helper::$config = ['webhooks_secret' => $configuredSecret];
        unset($_SERVER['HTTP_X_MONDU_SIGNATURE']);
        if ($signature !== null) {
            $_SERVER['HTTP_X_MONDU_SIGNATURE'] = $signature;
        }
        $resetRequest();
        $_GET = [];
        $_POST = [];
        Response::$status = null;
        CheckMonduSignature::handle();
        return Response::$status;
    };

    // The CLI has no request body, so the signed payload is the empty string.
    $check('signature guard: valid signature passes', null,
        $runSignatureGuard($secret, hash_hmac('sha256', '', $secret)));
    $check('signature guard: wrong signature is rejected', 401,
        $runSignatureGuard($secret, hash_hmac('sha256', '', 'other')));
    $check('signature guard: missing header is rejected', 401,
        $runSignatureGuard($secret, null));
    $check('signature guard: unconfigured secret rejects a signature made with an empty key', 401,
        $runSignatureGuard(null, hash_hmac('sha256', '', '')));

    echo "\n{$passed} passed, {$failures} failed\n";
    exit($failures === 0 ? 0 : 1);
}
