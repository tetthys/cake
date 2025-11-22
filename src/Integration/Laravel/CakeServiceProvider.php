<?php

declare(strict_types=1);

namespace Tetthys\Cake\Integration\Laravel;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
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
        if (!\function_exists("\Tetthys\Cake\Integration\Laravel\cakeCan")) {
            $helpers = __DIR__ . "/helpers.php";
            if (\is_file($helpers)) {
                require_once $helpers;
            }
        }

        // 2) Alias middleware: supports both explicit and auto-infer modes
        //    - cake:post.update,App\Policies\PostRules@update
        //    - cake:post.update
        $this->app
            ->make("router")
            ->aliasMiddleware(
                "cake",
                \Tetthys\Cake\Integration\Laravel\AuthorizationMiddleware::class,
            );

        // 3) Blade directives (@cakeCan / @cakeCannot)
        if (\class_exists(Blade::class)) {
            Blade::if(
                "cake",
                fn(
                    string $action,
                    mixed $object = null,
                    mixed $rules = null,
                ) => \Tetthys\Cake\Integration\Laravel\cake(
                    $action,
                    $object,
                    $rules,
                ),
            );

            Blade::if(
                "cakeCan",
                fn(
                    string $action,
                    mixed $object = null,
                    mixed $rules = null,
                ) => \Tetthys\Cake\Integration\Laravel\cake(
                    $action,
                    $object,
                    $rules,
                ),
            );

            Blade::if(
                "cakeCannot",
                fn(
                    string $action,
                    mixed $object = null,
                    mixed $rules = null,
                ) => !\Tetthys\Cake\Integration\Laravel\cake(
                    $action,
                    $object,
                    $rules,
                ),
            );
        }
    }
}
