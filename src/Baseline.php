<?php

declare(strict_types=1);

namespace BigEnergy\NPlusOne;

use JsonException;
use RuntimeException;

/**
 * The committed list of violations that are known and accepted.
 *
 * This is what makes the tool adoptable on an existing codebase: you record the
 * 300 N+1s you already have, CI stays quiet about them, and only the 301st
 * fails the build. Without it nobody can switch the check on without a
 * multi-week cleanup first, which is why most teams never switch it on at all.
 */
final class Baseline
{
    public function __construct(private readonly string $path) {}

    public function path(): string
    {
        return $this->path;
    }

    public function exists(): bool
    {
        return is_file($this->path);
    }

    /**
     * @return array<string, array<array-key, mixed>> keyed by fingerprint
     */
    public function load(): array
    {
        if (! $this->exists()) {
            return [];
        }

        $raw = file_get_contents($this->path);

        if ($raw === false) {
            throw new RuntimeException("Unable to read baseline at [{$this->path}].");
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException("Baseline at [{$this->path}] is not valid JSON: {$e->getMessage()}", previous: $e);
        }

        if (! is_array($decoded)) {
            return [];
        }

        $entries = $decoded['violations'] ?? null;

        if (! is_array($entries)) {
            return [];
        }

        $violations = [];

        foreach ($entries as $fingerprint => $entry) {
            if (is_string($fingerprint) && is_array($entry)) {
                $violations[$fingerprint] = $entry;
            }
        }

        return $violations;
    }

    /**
     * @param  array<string, Violation>  $violations
     */
    public function write(array $violations): void
    {
        $directory = dirname($this->path);

        if (! is_dir($directory) && ! mkdir($directory, 0o755, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create directory [{$directory}].");
        }

        $payload = [
            'generated_at' => date(DATE_ATOM),
            'violations' => array_map(
                static fn (Violation $violation): array => $violation->toArray(),
                $violations,
            ),
        ];

        $encoded = json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );

        if (file_put_contents($this->path, $encoded.PHP_EOL) === false) {
            throw new RuntimeException("Unable to write the baseline to [{$this->path}].");
        }
    }

    /**
     * Violations present in the run but absent from the baseline.
     *
     * Counts are ignored by design. The same endpoint lazy-loading the same
     * relation will fire a different number of times depending on how many rows
     * the factories happened to create, so comparing counts produces failures
     * that have nothing to do with the code under review.
     *
     * @param  array<string, Violation>  $violations
     * @return array<string, Violation>
     */
    public function newViolations(array $violations): array
    {
        $known = $this->load();

        return array_filter(
            $violations,
            static fn (string $fingerprint): bool => ! array_key_exists($fingerprint, $known),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * Baseline entries that no longer occur, i.e. things somebody has fixed.
     *
     * These never fail the build. They are reported so the baseline can be
     * pruned and does not silently keep granting permission for an N+1 that was
     * cleaned up months ago.
     *
     * @param  array<string, Violation>  $violations
     * @return list<string>
     */
    public function staleEntries(array $violations): array
    {
        return array_values(array_diff(
            array_keys($this->load()),
            array_keys($violations),
        ));
    }
}
