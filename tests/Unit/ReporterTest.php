<?php

declare(strict_types=1);

namespace BigEnergy\NPlusOne\Tests\Unit;

use BigEnergy\NPlusOne\Reporter;
use BigEnergy\NPlusOne\Tests\Concerns\BuildsViolations;
use BigEnergy\NPlusOne\Tests\Concerns\UsesWorkspace;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ReporterTest extends TestCase
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

    public function test_writing_a_run_creates_the_report_file(): void
    {
        $reporter = new Reporter($this->reportPath());

        $this->assertFalse($reporter->exists());

        $reporter->write($this->keyed($this->violation('App\Models\Order', 'manager')));

        $this->assertTrue($reporter->exists());
        $this->assertFileExists($this->reportPath());
    }

    public function test_the_directory_is_created_when_it_does_not_exist_yet(): void
    {
        $path = $this->workspace.DIRECTORY_SEPARATOR.'nested'.DIRECTORY_SEPARATOR.'report.json';

        (new Reporter($path))->write($this->keyed($this->violation('App\Models\Order', 'manager')));

        $this->assertFileExists($path);

        unlink($path);
        rmdir(dirname($path));
    }

    public function test_every_entry_survives_the_round_trip_to_disk(): void
    {
        $reporter = new Reporter($this->reportPath());
        $reporter->write($this->keyed(
            $this->violation('App\Models\Order', 'manager', hits: 3),
            $this->violation('App\Models\Invoice', 'lines'),
        ));

        $restored = (new Reporter($this->reportPath()))->read();

        $this->assertCount(2, $restored);
        $this->assertArrayHasKey('App\Models\Order::manager', $restored);
        $this->assertArrayHasKey('App\Models\Invoice::lines', $restored);
    }

    public function test_counts_survive_the_round_trip_to_disk(): void
    {
        (new Reporter($this->reportPath()))->write($this->keyed(
            $this->violation('App\Models\Order', 'manager', hits: 3),
        ));

        $restored = (new Reporter($this->reportPath()))->read();

        $this->assertSame(3, $restored['App\Models\Order::manager']->count());
    }

    public function test_reading_a_report_that_was_never_written_yields_nothing(): void
    {
        $this->assertSame([], (new Reporter($this->reportPath()))->read());
    }

    public function test_a_corrupt_report_fails_loudly_rather_than_silently_passing_the_build(): void
    {
        file_put_contents($this->reportPath(), '{not json');

        $this->expectException(RuntimeException::class);

        (new Reporter($this->reportPath()))->read();
    }
}
