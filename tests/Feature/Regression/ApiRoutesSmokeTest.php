<?php

namespace Tests\Feature\Regression;

use App\Http\Middleware\AssignGuardCustomer;
use App\Http\Middleware\AssignGuardDriver;
use App\Models\Customer;
use App\Models\Driver;
use App\Services\OrangeSMSService;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Filet de non-régression large : chaque route API, appelée avec un corps vide et
 * des identifiants inexistants, doit répondre proprement (2xx/4xx), jamais en 5xx.
 * Les nouvelles routes sont couvertes automatiquement.
 */
class ApiRoutesSmokeTest extends TestCase
{
    /** Routes volontairement exclues, avec la raison. */
    private const SKIPPED = [
        // actingAs() ne fournit pas de jeton à actualiser ; parcours réel couvert par OtpAuthenticationFlowTest
        'POST api/v1/customers/refresh' => 'nécessite un vrai jeton JWT',
    ];

    private Customer $customer;

    private Driver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();
        Queue::fake();
        Http::fake();
        $this->mock(OrangeSMSService::class)->shouldIgnoreMissing();

        $this->customer = Customer::create(['name' => 'Client Smoke', 'phone' => '2250700000001', 'is_active' => true]);
        $this->driver = Driver::factory()->create();
    }

    /** @test */
    public function no_api_route_returns_a_server_error()
    {
        $failures = [];
        $checked = 0;
        $asUser = 0;

        foreach ($this->apiRoutes() as $route) {
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $key = "{$method} {$route->uri()}";
                if (isset(self::SKIPPED[$key])) {
                    continue;
                }

                // Savepoint : une erreur SQL sur une route ne doit pas contaminer les suivantes
                DB::beginTransaction();
                $guard = $this->routeGuard($route);
                if ($guard) {
                    $this->actingAs($guard === 'api-customers' ? $this->customer : $this->driver, $guard);
                    $asUser++;
                }
                $response = $this->json($method, $this->resolveUri($route->uri()));
                DB::rollBack();
                $checked++;

                if ($response->getStatusCode() >= 500) {
                    $failures[] = sprintf('%s → %d %s', $key, $response->getStatusCode(), Str::limit($this->errorMessage($response), 200));
                }

                // Chaque appel repart d'un état d'authentification propre
                $this->app['auth']->forgetGuards();
            }
        }

        $this->assertGreaterThan(0, $checked);
        // Garde-fou : si les middlewares ne sont plus reconnus, les routes protégées ne seraient testées qu'en invité (401)
        $this->assertGreaterThan(0, $asUser, 'Aucune route appelée en tant que client ou chauffeur');
        $this->assertSame([], $failures, "Routes en erreur serveur :\n".implode("\n", $failures));
    }

    /** @return Route[] */
    private function apiRoutes(): array
    {
        return array_values(array_filter(
            $this->app['router']->getRoutes()->getRoutes(),
            fn (Route $route) => str_starts_with($route->uri(), 'api/')
        ));
    }

    /** Garde attendue par la route, d'après ses middlewares résolus (les alias auth.* deviennent des classes). */
    private function routeGuard(Route $route): ?string
    {
        $middleware = implode(',', $this->app['router']->gatherRouteMiddleware($route));

        return match (true) {
            str_contains($middleware, AssignGuardCustomer::class) => 'api-customers',
            str_contains($middleware, AssignGuardDriver::class) => 'api-drivers',
            default => null,
        };
    }

    /** Remplace chaque paramètre de route par un identifiant inexistant. */
    private function resolveUri(string $uri): string
    {
        return '/'.preg_replace('/\{[^}]+\}/', '999999', $uri);
    }

    private function errorMessage(TestResponse $response): string
    {
        return (string) ($response->json('message') ?? $response->getContent());
    }
}
