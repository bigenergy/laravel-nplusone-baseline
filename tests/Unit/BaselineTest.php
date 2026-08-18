<?php

declare(strict_types=1);

namespace BigEnergy\NPlusOne\Tests\Unit;

use BigEnergy\NPlusOne\Baseline;
use BigEnergy\NPlusOne\Tests\Concerns\BuildsViolations;
use BigEnergy\NPlusOne\Tests\Concerns\UsesWorkspace;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BaselineTest extends TestCase
{
    use BuildsViolations;
    use UsesWorkspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createWorkspace();
    }

    protected function tearDown(): void
    {
        $this->removeWorkspace();

        parent::tearDown();
    }

    public function test_without_a_baseline_every_violation_is_new(): void
    {
        $baseline = new Baseline($this->baselinePath());

        $violations = $this->keyed(
            $this->violation('App\Models\Order', 'manager'),
            $this->violation('App\Models\Invoice', 'lines'),
        );

        $this->assertFalse($baseline->exists());
        $this->assertCount(2, $baseline->newViolations($violations));
    }

    public function test_accepting_the_current_state_writes_the_baseline_file(): void
    {
        $baseline = new Baseline($this->baselinePath());

        $baseline->write($this->keyed($this->violation('App\Models\Order', 'manager')));

        $this->assertTrue($baseline->exists());
        $this->assertFileExists($this->baselinePath());
    }

    public function test_nothing_is_new_once_the_current_state_has_been_accepted(): void
    {
        $baseline = new Baseline($this->baselinePath());

        $violations = $this->keyed(
            $this->violation('App\Models\Order', 'manager'),
            $this->violation('App\Models\Invoice', 'lines'),
        );

        $baseline->write($violations);

        $this->assertSame([], $baseline->newViolations($violations));
    }

    public function test_a_relation_that_was_not_in_the_baseline_is_flagged(): void
    {
        $baseline = new Baseline($this->baselinePath());
        $baseline->write($this->keyed($this->violation('App\Models\Order', 'manager')));

        $new = $baseline->newViolations($this->keyed(
            $this->violation('App\Models\Order', 'manager'),
            $this->violation('App\Models\Order', 'customer'),
        ));

        $this->assertSame(['App\Models\Order::customer'], array_keys($new));
    }

    public function test_an_accepted_relation_stays_silent(): void
    {
        $baseline = new Baseline($this->baselinePath());
        $baseline->write($this->keyed($this->violation('App\Models\Order', 'manager')));

        $new = $baseline->newViolations($this->keyed(
            $this->violation('App\Models\Order', 'manager'),
            $this->violation('App\Models\Order', 'customer'),
        ));

        $this->assertArrayNotHasKey('App\Models\Order::manager', $new);
    }

    public function test_a_known_entry_firing_more_often_does_not_fail_the_build(): void
    {
        $baseline = new Baseline($this->baselinePath());
        $baseline->write($this->keyed($this->violation('App\Models\Order', 'manager', hits: 2)));

        // Counts move with whatever the factories happened to create. Comparing
        // them produces red builds unrelated to the change under review, which
        // is the fastest way to get the check switched off.
        $new = $baseline->newViolations($this->keyed(
            $this->violation('App\Models\Order', 'manager', hits: 47),
        ));

        $this->assertSame([], $new);
    }

    public function test_a_fixed_violation_is_reported_as_a_stale_baseline_entry(): void
    {
        $baseline = new Baseline($this->baselinePath());
        $baseline->write($this->keyed(
            $this->violation('App\Models\Order', 'manager'),
            $this->violation('App\Models\Invoice', 'lines'),
        ));

        $stale = $baseline->staleEntries($this->keyed($this->violation('App\Models\Order', 'manager')));

        $this->assertSame(['App\Models\Invoice::lines'], $stale);
    }

    public function test_a_stale_entry_is_not_a_failure(): void
    {
        $baseline = new Baseline($this->baselinePath());
        $baseline->write($this->keyed(
            $this->violation('App\Models\Order', 'manager'),
            $this->violation('App\Models\Invoice', 'lines'),
        ));

        $violations = $this->keyed($this->violation('App\Models\Order', 'manager'));

        $this->assertNotSame([], $baseline->staleEntries($violations));
        $this->assertSame([], $baseline->newViolations($violations));
    }

    public function test_a_corrupt_baseline_fails_loudly_rather_than_silently_accepting_everything(): void
    {
        file_put_contents($this->baselinePath(), '{not json');

        $this->expectException(RuntimeException::class);

        (new Baseline($this->baselinePath()))->load();
    }
}
