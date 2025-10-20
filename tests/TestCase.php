<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Http\Request;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Tetthys\Cake\Integration\Laravel\CakeServiceProvider;

abstract class TestCase extends BaseTestCase
{
    /** Register your package service provider */
    protected function getPackageProviders($app): array
    {
        return [CakeServiceProvider::class];
    }

    /** Minimal env so official view testing helpers work */
    protected function defineEnvironment($app): void
    {
        // Provide a request instance so helpers can resolve ip/now, etc.
        $app->instance('request', Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
        ]));
    }
}
