<?php

declare(strict_types=1);

namespace BigEnergy\NPlusOne;

use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository;
use JsonException;
use RuntimeException;
use Throwable;

/**
 * Persists what the current run found.
 *
 * The check runs in a separate process from the test suite, so the two
 * communicate through this file rather than through memory.
 */
final class Reporter
{
    /**
     * Where flush() writes when nobody passes a path.
     *
     * Captured by the service provider while an application is booted. Both
     * flush() callers — the PHPUnit extension's ExecutionFinished hook and the
     * shutdown function — fire *after* the last test tore its application down,
     * at which point config() and base_path() resolve against an emptied
     * container and throw. The path has to be remembered while there is still
     * a container to ask.
     */
    private static ?string $defaultPath = null;

    public function __construct(private readonly string $path) {}

    public static function rememberDefaultPath(string $path): void
    {
        self::$defaultPath = $path;
    }

    public static function forgetDefaultPath(): void
    {
        self::$defaultPath = null;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function exists(): bool
    {
        return is_file($this->path);
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
            throw new RuntimeException("Unable to write the report to [{$this->path}].");
        }
    }

    /**
     * @return array<string, Violation>
     */
    public function read(): array
    {
        if (! $this->exists()) {
            return [];
        }

        $raw = file_get_contents($this->path);

        if ($raw === false) {
            throw new RuntimeException("Unable to read report at [{$this->path}].");
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException("Report at [{$this->path}] is not valid JSON: {$e->getMessage()}", previous: $e);
        }

        if (! is_array($decoded)) {
            return [];
        }

        $entries = $decoded['violations'] ?? null;

        if (! is_array($entries)) {
            return [];
        }

        $violations = [];

        foreach ($entries as $data) {
            if (! is_array($data)) {
                continue;
            }

            $violation = Violation::fromArray($data);

            // Re-keyed by the violation's own fingerprint rather than by the key
            // in the file, so a hand-edited report still compares correctly.
            $violations[$violation->fingerprint()] = $violation;
        }

        ksort($violations);

        return $violations;
    }

    /**
     * Flush whatever the collector is holding. Safe to call more than once.
     *
     * Never throws. One caller is a shutdown function, where an exception is a
     * fatal error that replaces the suite's exit code with 255 — a report we
     * could not write is worth a line on stderr, not a broken build.
     */
    public static function flush(?string $path = null): void
    {
        if (! Collector::enabled()) {
            return;
        }

        try {
            (new self($path ?? self::defaultPath()))->write(Collector::violations());
        } catch (Throwable $e) {
            error_log('nplusone: unable to write the report: '.$e->getMessage());
        }
    }

    public static function defaultPath(): string
    {
        if (self::$defaultPath !== null) {
            return self::$defaultPath;
        }

        $container = Container::getInstance();

        if ($container->bound('config')) {
            $configured = $container->make(Repository::class)->get('nplusone.report_path');

            if (is_string($configured) && $configured !== '') {
                return $configured;
            }
        }

        $cwd = getcwd();

        return ($cwd === false ? '.' : $cwd).'/.nplusone/report.json';
    }
}
