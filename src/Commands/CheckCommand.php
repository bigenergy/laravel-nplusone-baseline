<?php

declare(strict_types=1);

namespace BigEnergy\NPlusOne\Commands;

use BigEnergy\NPlusOne\Baseline;
use BigEnergy\NPlusOne\Commands\Concerns\ResolvesPaths;
use BigEnergy\NPlusOne\Reporter;
use BigEnergy\NPlusOne\Violation;
use Illuminate\Console\Command;

final class CheckCommand extends Command
{
    use ResolvesPaths;

    /** @var string */
    protected $signature = 'nplusone:check
                            {--baseline= : Path to the baseline file}
                            {--report= : Path to the report written by the test run}
                            {--prune : Also fail when the baseline lists violations that no longer occur}';

    /** @var string */
    protected $description = 'Fail the build when the test run introduced N+1 queries that are not in the baseline';

    public function handle(): int
    {
        $reporter = new Reporter($this->resolvePath('report', 'nplusone.report_path'));
        $baseline = new Baseline($this->resolvePath('baseline', 'nplusone.baseline_path'));

        if (! $reporter->exists()) {
            $this->components->error("No report found at [{$reporter->path()}].");
            $this->line('  Run your test suite first — the report is written while the tests execute.');

            return self::FAILURE;
        }

        $violations = $reporter->read();
        $new = $baseline->newViolations($violations);
        $stale = $baseline->staleEntries($violations);

        if (! $baseline->exists()) {
            $this->components->warn("No baseline at [{$baseline->path()}] — every violation counts as new.");
            $this->newLine();
        }

        if ($new !== []) {
            $this->components->error(sprintf(
                '%d new N+1 %s introduced.',
                count($new),
                count($new) === 1 ? 'query' : 'queries',
            ));
            $this->newLine();
            $this->render($new);
            $this->line('  Fix them with eager loading, or accept them with: <fg=yellow>php artisan nplusone:baseline</>');
            $this->newLine();

            return self::FAILURE;
        }

        $known = count($baseline->load());

        $this->components->info(sprintf(
            'No new N+1 queries. %d known %s in the baseline.',
            $known,
            $known === 1 ? 'violation' : 'violations',
        ));

        if ($stale !== []) {
            $this->newLine();
            $this->components->warn(sprintf(
                '%d baseline %s no longer occur and can be removed:',
                count($stale),
                count($stale) === 1 ? 'entry' : 'entries',
            ));

            foreach ($stale as $fingerprint) {
                $this->line("  <fg=gray>-</> {$fingerprint}");
            }

            $this->newLine();
            $this->line('  Prune with: <fg=yellow>php artisan nplusone:baseline</>');

            if ($this->option('prune')) {
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, Violation>  $violations
     */
    private function render(array $violations): void
    {
        foreach ($violations as $violation) {
            $this->line("  <fg=red>✗</> <options=bold>{$violation->fingerprint()}</> <fg=gray>×{$violation->count()}</>");

            foreach (array_slice($violation->sites(), 0, 3) as $site) {
                $this->line("      <fg=gray>at</> {$site}");
            }

            foreach (array_slice($violation->tests(), 0, 3) as $test) {
                $this->line("      <fg=gray>in</> {$test}");
            }

            $this->newLine();
        }
    }
}
