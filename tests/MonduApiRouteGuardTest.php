<?php

/**
 * Framework-free end to end test for POST /mondu-api: the real routes from RoutesService, the
 * real router, Request and guards. Only JTL's plugin config, the exiting Response::json() and
 * the controllers are stubs, so a request either reaches a controller or is rejected by a guard.
 * Run:  php tests/MonduApiRouteGuardTest.php   (exit code 0 = all passed)
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
    class ResponseSent extends \Exception
    {
    }

    class Response
    {
        public const HTTP_NOT_FOUND = 404;
        public const HTTP_UNAUTHORIZED = 401;

        // The real one exits; throwing ends the request the same way.
        public static function json($data, $statusCode = 200)
        {
            throw new ResponseSent('', $statusCode);
        }
    }
}

namespace Plugin\MonduPayment\Src\Support\Debug {
    class Debugger
    {
        public function log($message)
        {
        }
    }
}

namespace Plugin\MonduPayment\Src\Controllers\Frontend {
    class Calls
    {
        public static array $made = [];
    }

    class InvoicesController
    {
        public function create()
        {
            Calls::$made[] = 'invoice-create';
        }

        public function cancel()
        {
            Calls::$made[] = 'cancel-invoice';
        }
    }

    class CreditNotesController
    {
        public function create()
        {
            Calls::$made[] = 'credit-note';
        }
    }

    class OrdersController
    {
        public function cancel()
        {
            Calls::$made[] = 'cancel-order';
        }
    }

    class WebhookController
    {
        public function index()
        {
            Calls::$made[] = 'webhook';
        }
    }
}

namespace {
    $src = __DIR__ . '/../Src';
    foreach ([
        '/Exceptions/RouteNotFoundException.php',
        '/Support/Http/Request.php',
        '/Services/ConfigService.php',
        '/Middlewares/CheckWebhookSecret.php',
        '/Middlewares/CheckMonduSignature.php',
        '/Support/Facades/Router/MiddlewareHandler.php',
        '/Support/Facades/Router/ParamsHandler.php',
        '/Support/Facades/Router/RouteHandler.php',
        '/Support/Facades/Router/Route.php',
        '/Services/RoutesService.php',
    ] as $file) {
        require $src . $file;
    }

    use JTL\Plugin\Helper;
    use Plugin\MonduPayment\Src\Controllers\Frontend\Calls;
    use Plugin\MonduPayment\Src\Helpers\ResponseSent;
    use Plugin\MonduPayment\Src\Services\RoutesService;
    use Plugin\MonduPayment\Src\Support\Http\Request;

    $secret = 'b3f1c2d4e5a6978812345678abcdef01';
    Helper::$config = ['webhooks_secret' => $secret];
    $plugin = new class {
        public function getId()
        {
            return 7;
        }
    };

    // Returns [controllers reached, status the request was ended with or null].
    $post = function (string $query, array $body = []) use ($plugin) {
        foreach (['data', 'rawData'] as $name) {
            $property = new ReflectionProperty(Request::class, $name);
            $property->setAccessible(true);
            $property->setValue(null, []);
        }
        parse_str($query, $_GET);
        $_POST = $body;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/mondu-api?' . $query;
        unset($_SERVER['HTTP_X_MONDU_SIGNATURE']);
        Calls::$made = [];

        $status = null;
        try {
            (new RoutesService())->frontEndRoutes($plugin);
        } catch (ResponseSent $e) {
            $status = $e->getCode();
        }
        return [Calls::$made, $status];
    };

    $failures = 0;
    $passed = 0;
    $check = function (string $name, array $expected, array $actual) use (&$failures, &$passed) {
        if ($expected === $actual) {
            $passed++;
            printf("ok    %s\n", $name);
        } else {
            $failures++;
            printf("FAIL  %s expected=%s got=%s\n", $name, json_encode($expected), json_encode($actual));
        }
    };

    $check('README request with the secret reaches the controller', [['cancel-order'], null],
        $post('return=cancel-order&webhooks_secret=' . $secret, ['order_number' => '1']));

    foreach (['cancel-order', 'cancel-invoice', 'invoice-create', 'credit-note'] as $route) {
        $check("{$route}: route as second parameter without the secret is rejected", [[], 422],
            $post("x=1&y={$route}", ['order_number' => '1']));
        $check("{$route}: redirect= without the secret is rejected", [[], 422],
            $post("redirect={$route}", ['order_number' => '1']));
        $check("{$route}: body return value overriding the query is rejected", [[], 422],
            $post("return={$route}", ['order_number' => '1', 'return' => 'x']));
    }

    $check('webhook as second parameter without a signature is rejected', [[], 401],
        $post('x=1&y=webhook'));

    echo "\n{$passed} passed, {$failures} failed\n";
    exit($failures === 0 ? 0 : 1);
}
