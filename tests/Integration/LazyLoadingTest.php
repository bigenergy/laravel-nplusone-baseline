<?php

declare(strict_types=1);

namespace BigEnergy\NPlusOne\Tests\Integration;

use BigEnergy\NPlusOne\Collector;
use BigEnergy\NPlusOne\Tests\Fixtures\Author;
use BigEnergy\NPlusOne\Tests\Fixtures\Book;
use BigEnergy\NPlusOne\Tests\Fixtures\Database;
use BigEnergy\NPlusOne\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;

/**
 * Real Eloquent, real SQLite, real lazy loads.
 *
 * These are the tests that pin down the two integration points the package
 * cannot verify on its own: that preventLazyLoading() is actually installed by
 * the service provider, and that Laravel hands the violation callback a model
 * and a relation name in that order.
 */
final class LazyLoadingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Database::migrate();
        Collector::reset();
    }

    public function test_strict_mode_is_installed_by_the_service_provider(): void
    {
        $this->assertTrue(Model::preventsLazyLoading());
        $this->assertTrue(Collector::enabled());
    }

    public function test_a_lazy_loaded_relation_is_recorded_and_does_not_throw(): void
    {
        Database::seed();
        Collector::reset();

        // No expectException: not throwing is the whole point. Vanilla strict
        // mode would have aborted on the first iteration.
        foreach (Book::query()->get() as $book) {
            $this->assertSame('Ursula', $book->author->name);
        }

        $violations = Collector::violations();

        $this->assertArrayHasKey(Book::class.'::author', $violations);
        $this->assertSame(2, $violations[Book::class.'::author']->count());
    }

    public function test_the_recorded_fingerprint_carries_the_model_class_and_the_relation_name(): void
    {
        Database::seed();
        Collector::reset();

        $author = Author::query()->firstOrFail();
        $this->assertCount(2, $author->books);

        $violations = Collector::violations();

        $this->assertArrayHasKey(Author::class.'::books', $violations);
        $this->assertSame(Author::class, $violations[Author::class.'::books']->model);
        $this->assertSame('books', $violations[Author::class.'::books']->relation);
    }

    public function test_an_eager_loaded_relation_records_nothing(): void
    {
        Database::seed();
        Collector::reset();

        foreach (Book::query()->with('author')->get() as $book) {
            $this->assertSame('Ursula', $book->author->name);
        }

        $this->assertSame([], Collector::violations());
    }

    public function test_a_relation_read_on_a_freshly_created_model_is_not_recorded(): void
    {
        Collector::reset();

        // Laravel does not throw here either: the model was just inserted, so
        // loading its relation is a first query rather than an N+1. The package
        // must not be stricter than the mechanism it wraps.
        $author = Author::create(['name' => 'Ursula']);

        $this->assertCount(0, $author->books);
        $this->assertSame([], Collector::violations());
    }

    public function test_the_call_site_points_at_application_code_rather_than_eloquent(): void
    {
        Database::seed();
        Collector::reset();

        foreach (Book::query()->get() as $book) {
            $this->assertSame('Ursula', $book->author->name);
        }

        $sites = Collector::violations()[Book::class.'::author']->sites();

        $this->assertNotSame([], $sites);
        $this->assertStringNotContainsString('/vendor/', implode('|', $sites));
        $this->assertStringContainsString('LazyLoadingTest.php:', implode('|', $sites));
    }

    public function test_the_running_test_is_attributed_when_the_extension_set_one(): void
    {
        Database::seed();
        Collector::reset();
        Collector::setCurrentTest('Tests\Feature\BookApiTest::test_index');

        foreach (Book::query()->get() as $book) {
            $this->assertSame('Ursula', $book->author->name);
        }

        Collector::setCurrentTest(null);

        $this->assertSame(
            ['Tests\Feature\BookApiTest::test_index'],
            Collector::violations()[Book::class.'::author']->tests(),
        );
    }
}
