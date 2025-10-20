<?php

declare(strict_types=1);

namespace Tetthys\Cake\Integration\Laravel;

use Illuminate\Support\ServiceProvider;
use Tetthys\Cake\Engine\Engine;
use Tetthys\Cake\Integration\Laravel\Contracts\ActorResolver;
use Tetthys\Cake\Integration\Laravel\Contracts\AuthorizationResponder;
use Tetthys\Cake\Integration\Laravel\Responders\DefaultJson403Responder;

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
}
