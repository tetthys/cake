<?php

use Orchestra\Testbench\TestCase as BaseTestCase;

// Base test case for this package
class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        // Your package's provider is auto-discovered via composer "extra.laravel.providers",
        // but returning it explicitly is also fine/harmless in Testbench.
        return [Tetthys\Cake\Integration\Laravel\CakeServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        // Minimal auth setup (so actingAs() works cleanly)
        $app["config"]->set("app.key", "base64:" . base64_encode(random_bytes(32)));
        $app["config"]->set("auth.defaults.guard", "web");
        $app["config"]->set("auth.guards.web", [
            "driver" => "session",
            "provider" => "users",
        ]);
        $app["config"]->set("auth.providers.users", [
            "driver" => "array",
            "model" => Tests\Support\FakeUser::class,
        ]);
    }
}

uses(TestCase::class)->in(__DIR__);
