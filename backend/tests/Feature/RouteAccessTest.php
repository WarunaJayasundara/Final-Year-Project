<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Sweeps every API route: anonymous callers are turned away, students cannot reach admin routes, and no read-only route crashes. */
class RouteAccessTest extends TestCase
{
    /** @var User[] */
    private array $users = [];

    protected function tearDown(): void
    {
        foreach ($this->users as $user) {
            $user->delete();
        }

        parent::tearDown();
    }

    private function makeUser(string $role): User
    {
        $user = User::create([
            'name' => 'Route Access '.$role,
            'email' => 'route-access-'.$role.'-'.uniqid().'@test.local',
            'password' => Hash::make('password'),
            'auth_provider' => 'password',
            'role' => $role,
            'locale' => 'en',
        ]);
        $this->users[] = $user;

        // Reload so database defaults (xp, coins, ...) are populated, as they are for any real request.
        return $user->fresh();
    }

    /** @return array<int, array{method:string, uri:string, admin:bool, public:bool}> */
    private function apiRoutes(): array
    {
        $public = ['api/admin/login', 'api/auth/register', 'api/auth/login', 'api/auth/forgot-password', 'api/auth/reset-password', 'api/auth/logout', 'api/auth/me'];
        $routes = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (! str_starts_with($uri, 'api/') || str_starts_with($uri, 'api/auth/google') || str_contains($uri, 'oauth')) {
                continue;
            }

            foreach ($route->methods() as $method) {
                if ($method === 'HEAD') {
                    continue;
                }

                $routes[] = [
                    'method' => $method,
                    'uri' => '/'.preg_replace('/\{[^}]+\??\}/', '1', $uri),
                    'admin' => str_starts_with($uri, 'api/admin/') && $uri !== 'api/admin/login',
                    'public' => in_array($uri, $public, true) || $uri === 'api/auth/locale',
                ];
            }
        }

        return $routes;
    }

    public function test_protected_routes_reject_anonymous_callers()
    {
        $checked = 0;

        foreach ($this->apiRoutes() as $r) {
            if ($r['public']) {
                continue;
            }

            $status = $this->json($r['method'], $r['uri'])->getStatusCode();
            $this->assertSame(401, $status, "{$r['method']} {$r['uri']} should be 401 for anonymous callers, got {$status}");
            $checked++;
        }

        $this->assertGreaterThan(70, $checked);
    }

    public function test_students_cannot_reach_admin_routes()
    {
        $student = $this->makeUser('user');
        $checked = 0;

        foreach ($this->apiRoutes() as $r) {
            if (! $r['admin']) {
                continue;
            }

            $status = $this->actingAs($student, 'web')->json($r['method'], $r['uri'])->getStatusCode();
            // Routes with a model binding ("/1") 404 for a missing record before the role check can run.
            $expected = str_contains($r['uri'], '/1') ? [403, 404] : [403];
            $this->assertContains($status, $expected, "{$r['method']} {$r['uri']} should be refused for a student, got {$status}");
            $checked++;
        }

        $this->assertGreaterThan(30, $checked);
    }

    public function test_read_only_routes_never_crash_for_students_or_admins()
    {
        $student = $this->makeUser('user');
        $admin = $this->makeUser('admin');
        $checked = 0;

        foreach ($this->apiRoutes() as $r) {
            if ($r['method'] !== 'GET' || $r['public']) {
                continue;
            }

            $actor = $r['admin'] ? $admin : $student;
            $status = $this->actingAs($actor, 'web')->json('GET', $r['uri'])->getStatusCode();
            $this->assertLessThan(500, $status, "GET {$r['uri']} crashed with HTTP {$status}");
            $checked++;
        }

        $this->assertGreaterThan(40, $checked);
    }
}
