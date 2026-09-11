<?php

namespace Abdulsalam\LaravelContextFlow\Tests;

use Abdulsalam\LaravelContextFlow\ContextFlowServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [ContextFlowServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('context-flow.http.incoming', false);
        $app['config']->set('context-flow.http.outgoing', false);
        $app['config']->set('context-flow.security.signing_key', str_repeat('x', 32));
        $app['config']->set('context-flow.queue.unregistered_keys', 'drop');
    }
}
