<?php

declare(strict_types=1);

namespace Tetthys\Cake\Integration\Laravel;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Tetthys\Cake\Engine\Engine;
use Tetthys\Cake\Integration\Laravel\Contracts\ActorResolver;
use Tetthys\Cake\Integration\Laravel\Contracts\AuthorizationResponder;
use Tetthys\Cake\Integration\Laravel\Responders\DefaultJson403Responder;
use Tetthys\Cake\Integration\Laravel\Resolvers\DefaultActorResolver;

final class CakeServiceProvider extends ServiceProvider
{
    public const CONFIG_KEY = 'cake';
    public const PUBLISH_TAG_CONFIG = 'cake-config';

    public function register(): void
    {
        // English comment: Merge package config so users can override values in config/cake.php
        $this->mergeConfigFrom($this->configPath(), self::CONFIG_KEY);

        $this->app->singleton(Engine::class);

        $this->app->bind(ActorResolver::class, DefaultActorResolver::class);
        $this->app->bind(AuthorizationResponder::class, DefaultJson403Responder::class);
    }

    public function boot(): void
    {
        $this->bootConfigPublishing();
        $this->bootHelpers();
        $this->bootMiddlewareAlias();
        $this->bootBladeDirective();
    }

    private function bootConfigPublishing(): void
    {
        // English comment: Publishing is only relevant in console
        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            $this->configPath() => $this->app->configPath(self::CONFIG_KEY . '.php'),
        ], self::PUBLISH_TAG_CONFIG);
    }

    private function bootHelpers(): void
    {
        // English comment: Skip if the helper is already available via composer files autoload
        if (\function_exists(__NAMESPACE__ . '\\cake')) {
            return;
        }

        $helpers = __DIR__ . '/helpers.php';
        if (\is_file($helpers)) {
            require_once $helpers;
        }
    }

    private function bootMiddlewareAlias(): void
    {
        // English comment: Router is available as a binding; avoid string-based make()
        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('cake', AuthorizationMiddleware::class);
    }

    private function bootBladeDirective(): void
    {
        // English comment: Blade facade exists in typical Laravel installs; keep the guard minimal
        if (!\class_exists(Blade::class)) {
            return;
        }

        Blade::if('cake', static function (string $action, mixed $object = null, mixed $rules = null): bool {
            return \Tetthys\Cake\Integration\Laravel\cake($action, $object, $rules);
        });
    }

    private function configPath(): string
    {
        return __DIR__ . '/config/cake.php';
    }
}
