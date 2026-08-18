<?php

declare(strict_types=1);

namespace BigEnergy\NPlusOne\PHPUnit;

use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

/**
 * Tells the collector which test is currently running, so a violation can be
 * reported as "Order::manager, in OrderApiTest::test_index" rather than as a
 * bare class name with no route back to the code that triggered it, and writes
 * the report once the run is over.
 *
 * Register in phpunit.xml:
 *
 *   <extensions>
 *       <bootstrap class="BigEnergy\NPlusOne\PHPUnit\NPlusOneExtension"/>
 *   </extensions>
 *
 * Requires PHPUnit 10+. On PHPUnit 9 the package still collects violations via
 * the service provider; they simply carry no test name, and the report is
 * written by the shutdown hook instead.
 */
final class NPlusOneExtension implements Extension
{
    public function bootstrap(
        Configuration $configuration,
        Facade $facade,
        ParameterCollection $parameters,
    ): void {
        $facade->registerSubscribers(
            new RecordCurrentTest,
            new WriteReportWhenRunEnds,
        );
    }
}
