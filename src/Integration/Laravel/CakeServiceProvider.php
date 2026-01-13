<?php

declare(strict_types=1);

namespace Tetthys\Cake\Integration\Laravel;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Tetthys\Cake\Engine\Engine;
use Tetthys\Cake\Integration\Laravel\Contracts\ActorResolver;
use Tetthys\Cake\Integration\Laravel\Contracts\AuthorizationResponder;
use Tetthys\Cake\Integration\Laravel\Responders\DefaultJson403Responder;
use Tetthys\Cake\Integration\Laravel\Resolvers\DefaultActorResolver;

final class CakeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Engine::class);

        $this->app->bind(ActorResolver::class, DefaultActorResolver::class);

        $this->app->bind(
            AuthorizationResponder::class,
            DefaultJson403Responder::class,
        );
    }

    public function boot(): void
    {
        // 1) Ensure helpers are loaded (in case composer "files" autoload isn't configured)
        if (!\function_exists("\Tetthys\Cake\Integration\Laravel\cake")) {
            $helpers = __DIR__ . "/helpers.php";
            if (\is_file($helpers)) {
                require_once $helpers;
            }
        }

        // 2) Alias middleware
        $this->app
            ->make("router")
            ->aliasMiddleware(
                "cake",
                AuthorizationMiddleware::class,
            );

        // 3) Blade directive: @cake(...)
        if (\class_exists(Blade::class)) {
            Blade::if(
                "cake",
                static fn(
                    string $action,
                    mixed $object = null,
                    mixed $rules = null,
                ) => \Tetthys\Cake\Integration\Laravel\cake(
                    $action,
                    $object,
                    $rules,
                ),
            );
        }
    }
}
