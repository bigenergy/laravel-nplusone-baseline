<?php

declare(strict_types=1);

namespace BigEnergy\NPlusOne\Tests\Unit;

use BigEnergy\NPlusOne\Collector;
use PHPUnit\Framework\TestCase;

final class CollectorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetCollector();
    }

    protected function tearDown(): void
    {
        $this->resetCollector();

        parent::tearDown();
    }

    public function test_a_disabled_collector_records_nothing(): void
    {
        Collector::record('App\Models\Order', 'manager');

        $this->assertSame([], Collector::violations());
    }

    public function test_distinct_model_relation_pairs_are_separate_entries(): void
    {
        Collector::enable();
        Collector::record('App\Models\Order', 'manager');
        Collector::record('App\Models\Invoice', 'lines');

        $this->assertCount(2, Collector::violations());
    }

    public function test_repeat_hits_on_one_pair_aggregate_into_a_single_entry(): void
    {
        Collector::enable();
        Collector::record('App\Models\Order', 'manager');
        Collector::record('App\Models\Order', 'manager');
        Collector::record('App\Models\Order', 'manager');

        $violations = Collector::violations();

        $this->assertCount(1, $violations);
        $this->assertSame(3, $violations['App\Models\Order::manager']->count());
    }

    public function test_the_running_test_is_attributed_to_the_violation(): void
    {
        Collector::enable();
        Collector::setCurrentTest('Tests\Feature\OrderApiTest::test_index');
        Collector::record('App\Models\Order', 'manager');
        Collector::setCurrentTest('Tests\Feature\InvoiceTest::test_show');
        Collector::record('App\Models\Invoice', 'lines');

        $violations = Collector::violations();

        $this->assertSame(
            ['Tests\Feature\OrderApiTest::test_index'],
            $violations['App\Models\Order::manager']->tests(),
        );
        $this->assertSame(
            ['Tests\Feature\InvoiceTest::test_show'],
            $violations['App\Models\Invoice::lines']->tests(),
        );
    }

    public function test_violations_are_keyed_by_the_model_relation_fingerprint(): void
    {
        Collector::enable();
        Collector::record('App\Models\Invoice', 'lines');

        $violations = Collector::violations();

        $this->assertArrayHasKey('App\Models\Invoice::lines', $violations);
        $this->assertSame('App\Models\Invoice::lines', $violations['App\Models\Invoice::lines']->fingerprint());
    }

    public function test_the_call_site_is_recorded_as_context(): void
    {
        Collector::enable();
        Collector::record('App\Models\Order', 'manager');

        $sites = Collector::violations()['App\Models\Order::manager']->sites();

        // The package's own frames are skipped, so the first frame left is the
        // line in this test that asked for the relation.
        $this->assertCount(1, $sites);
        $this->assertStringContainsString('CollectorTest.php:', implode('', $sites));
    }

    private function resetCollector(): void
    {
        Collector::reset();
        Collector::disable();
    }
}
