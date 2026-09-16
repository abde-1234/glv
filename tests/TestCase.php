<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        // Fail before RefreshDatabase can run if a cached/local configuration targets real data.
        $app = parent::createApplication();
        if (! $app->environment('testing')
            || $app['config']->get('database.default') !== 'sqlite'
            || $app['config']->get('database.connections.sqlite.database') !== ':memory:'
            || $app['config']->get('database.connections.sqlite.url')) {
            throw new \RuntimeException('Les tests GLV exigent SQLite :memory: et APP_ENV=testing.');
        }

        return $app;
    }
}
