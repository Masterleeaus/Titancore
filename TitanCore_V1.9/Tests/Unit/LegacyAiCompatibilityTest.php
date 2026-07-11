<?php

namespace {
    if (! function_exists('config')) {
        function config(?string $key = null, mixed $default = null): mixed
        {
            $config = $GLOBALS['__titan_test_config'] ?? [];

            if ($key === null) {
                return $config;
            }

            $segments = explode('.', $key);
            $value = $config;

            foreach ($segments as $segment) {
                if (! is_array($value) || ! array_key_exists($segment, $value)) {
                    return $default;
                }

                $value = $value[$segment];
            }

            return $value;
        }
    }

    if (! function_exists('now')) {
        function now(): object
        {
            return new class {
                public function __toString(): string
                {
                    return '2026-01-01 00:00:00';
                }
            };
        }
    }
}

namespace Illuminate\Routing {
    class Controller {}
}

namespace Illuminate\Http {
    class Request {}
    class JsonResponse {}
}

namespace Illuminate\Support {
    class Arr
    {
        public static function get(array $array, string|int|null $key, mixed $default = null): mixed
        {
            if ($key === null) {
                return $array;
            }

            $segments = explode('.', (string) $key);
            $value = $array;

            foreach ($segments as $segment) {
                if (! is_array($value) || ! array_key_exists($segment, $value)) {
                    return $default;
                }

                $value = $value[$segment];
            }

            return $value;
        }
    }

    class Str
    {
        public static function startsWith(string $haystack, string|array $needles): bool
        {
            foreach ((array) $needles as $needle) {
                if ($needle !== '' && str_starts_with($haystack, $needle)) {
                    return true;
                }
            }

            return false;
        }
    }
}

namespace Illuminate\Support\Facades {
    class Auth
    {
        public static bool $checked = false;
        public static mixed $user = null;

        public static function check(): bool
        {
            return self::$checked;
        }

        public static function id(): mixed
        {
            return self::$user->id ?? null;
        }

        public static function user(): mixed
        {
            return self::$user;
        }
    }

    class Session
    {
        public static array $values = [];

        public static function get(string $key, mixed $default = null): mixed
        {
            return self::$values[$key] ?? $default;
        }
    }

    class DB
    {
        public static function getSchemaBuilder(): object
        {
            return new class {
                public function hasTable(string $table): bool
                {
                    return false;
                }
            };
        }

        public static function table(string $table): object
        {
            return new class {
                public function insertGetId(array $payload): int
                {
                    return 1;
                }

                public function where(string $column, mixed $value = null): self
                {
                    return $this;
                }

                public function update(array $payload): int
                {
                    return 1;
                }

                public function value(string $column): mixed
                {
                    return null;
                }
            };
        }
    }

    class Log
    {
        public static array $entries = [];

        public static function info(string $message, array $context = []): void
        {
            self::$entries[] = ['info', $message, $context];
        }

        public static function debug(string $message, array $context = []): void
        {
            self::$entries[] = ['debug', $message, $context];
        }
    }

    class Route
    {
        public static array $routes = [];
        private static array $groupStack = [
            ['prefix' => '', 'as' => '', 'middleware' => []],
        ];

        public static function reset(): void
        {
            self::$routes = [];
            self::$groupStack = [['prefix' => '', 'as' => '', 'middleware' => []]];
        }

        public static function prefix(string $prefix): RouteGroupBuilder
        {
            return (new RouteGroupBuilder())->prefix($prefix);
        }

        public static function middleware(array $middleware): RouteGroupBuilder
        {
            return (new RouteGroupBuilder())->middleware($middleware);
        }

        public static function as(string $name): RouteGroupBuilder
        {
            return (new RouteGroupBuilder())->as($name);
        }

        public static function group(array $attributes, callable $callback): void
        {
            self::pushGroup($attributes);
            $callback();
            self::popGroup();
        }

        public static function get(string $uri, mixed $action): PendingRoute
        {
            return self::record(['GET'], $uri, $action);
        }

        public static function post(string $uri, mixed $action): PendingRoute
        {
            return self::record(['POST'], $uri, $action);
        }

        public static function put(string $uri, mixed $action): PendingRoute
        {
            return self::record(['PUT'], $uri, $action);
        }

        public static function delete(string $uri, mixed $action): PendingRoute
        {
            return self::record(['DELETE'], $uri, $action);
        }

        public static function patch(string $uri, mixed $action): PendingRoute
        {
            return self::record(['PATCH'], $uri, $action);
        }

        public static function match(array $methods, string $uri, mixed $action): PendingRoute
        {
            return self::record($methods, $uri, $action);
        }

        public static function pushGroup(array $attributes): void
        {
            $current = self::currentGroup();
            $prefix = trim(($current['prefix'] !== '' ? $current['prefix'] . '/' : '') . trim((string) ($attributes['prefix'] ?? ''), '/'), '/');

            self::$groupStack[] = [
                'prefix' => $prefix,
                'as' => $current['as'] . ($attributes['as'] ?? ''),
                'middleware' => array_merge($current['middleware'], (array) ($attributes['middleware'] ?? [])),
            ];
        }

