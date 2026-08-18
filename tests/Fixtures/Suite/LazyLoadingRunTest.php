<?php

declare(strict_types=1);

namespace BigEnergy\NPlusOne\Tests\Fixtures\Suite;

use BigEnergy\NPlusOne\NPlusOneServiceProvider;
use BigEnergy\NPlusOne\Tests\Fixtures\Book;
use BigEnergy\NPlusOne\Tests\Fixtures\Database;
use BigEnergy\NPlusOne\Tests\Fixtures\EndToEnd;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase;

/**
 * Not part of the package's own suite — run as a child process by
 * ReportIsWrittenTest, against a phpunit.xml that registers the extension the
 * way the README tells users to.
 *
 * Nothing here writes the report. That is the point: it has to be written after
 * this test and its application are both gone.
 */
final class LazyLoadingRunTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Database::migrate();
    }

    public function test_a_relation_is_lazy_loaded_somewhere_in_the_run(): void
    {
        Database::seed();

        foreach (Book::query()->get() as $book) {
            $this->assertSame('Ursula', $book->author->name);
        }
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
        $config->set('nplusone.report_path', EndToEnd::reportPath());
        $config->set('nplusone.baseline_path', EndToEnd::baselinePath());

        $config->set('database.default', 'testing');
        $config->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
    }
}
