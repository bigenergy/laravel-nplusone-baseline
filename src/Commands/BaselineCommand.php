<?php

declare(strict_types=1);

namespace BigEnergy\NPlusOne\Commands;

use BigEnergy\NPlusOne\Baseline;
use BigEnergy\NPlusOne\Commands\Concerns\ResolvesPaths;
use BigEnergy\NPlusOne\Reporter;
use Illuminate\Console\Command;

final class BaselineCommand extends Command
{
    use ResolvesPaths;

    /** @var string */
    protected $signature = 'nplusone:baseline
                            {--baseline= : Path to the baseline file}
                            {--report= : Path to the report written by the test run}
                            {--force : Overwrite an existing baseline without asking}';

    /** @var string */
    protected $description = 'Record the current N+1 violations as accepted, so CI only fails on new ones';

    public function handle(): int
    {
        $reporter = new Reporter($this->resolvePath('report', 'nplusone.report_path'));
        $baseline = new Baseline($this->resolvePath('baseline', 'nplusone.baseline_path'));

        if (! $reporter->exists()) {
            $this->components->error("No report found at [{$reporter->path()}].");
            $this->line('  Run your test suite first — the report is written while the tests execute.');

            return self::FAILURE;
        }

        if ($baseline->exists() && ! $this->option('force')) {
            $existing = count($baseline->load());

            $question = sprintf(
                'A baseline with %d %s already exists. Overwrite it?',
                $existing,
                $existing === 1 ? 'entry' : 'entries',
            );

            if (! $this->confirm($question, false)) {
                return self::SUCCESS;
            }
        }

        $violations = $reporter->read();
        $baseline->write($violations);

        $this->components->info(sprintf(
            '%d %s written to %s',
            count($violations),
            count($violations) === 1 ? 'violation' : 'violations',
            $baseline->path(),
        ));

        $this->newLine();
        $this->line('  Commit this file. From now on CI fails only on violations that are not in it.');
        $this->newLine();

        return self::SUCCESS;
    }
}
