<?php

declare(strict_types=1);

namespace BigEnergy\NPlusOne\Tests;

use BigEnergy\NPlusOne\NPlusOneServiceProvider;
use BigEnergy\NPlusOne\Tests\Concerns\UsesWorkspace;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use UsesWorkspace;

    protected function setUp(): void
    {
        // Before parent::setUp(), which is what boots the application that
        // defineEnvironment() below configures.
        $this->createWorkspace();

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->removeWorkspace();
    }

    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [NPlusOneServiceProvider::class];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $config = $app->make(Repository::class);

        $config->set('nplusone.enabled', true);
        $config->set('nplusone.environments', ['testing']);
        $config->set('nplusone.report_path', $this->reportPath());
        $config->set('nplusone.baseline_path', $this->baselinePath());

        $config->set('database.default', 'testing');
        $config->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
    }
}
