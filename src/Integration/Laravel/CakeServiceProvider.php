<?php

declare(strict_types=1);

namespace Tetthys\Cake\Integration\Laravel;

use Illuminate\Support\ServiceProvider;
use Tetthys\Cake\Integration\Laravel\Contracts\ActorResolver;

final class CakeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Default binding; apps can override in their own providers.
        $this->app->bind(ActorResolver::class, DefaultActorResolver::class);

        // Engine as a shared service is handy in apps
        $this->app->singleton(\Tetthys\Cake\Engine\Engine::class);
    }
}
