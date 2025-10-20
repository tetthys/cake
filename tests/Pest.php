<?php

use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [Tetthys\Cake\Integration\Laravel\CakeServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        // Only set app key; no custom auth provider needed when using $this->be()
        $app["config"]->set("app.key", "base64:" . base64_encode(random_bytes(32)));
        $app["config"]->set("auth.defaults.guard", "web");
        $app["config"]->set("auth.guards.web", [
            "driver" => "session",
            "provider" => "users", // will not be used with $this->be()
        ]);
    }
}

uses(TestCase::class)->in(__DIR__);
