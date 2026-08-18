<?php

declare(strict_types=1);

namespace BigEnergy\NPlusOne\PHPUnit;

use BigEnergy\NPlusOne\Collector;
use PHPUnit\Event\Test\Prepared;
use PHPUnit\Event\Test\PreparedSubscriber;

/**
 * Attributes everything the collector records from here on to the test that is
 * about to run. Prepared fires after the test case has been set up and before
 * its body executes, which is the last point where the name is still known.
 */
final class RecordCurrentTest implements PreparedSubscriber
{
    public function notify(Prepared $event): void
    {
        Collector::setCurrentTest($event->test()->id());
    }
}
