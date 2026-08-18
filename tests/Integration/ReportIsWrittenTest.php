<?php

declare(strict_types=1);

namespace BigEnergy\NPlusOne\Tests\Integration;

use BigEnergy\NPlusOne\Collector;
use BigEnergy\NPlusOne\Reporter;
use BigEnergy\NPlusOne\Tests\Fixtures\Book;
use BigEnergy\NPlusOne\Tests\Fixtures\EndToEnd;
use BigEnergy\NPlusOne\Tests\TestCase;
use Symfony\Component\Process\Process;

final class ReportIsWrittenTest extends TestCase
{
    public function test_flushing_writes_the_report_to_the_configured_path(): void
    {
        Collector::reset();
        Collector::record('App\Models\Order', 'manager');

        // No argument: this is the path the service provider remembered while
        // the application was booting, which is the only reason flush() still
        // knows where to write once every application has been torn down.
        Reporter::flush();

        $this->assertFileExists($this->reportPath());
        $this->assertArrayHasKey(
            'App\Models\Order::manager',
            (new Reporter($this->reportPath()))->read(),
        );
    }

    /**
     * The one thing a single-process test cannot fake: whether the report is
     * still written after the last test has torn its application down and the
     * runner is shutting the process down.
     */
    public function test_a_real_phpunit_run_leaves_the_report_on_disk(): void
    {
        $binary = $this->phpunitBinary();

        if ($binary === null) {
            $this->markTestSkipped('No PHPUnit binary in vendor/; run composer install.');
        }

        $this->clearEndToEndDirectory();

        $process = new Process(
            [PHP_BINARY, $binary, '--configuration', self::packageRoot().'/tests/Fixtures/Suite/phpunit.xml'],
            self::packageRoot(),
        );
        $process->setTimeout(300.0);
        $process->run();

        $output = $process->getOutput().$process->getErrorOutput();

        $this->assertSame(0, $process->getExitCode(), "The fixture suite did not pass:\n".$output);
        $this->assertFileExists(EndToEnd::reportPath(), "No report was written:\n".$output);

        $decoded = json_decode((string) file_get_contents(EndToEnd::reportPath()), true);

        $this->assertIsArray($decoded);

        $violations = $decoded['violations'] ?? null;

        $this->assertIsArray($violations);
        $this->assertArrayHasKey(Book::class.'::author', $violations);

        $this->clearEndToEndDirectory();
    }

    private static function packageRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    private function phpunitBinary(): ?string
    {
        $candidates = [
            self::packageRoot().'/vendor/bin/phpunit',
            self::packageRoot().'/vendor/phpunit/phpunit/phpunit',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function clearEndToEndDirectory(): void
    {
        foreach (glob(EndToEnd::directory().DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}
