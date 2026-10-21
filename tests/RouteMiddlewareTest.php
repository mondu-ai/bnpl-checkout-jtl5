<?php

/**
 * Framework-free test for the router's middleware dispatch.
 *
 * Every dispatch branch of Route::resolve() (return=, redirect= and the fallback that picks
 * the route from the second query parameter) must run the middleware registered for the
 * route before the controller. The router has no JTL/DB/HTTP dependencies on these paths,
 * so it is exercised directly with a recording guard and a recording controller.
 * Run:  php tests/RouteMiddlewareTest.php   (exit code 0 = all passed)
 */

namespace Plugin\MonduPayment\Src\Middlewares {
    class TestGate
    {
        public static array $calls = [];

        public static function handle()
        {
            self::$calls[] = 'gate';
            // The real guards end the request with Response::json() + exit on rejection.
            throw new \DomainException('rejected by gate');
        }
    }
}

namespace Plugin\MonduPayment\Src\Controllers\Frontend {
    class RecordingController
    {
        public static array $calls = [];

        public function cancel()
        {
            self::$calls[] = 'controller';
            return 'dispatched';
        }
    }
}

namespace {
    require __DIR__ . '/../Src/Support/Facades/Router/MiddlewareHandler.php';
    require __DIR__ . '/../Src/Support/Facades/Router/ParamsHandler.php';
    require __DIR__ . '/../Src/Support/Facades/Router/RouteHandler.php';
    require __DIR__ . '/../Src/Support/Facades/Router/Route.php';

    use Plugin\MonduPayment\Src\Controllers\Frontend\RecordingController;
    use Plugin\MonduPayment\Src\Middlewares\TestGate;
    use Plugin\MonduPayment\Src\Support\Facades\Router\Route;
    use Plugin\MonduPayment\Src\Support\Facades\Router\RouteHandler;

    Route::group(['TestGate'], function () {
        Route::post('cancel-order', 'Frontend\RecordingController@cancel');
    });
    Route::group(['MissingGate'], function () {
        Route::post('unresolvable-guard', 'Frontend\RecordingController@cancel');
    });
    Route::post('open-route', 'Frontend\RecordingController@cancel');

    $failures = 0;
    $passed = 0;

    $check = function (string $name, bool $ok, string $detail = '') use (&$failures, &$passed) {
        if ($ok) {
            $passed++;
            printf("ok    %s\n", $name);
        } else {
            $failures++;
            printf("FAIL  %s %s\n", $name, $detail);
        }
    };

    // Returns [calls in order, exception class or null].
    $dispatch = function (string $uri) {
        TestGate::$calls = [];
        RecordingController::$calls = [];
        $error = null;
        try {
            Route::resolve($uri, 'POST', 7);
        } catch (\Throwable $e) {
            $error = get_class($e);
        }
        return [array_merge(TestGate::$calls, RecordingController::$calls), $error];
    };

    $guarded = [
        'return branch'               => '/mondu-api?return=cancel-order&webhooks_secret=x',
        'redirect branch'             => '/mondu-api?redirect=cancel-order',
        'fallback, route second'      => '/mondu-api?x=1&y=cancel-order',
        'fallback, single parameter'  => '/mondu-api?y=cancel-order',
    ];
    foreach ($guarded as $name => $uri) {
        [$calls, $error] = $dispatch($uri);
        $check(
            "guard runs and blocks the controller: {$name}",
            $calls === ['gate'] && $error === \DomainException::class,
            'calls=' . json_encode($calls) . ' error=' . var_export($error, true)
        );
    }

    [$calls, $error] = $dispatch('/mondu-api?x=1&y=open-route');
    $check(
        'route without middleware still dispatches through the fallback',
        $calls === ['controller'] && $error === null,
        'calls=' . json_encode($calls) . ' error=' . var_export($error, true)
    );

    [$calls, $error] = $dispatch('/mondu-api?return=unresolvable-guard');
    $check(
        'unresolvable middleware fails closed',
        $calls === [] && $error === \RuntimeException::class,
        'calls=' . json_encode($calls) . ' error=' . var_export($error, true)
    );

    $error = null;
    try {
        RouteHandler::call('Frontend\RecordingController@cancel', 7);
    } catch (\ArgumentCountError $e) {
        $error = get_class($e);
    }
    $check('RouteHandler::call requires the middleware list', $error === \ArgumentCountError::class);

    echo "\n{$passed} passed, {$failures} failed\n";
    exit($failures === 0 ? 0 : 1);
}
