<?php

declare(strict_types=1);

namespace BigEnergy\NPlusOne\Commands\Concerns;

use Illuminate\Contracts\Config\Repository;
use RuntimeException;

trait ResolvesPaths
{
    /**
     * A --report/--baseline option wins over the configured path, so the two
     * commands can be pointed at a run that happened somewhere else — a CI job
     * that uploads the report as an artifact, most usefully.
     */
    private function resolvePath(string $option, string $key): string
    {
        $override = $this->option($option);

        if (is_string($override) && $override !== '') {
            return $override;
        }

        $configured = $this->laravel->make(Repository::class)->get($key);

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        throw new RuntimeException("No path configured at [{$key}]; pass --{$option}.");
    }
}
