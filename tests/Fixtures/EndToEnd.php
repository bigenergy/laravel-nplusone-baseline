<?php

declare(strict_types=1);

namespace BigEnergy\NPlusOne\Tests\Fixtures;

/**
 * Paths shared between the parent test and the child PHPUnit process it spawns.
 *
 * A fixed location rather than an environment variable: the child is a real
 * `phpunit -c ...` invocation, and one less thing to plumb through it is one
 * less thing that can silently fail to arrive.
 */
final class EndToEnd
{
    public static function directory(): string
    {
        return sys_get_temp_dir().DIRECTORY_SEPARATOR.'nplusone-end-to-end';
    }

    public static function reportPath(): string
    {
        return self::directory().DIRECTORY_SEPARATOR.'report.json';
    }

    public static function baselinePath(): string
    {
        return self::directory().DIRECTORY_SEPARATOR.'baseline.json';
    }
}
