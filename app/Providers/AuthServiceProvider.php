<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;
use App\Auth\UserAuthProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
    ];

    /**
     * Register any authentication / authorization services.
     */

    public function boot(): void
    {
        $this->registerPolicies();

        Auth::provider('user_auth', function ($app, array $config) {
            return new UserAuthProvider($app['hash'], $config['model']);
        });
    }
}
