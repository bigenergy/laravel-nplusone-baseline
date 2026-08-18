<?php

declare(strict_types=1);

namespace BigEnergy\NPlusOne\PHPUnit;

use BigEnergy\NPlusOne\Collector;
use BigEnergy\NPlusOne\Reporter;
use PHPUnit\Event\TestRunner\ExecutionFinished;
use PHPUnit\Event\TestRunner\ExecutionFinishedSubscriber;

/**
 * Writes the report once every test has run.
 *
 * The last test has already torn its application down by this point, so the
 * path cannot be read from config() any more — Reporter remembers it while a
 * container is still alive for exactly this moment.
 */
final class WriteReportWhenRunEnds implements ExecutionFinishedSubscriber
{
    public function notify(ExecutionFinished $event): void
    {
        Collector::setCurrentTest(null);

        Reporter::flush();
    }
}
