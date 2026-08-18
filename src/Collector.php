<?php

declare(strict_types=1);

namespace BigEnergy\NPlusOne;

use Illuminate\Container\Container;

/**
 * Process-wide store for lazy-loading violations.
 *
 * State is static on purpose: Laravel boots and tears down the application
 * container many times over a test run, but the PHP process stays the same,
 * so this survives where a container singleton would not.
 */
final class Collector
{
    /** @var array<string, Violation> */
    private static array $violations = [];

    private static ?string $currentTest = null;

    private static bool $enabled = false;

    public static function enable(): void
    {
        self::$enabled = true;
    }

    public static function disable(): void
    {
        self::$enabled = false;
    }

    public static function enabled(): bool
    {
        return self::$enabled;
    }

    public static function setCurrentTest(?string $test): void
    {
        self::$currentTest = $test;
    }

    public static function currentTest(): ?string
    {
        return self::$currentTest;
    }

    public static function record(string $model, string $relation): void
    {
        if (! self::$enabled) {
            return;
        }

        $fingerprint = Violation::fingerprintFor($model, $relation);

        self::$violations[$fingerprint] ??= new Violation($model, $relation);
        self::$violations[$fingerprint]->hit(self::$currentTest, self::callSite());
    }

    /** @return array<string, Violation> */
    public static function violations(): array
    {
        ksort(self::$violations);

        return self::$violations;
    }

    public static function reset(): void
    {
        self::$violations = [];
        self::$currentTest = null;
    }

    /**
     * First stack frame that belongs to application code.
     *
     * Frames inside vendor/ are skipped, otherwise every violation would point
     * at Eloquent internals instead of the line that actually caused it. So are
     * frames inside this package: installed as a dependency it sits under
     * vendor/ and the first rule covers it, but checked out and running its own
     * suite it does not, and every violation would be blamed on Collector.php.
     */
    private static function callSite(): ?string
    {
        $frames = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 40);
        $package = rtrim(self::normalise(__DIR__), '/').'/';

        foreach ($frames as $frame) {
            $file = $frame['file'] ?? null;

            if (! is_string($file)) {
                continue;
            }

            $normalised = self::normalise($file);

            if (str_contains($normalised, '/vendor/') || str_starts_with($normalised, $package)) {
                continue;
            }

            return self::resolve($file).':'.((int) ($frame['line'] ?? 0));
        }

        return null;
    }

    /**
     * Compiled Blade views live in storage/framework/views under a hashed name,
     * which is useless in a report. Laravel appends the original path as a
     * trailing comment, so recover it when present.
     */
    private static function resolve(string $file): string
    {
        if (str_contains(self::normalise($file), 'framework/views') && is_readable($file)) {
            $contents = (string) file_get_contents($file);

            if (preg_match('/\/\*\*PATH\s+(.*?)\s+ENDPATH\*\*\//s', $contents, $matches) === 1) {
                return self::relative($matches[1]).' (blade)';
            }
        }

        return self::relative($file);
    }

    private static function relative(string $file): string
    {
        $base = self::basePath();
        $normalised = self::normalise($file);

        if ($base === null) {
            return $normalised;
        }

        // Trailing separator, so a sibling directory sharing a prefix with the
        // project root is not mistaken for something inside it.
        $prefix = rtrim(self::normalise($base), '/').'/';

        return str_starts_with($normalised, $prefix)
            ? substr($normalised, strlen($prefix))
            : $normalised;
    }

    /**
     * base_path() resolves through the container, and the container is gone by
     * the time a torn-down test application is asked anything. Report paths are
     * cosmetic, so fall back to the working directory rather than throwing.
     */
    private static function basePath(): ?string
    {
        if (function_exists('base_path') && Container::getInstance()->bound('path.base')) {
            return base_path();
        }

        $cwd = getcwd();

        return $cwd === false ? null : $cwd;
    }

    /**
     * Backtrace paths use the platform separator, Laravel's own paths mix both.
     * Comparing normalised copies keeps the vendor/ and Blade checks working on
     * Windows, where they silently matched nothing before.
     */
    private static function normalise(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}
