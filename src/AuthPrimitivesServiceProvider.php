<?php

namespace Nawasara\AuthPrimitives;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Nawasara\AuthPrimitives\Http\Middleware\EnsureSudo;

/**
 * Auth-primitives service provider.
 *
 * Responsibilities (intentionally narrow):
 *   - Merge config('auth-primitives') from the bundled defaults
 *   - Register the `sudo` route-middleware alias (consumers expect it)
 *   - Publishable config for apps that want to tweak window/acr per-deploy
 *
 * What this provider DOES NOT do (left to the integration package
 * nawasara/core):
 *   - Register the /sudo/redirect and /sudo/callback routes
 *   - Wire the Auth\Events\Logout listener that calls Sudo::forget()
 *   - Read Vault credentials, talk to Keycloak, decode ID tokens
 *
 * Keeping the integration logic out means domain packages
 * (vault, api, keycloak, ...) can depend on auth-primitives without
 * dragging in core's stack.
 */
class AuthPrimitivesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/auth-primitives.php', 'auth-primitives');
    }

    public function boot(): void
    {
        $this->registerMiddleware();
        $this->offerPublishing();
    }

    /**
     * Register the `sudo` route-middleware alias. Apply it to any route
     * performing a critical action:
     *
     *   Route::get('db/drop/{name}', ...)->middleware(['auth', 'sudo']);
     *
     * EnsureSudo bounces requests without an active sudo window through
     * the OTP step-up first.
     */
    protected function registerMiddleware(): void
    {
        $router = $this->app->make(Router::class);

        $router->aliasMiddleware('sudo', EnsureSudo::class);
    }

    protected function offerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/auth-primitives.php' => config_path('auth-primitives.php'),
        ], 'auth-primitives:config');
    }
}
