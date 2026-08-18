<?php

declare(strict_types=1);

namespace BigEnergy\NPlusOne\Tests\Integration;

use BigEnergy\NPlusOne\PHPUnit\NPlusOneExtension;
use BigEnergy\NPlusOne\PHPUnit\RecordCurrentTest;
use BigEnergy\NPlusOne\PHPUnit\WriteReportWhenRunEnds;
use PHPUnit\Event\Code\Test;
use PHPUnit\Event\Test\Prepared;
use PHPUnit\Event\Test\PreparedSubscriber;
use PHPUnit\Event\TestRunner\ExecutionFinishedSubscriber;
use PHPUnit\Framework\TestCase;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use ReflectionMethod;
use ReflectionParameter;

/**
 * Pins the PHPUnit event API the extension is written against.
 *
 * The package supports three PHPUnit majors and this corner of the API has
 * moved before. When it moves again these fail with a name, instead of the
 * whole suite quietly collecting nothing.
 *
 * Everything here is checked through reflection rather than instanceof: a
 * static analyser resolves instanceof against the version that happens to be
 * installed and calls the assertion redundant, which is precisely the check
 * being made — just at the wrong time.
 */
final class ExtensionApiTest extends TestCase
{
    public function test_the_extension_matches_the_installed_extension_interface(): void
    {
        // Loading the class is most of the assertion: PHP refuses to load it at
        // all if bootstrap() stops matching Extension::bootstrap().
        $extension = new NPlusOneExtension;

        $this->assertContains(Extension::class, class_implements($extension) ?: []);

        $bootstrap = new ReflectionMethod($extension, 'bootstrap');

        $this->assertTrue($bootstrap->isPublic());
        $this->assertSame(3, $bootstrap->getNumberOfParameters());
    }

    public function test_the_runner_facade_still_accepts_variadic_subscribers(): void
    {
        $parameters = (new ReflectionMethod(Facade::class, 'registerSubscribers'))->getParameters();
        $first = $parameters[0] ?? null;

        $this->assertInstanceOf(ReflectionParameter::class, $first);
        $this->assertTrue($first->isVariadic());
    }

    public function test_the_subscribers_implement_the_events_they_are_registered_for(): void
    {
        $this->assertContains(
            PreparedSubscriber::class,
            class_implements(new RecordCurrentTest) ?: [],
        );
        $this->assertContains(
            ExecutionFinishedSubscriber::class,
            class_implements(new WriteReportWhenRunEnds) ?: [],
        );
    }

    public function test_the_events_still_expose_what_the_subscribers_read_from_them(): void
    {
        // Constructing these throws ReflectionException the moment PHPUnit
        // renames or drops either method, which is the breakage worth catching.
        $this->assertTrue((new ReflectionMethod(Prepared::class, 'test'))->isPublic());
        $this->assertTrue((new ReflectionMethod(Test::class, 'id'))->isPublic());
    }
}
