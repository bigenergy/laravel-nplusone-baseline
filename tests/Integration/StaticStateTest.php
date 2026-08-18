<?php

declare(strict_types=1);

namespace BigEnergy\NPlusOne\Tests\Integration;

use BigEnergy\NPlusOne\Collector;
use BigEnergy\NPlusOne\Tests\Fixtures\Book;
use BigEnergy\NPlusOne\Tests\Fixtures\Database;
use BigEnergy\NPlusOne\Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Depends;

/**
 * The collector holds static state, and this is the reason why: Laravel builds
 * and destroys an application per test, so anything living in the container
 * would be thrown away 300 times over a suite and the run would never add up to
 * a picture.
 *
 * Deliberately no Collector::reset() here — these two tests share state on
 * purpose. PHPUnit runs a class's tests consecutively, so nothing else gets in
 * between them.
 */
final class StaticStateTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Once for the class, never between its tests. Whatever an earlier test
        // class left in the collector would otherwise be added to the counts
        // below, and the assertions would depend on the order PHPUnit happened
        // to walk the directory in.
        Collector::reset();
    }

    public function test_a_lazy_load_is_recorded_in_the_first_test(): string
    {
        Database::migrate();
        Database::seed();

        foreach (Book::query()->get() as $book) {
            $this->assertSame('Ursula', $book->author->name);
        }

        $fingerprint = Book::class.'::author';

        $this->assertArrayHasKey($fingerprint, Collector::violations());

        return $fingerprint;
    }

    #[Depends('test_a_lazy_load_is_recorded_in_the_first_test')]
    public function test_the_violation_outlives_the_application_that_recorded_it(string $fingerprint): void
    {
        // The in-memory database is empty again, which is the cheapest proof
        // that this is a different application from the one in the test above.
        $this->assertFalse(Schema::hasTable('books'));

        $violations = Collector::violations();

        $this->assertArrayHasKey($fingerprint, $violations);
        $this->assertSame(2, $violations[$fingerprint]->count());
    }
}
