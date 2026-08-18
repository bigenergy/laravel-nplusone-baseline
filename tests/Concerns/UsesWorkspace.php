<?php

declare(strict_types=1);

namespace BigEnergy\NPlusOne\Tests\Concerns;

use RuntimeException;

/**
 * A throwaway directory per test, so report and baseline files never leak from
 * one test into the next.
 */
trait UsesWorkspace
{
    protected string $workspace = '';

    protected function createWorkspace(): void
    {
        $this->workspace = sys_get_temp_dir().DIRECTORY_SEPARATOR.'nplusone-'.bin2hex(random_bytes(8));

        if (! mkdir($this->workspace, 0o755, true) && ! is_dir($this->workspace)) {
            throw new RuntimeException("Unable to create workspace [{$this->workspace}].");
        }
    }

    protected function removeWorkspace(): void
    {
        if ($this->workspace === '' || ! is_dir($this->workspace)) {
            return;
        }

        foreach (glob($this->workspace.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        rmdir($this->workspace);
    }

    protected function reportPath(): string
    {
        return $this->workspace.DIRECTORY_SEPARATOR.'report.json';
    }

    protected function baselinePath(): string
    {
        return $this->workspace.DIRECTORY_SEPARATOR.'baseline.json';
    }
}