        public static function popGroup(): void
        {
            array_pop(self::$groupStack);
        }

        public static function currentGroup(): array
        {
            return self::$groupStack[array_key_last(self::$groupStack)];
        }

        private static function record(array $methods, string $uri, mixed $action): PendingRoute
        {
            $group = self::currentGroup();
            $path = '/' . trim($group['prefix'] . '/' . trim($uri, '/'), '/');
            $path = preg_replace('#//+#', '/', $path) ?: '/';

            self::$routes[] = [
                'methods' => $methods,
                'uri' => $path,
                'action' => $action,
                'name' => null,
            ];

            return new PendingRoute(count(self::$routes) - 1);
        }

        public static function setRouteName(int $index, string $name): void
        {
            self::$routes[$index]['name'] = self::currentGroup()['as'] . $name;
        }
    }

    class RouteGroupBuilder
    {
        private array $attributes = [];

        public function prefix(string $prefix): self
        {
            $this->attributes['prefix'] = $prefix;

            return $this;
        }

        public function middleware(array $middleware): self
        {
            $this->attributes['middleware'] = $middleware;

            return $this;
        }

        public function as(string $name): self
        {
            $this->attributes['as'] = $name;

            return $this;
        }

        public function group(callable $callback): void
        {
            Route::pushGroup($this->attributes);
            $callback();
            Route::popGroup();
        }
    }

    class PendingRoute
    {
        public function __construct(private int $index) {}

        public function where(string $parameter, string $pattern): self
        {
            return $this;
        }

        public function name(string $name): self
        {
            Route::setRouteName($this->index, $name);

            return $this;
        }
    }
}

namespace Modules\TitanCore\Tests\Unit {
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
    use Modules\TitanCore\Services\MagicAiClient;
    use Modules\TitanCore\Services\Providers\TitanCoreAiProvider;
    use Modules\TitanCore\Services\TitanCoreModelGateway;
    use Modules\TitanCore\Services\TitanCoreRouter;
    use Modules\TitanCore\Services\TitanAiClient;
    use PHPUnit\Framework\TestCase;
    use ReflectionClass;
    use ReflectionProperty;

    class LegacyAiCompatibilityTest extends TestCase
    {
        protected function setUp(): void
        {
            parent::setUp();

            $GLOBALS['__titan_test_config'] = [];
            Auth::$checked = false;
            Auth::$user = null;
            Route::reset();
        }

        public function test_magic_compatibility_controllers_extend_canonical_controllers(): void
        {
            $this->loadControllerClasses();

            $this->assertTrue((new ReflectionClass(\Modules\TitanCore\Http\Controllers\Admin\MagicAiConsoleController::class))
                ->isSubclassOf(\Modules\TitanCore\Http\Controllers\Admin\TitanAiConsoleController::class));
            $this->assertTrue((new ReflectionClass(\Modules\TitanCore\Http\Controllers\Tenant\MagicAiLauncherController::class))
                ->isSubclassOf(\Modules\TitanCore\Http\Controllers\Tenant\TitanAiLauncherController::class));
            $this->assertTrue((new ReflectionClass(\Modules\TitanCore\Http\Controllers\Api\MagicAiProxyController::class))
                ->isSubclassOf(\Modules\TitanCore\Http\Controllers\Api\TitanAiProxyController::class));
        }

        public function test_magic_client_remains_a_lightweight_compatibility_alias(): void
        {
            $this->loadClientClasses();

            $client = new MagicAiClient('https://gateway.example.test', 'secret-key', 45);
            $baseUrl = new ReflectionProperty(TitanAiClient::class, 'baseUrl');
            $apiKey = new ReflectionProperty(TitanAiClient::class, 'apiKey');
            $timeout = new ReflectionProperty(TitanAiClient::class, 'timeoutSeconds');

            $baseUrl->setAccessible(true);
            $apiKey->setAccessible(true);
            $timeout->setAccessible(true);

            $this->assertInstanceOf(TitanAiClient::class, $client);
            $this->assertSame('https://gateway.example.test', $baseUrl->getValue($client));
            $this->assertSame('secret-key', $apiKey->getValue($client));
            $this->assertSame(45, $timeout->getValue($client));
        }

