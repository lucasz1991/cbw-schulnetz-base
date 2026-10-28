<?php

namespace Tests\Feature;

use App\Livewire\Auth\Register;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->afterBootstrapping(\Illuminate\Foundation\Bootstrap\LoadConfiguration::class, function ($app) {
            $app['config']->set([
                'database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
                'cache.default' => 'array', 'session.driver' => 'array', 'activitylog.dispatch_enabled' => false,
            ]);
        });
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    public function test_custom_uvs_registration_route_remains_available(): void
    {
        $route = Route::getRoutes()->match(Request::create('/register', 'GET'));

        $this->assertSame('register', $route->getName());
        $this->assertStringContainsString(Register::class, $route->getActionName());
        $this->assertFalse(Features::enabled(Features::registration()));
    }

    public function test_generic_fortify_post_cannot_bypass_uvs_registration(): void
    {
        $this->expectException(MethodNotAllowedHttpException::class);

        Route::getRoutes()->match(Request::create('/register', 'POST'));
    }
}
