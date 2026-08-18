<?php

declare(strict_types=1);

namespace BigEnergy\NPlusOne\Tests\Integration;

use BigEnergy\NPlusOne\Baseline;
use BigEnergy\NPlusOne\Reporter;
use BigEnergy\NPlusOne\Tests\Concerns\BuildsViolations;
use BigEnergy\NPlusOne\Tests\TestCase;
use BigEnergy\NPlusOne\Violation;
use Illuminate\Console\Command;
use Illuminate\Testing\PendingCommand;

final class CommandsTest extends TestCase
{
    use BuildsViolations;

    public function test_check_fails_when_there_is_no_report_to_check(): void
    {
        $this->nplusone('nplusone:check')
            ->expectsOutputToContain('No report found')
            ->assertExitCode(Command::FAILURE)
            ->run();
    }

    public function test_check_fails_when_a_violation_is_not_covered_by_a_baseline(): void
    {
        $this->writeReport($this->violation('App\Models\Order', 'manager'));

        $this->nplusone('nplusone:check')
            ->assertExitCode(Command::FAILURE)
            ->run();
    }

    public function test_check_passes_once_the_baseline_covers_everything_in_the_run(): void
    {
        $violation = $this->violation('App\Models\Order', 'manager');

        $this->writeReport($violation);
        (new Baseline($this->baselinePath()))->write($this->keyed($violation));

        $this->nplusone('nplusone:check')
            ->assertExitCode(Command::SUCCESS)
            ->run();
    }

    public function test_baseline_writes_a_file_that_check_then_accepts(): void
    {
        $this->writeReport(
            $this->violation('App\Models\Order', 'manager'),
            $this->violation('App\Models\Invoice', 'lines'),
        );

        $this->nplusone('nplusone:baseline')
            ->assertExitCode(Command::SUCCESS)
            ->run();

        $this->assertFileExists($this->baselinePath());
        $this->assertCount(2, (new Baseline($this->baselinePath()))->load());

        $this->nplusone('nplusone:check')
            ->assertExitCode(Command::SUCCESS)
            ->run();
    }

    public function test_check_fails_again_as_soon_as_a_new_relation_appears(): void
    {
        $this->writeReport($this->violation('App\Models\Order', 'manager'));

        $this->nplusone('nplusone:baseline')->assertExitCode(Command::SUCCESS)->run();

        $this->writeReport(
            $this->violation('App\Models\Order', 'manager'),
            $this->violation('App\Models\Order', 'customer'),
        );

        $this->nplusone('nplusone:check')
            ->expectsOutputToContain('App\Models\Order::customer')
            ->assertExitCode(Command::FAILURE)
            ->run();
    }

    public function test_a_known_relation_firing_more_often_keeps_the_build_green(): void
    {
        $this->writeReport($this->violation('App\Models\Order', 'manager', hits: 2));

        $this->nplusone('nplusone:baseline')->assertExitCode(Command::SUCCESS)->run();

        $this->writeReport($this->violation('App\Models\Order', 'manager', hits: 47));

        $this->nplusone('nplusone:check')
            ->assertExitCode(Command::SUCCESS)
            ->run();
    }

    public function test_a_fixed_violation_is_reported_but_does_not_fail_the_build(): void
    {
        $this->writeReport(
            $this->violation('App\Models\Order', 'manager'),
            $this->violation('App\Models\Invoice', 'lines'),
        );

        $this->nplusone('nplusone:baseline')->assertExitCode(Command::SUCCESS)->run();

        $this->writeReport($this->violation('App\Models\Order', 'manager'));

        $this->nplusone('nplusone:check')
            ->expectsOutputToContain('App\Models\Invoice::lines')
            ->assertExitCode(Command::SUCCESS)
            ->run();
    }

    public function test_prune_turns_a_stale_baseline_entry_into_a_failure(): void
    {
        $this->writeReport(
            $this->violation('App\Models\Order', 'manager'),
            $this->violation('App\Models\Invoice', 'lines'),
        );

        $this->nplusone('nplusone:baseline')->assertExitCode(Command::SUCCESS)->run();

        $this->writeReport($this->violation('App\Models\Order', 'manager'));

        $this->nplusone('nplusone:check', ['--prune' => true])
            ->assertExitCode(Command::FAILURE)
            ->run();
    }

    public function test_baseline_asks_before_overwriting_an_existing_file(): void
    {
        $this->writeReport($this->violation('App\Models\Order', 'manager'));
        (new Baseline($this->baselinePath()))->write(
            $this->keyed($this->violation('App\Models\Order', 'manager')),
        );

        $this->nplusone('nplusone:baseline')
            ->expectsConfirmation('A baseline with 1 entry already exists. Overwrite it?', 'no')
            ->assertExitCode(Command::SUCCESS)
            ->run();
    }

    public function test_baseline_overwrites_without_asking_when_forced(): void
    {
        $this->writeReport($this->violation('App\Models\Order', 'manager'));
        (new Baseline($this->baselinePath()))->write(
            $this->keyed($this->violation('App\Models\Invoice', 'lines')),
        );

        $this->nplusone('nplusone:baseline', ['--force' => true])
            ->assertExitCode(Command::SUCCESS)
            ->run();

        $this->assertSame(
            ['App\Models\Order::manager'],
            array_keys((new Baseline($this->baselinePath()))->load()),
        );
    }

    public function test_the_paths_can_be_pointed_somewhere_else_from_the_command_line(): void
    {
        $report = $this->workspace.DIRECTORY_SEPARATOR.'elsewhere.json';
        $baseline = $this->workspace.DIRECTORY_SEPARATOR.'accepted.json';

        (new Reporter($report))->write($this->keyed($this->violation('App\Models\Order', 'manager')));

        $this->nplusone('nplusone:baseline', ['--report' => $report, '--baseline' => $baseline, '--force' => true])
            ->assertExitCode(Command::SUCCESS)
            ->run();

        $this->assertFileExists($baseline);

        $this->nplusone('nplusone:check', ['--report' => $report, '--baseline' => $baseline])
            ->assertExitCode(Command::SUCCESS)
            ->run();

        unlink($report);
        unlink($baseline);
    }

    /**
     * artisan() is typed PendingCommand|int because it returns an exit code once
     * the command has already run. Inside a test it is always the pending
     * command, but only a runtime check can say so.
     *
     * @param  array<string, mixed>  $parameters
     */
    private function nplusone(string $command, array $parameters = []): PendingCommand
    {
        $pending = $this->artisan($command, $parameters);

        $this->assertInstanceOf(PendingCommand::class, $pending);

        return $pending;
    }

    private function writeReport(Violation ...$violations): void
    {
        (new Reporter($this->reportPath()))->write($this->keyed(...$violations));
    }
}