        public function test_router_delegates_legacy_ai_requests_through_the_gateway(): void
        {
            $this->loadGatewayAndRouterClasses();

            $gateway = new class extends TitanCoreModelGateway {
                public array $calls = [];

                public function __construct() {}

                public function invokeProxyRequest(array $request, array $config = [], array $context = []): array
                {
                    $this->calls[] = compact('request', 'config', 'context');

                    return ['ok' => true, 'status' => 200, 'body' => ['proxied' => true]];
                }
            };

            $GLOBALS['__titan_test_config'] = [
                'titancore' => [
                    'providers' => [
                        'titanai' => [
                            'enabled' => true,
                            'base_url' => 'https://gateway.example.test',
                            'api_key' => 'config-key',
                        ],
                    ],
                ],
            ];

            $router = new TitanCoreRouter($gateway);
            $result = $router->invokeTool([
                'method' => 'POST',
                'path' => '/v1/chat',
                'payload' => ['message' => 'hello'],
            ]);

            $this->assertTrue($result['ok']);
            $this->assertCount(1, $gateway->calls);
            $this->assertSame('config-key', $gateway->calls[0]['config']['api_key']);
            $this->assertSame('titanai', $gateway->calls[0]['context']['provider']);
            $this->assertSame('proxy', $gateway->calls[0]['context']['feature']);
        }

        public function test_gateway_logs_proxy_usage_when_forwarding_requests(): void
        {
            $this->loadGatewayAndRouterClasses();
            $this->loadProviderClass();

            $provider = new class extends TitanCoreAiProvider {
                public function __construct() {}

                public function invoke(array $request, array $config): array
                {
                    return [
                        'ok' => true,
                        'status' => 200,
                        'body' => ['proxied' => true],
                        'provider' => 'titanai',
                    ];
                }
            };

            $gateway = new class($provider) extends TitanCoreModelGateway {
                public array $usageCalls = [];

                public function __construct(TitanCoreAiProvider $toolProvider)
                {
                    $this->toolProvider = $toolProvider;
                }

                protected function logUsage(string $feature, array $result, array $context): void
                {
                    $this->usageCalls[] = compact('feature', 'result', 'context');
                }
            };

            $result = $gateway->invokeProxyRequest(
                ['method' => 'GET', 'path' => '/v1/health'],
                ['allowed_path_prefixes' => ['/v1']],
                ['provider' => 'titanai', 'feature' => 'proxy'],
            );

            $this->assertTrue($result['ok']);
            $this->assertCount(1, $gateway->usageCalls);
            $this->assertSame('proxy', $gateway->usageCalls[0]['feature']);
            $this->assertSame('titanai', $gateway->usageCalls[0]['result']['provider']);
        }

        public function test_api_and_web_routes_expose_magicai_compatibility_aliases(): void
        {
            $this->loadRouteFiles();

            $routeNames = array_column(Route::$routes, 'name');
            $routeUrisByName = [];

            foreach (Route::$routes as $route) {
                if ($route['name'] !== null) {
                    $routeUrisByName[$route['name']] = $route['uri'];
                }
            }

            $this->assertContains('titancore.api.titanai.ping', $routeNames);
            $this->assertContains('titancore.api.magicai.ping', $routeNames);
            $this->assertContains('titancore.api.magicai.proxy', $routeNames);
            $this->assertContains('titancore.admin.magicai.console', $routeNames);
            $this->assertContains('titancore.tenant.magicai.launcher', $routeNames);
            $this->assertSame('/titancore/magicai/ping', $routeUrisByName['titancore.api.magicai.ping']);
            $this->assertSame('/admin/settings/titancore/magicai', $routeUrisByName['titancore.admin.magicai.console']);
            $this->assertSame('/account/titancore/magicai', $routeUrisByName['titancore.tenant.magicai.launcher']);
        }

        private function loadControllerClasses(): void
        {
            require_once __DIR__ . '/../../Http/Controllers/Api/Concerns/ProxiesAiRequests.php';
            require_once __DIR__ . '/../../Http/Controllers/Api/TitanAiProxyController.php';
            require_once __DIR__ . '/../../Http/Controllers/Api/MagicAiProxyController.php';
            require_once __DIR__ . '/../../Http/Controllers/Admin/TitanAiConsoleController.php';
            require_once __DIR__ . '/../../Http/Controllers/Admin/MagicAiConsoleController.php';
            require_once __DIR__ . '/../../Http/Controllers/Tenant/TitanAiLauncherController.php';
            require_once __DIR__ . '/../../Http/Controllers/Tenant/MagicAiLauncherController.php';
        }

        private function loadClientClasses(): void
        {
            require_once __DIR__ . '/../../Services/TitanCoreAiClient.php';
            require_once __DIR__ . '/../../Services/TitanAiClient.php';
            require_once __DIR__ . '/../../Services/MagicAiClient.php';
        }

        private function loadGatewayAndRouterClasses(): void
        {
            require_once __DIR__ . '/../../Services/TitanCoreModelGateway.php';
            require_once __DIR__ . '/../../Services/TitanCoreRouter.php';
        }

        private function loadProviderClass(): void
        {
            require_once __DIR__ . '/../../Services/Providers/TitanCoreAiProvider.php';
        }

        private function loadRouteFiles(): void
        {
            require __DIR__ . '/../../Routes/api.php';
            require __DIR__ . '/../../Routes/web.php';
        }
    }
}
