<?php

declare(strict_types=1);

namespace BigEnergy\NPlusOne\Tests\Integration;

use BigEnergy\NPlusOne\PHPUnit\NPlusOneExtension;
use BigEnergy\NPlusOne\PHPUnit\RecordCurrentTest;
use BigEnergy\NPlusOne\PHPUnit\WriteReportWhenRunEnds;
use PHPUnit\Event\Code\Test;
use PHPUnit\Event\Test\Prepared;
use PHPUnit\Event\Test\PreparedSubscriber;
use PHPUnit\Event\TestRunner\ExecutionFinished;
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
 */
final class ExtensionApiTest extends TestCase
{
    public function test_the_extension_matches_the_installed_extension_interface(): void
    {
        // Instantiating is most of the assertion: PHP refuses to load the class
        // at all if bootstrap() no longer matches Extension::bootstrap().
        $this->assertInstanceOf(Extension::class, new NPlusOneExtension());
    }

    public function test_the_runner_facade_still_accepts_variadic_subscribers(): void
    {
        $this->assertTrue(method_exists(Facade::class, 'registerSubscribers'));

        $parameters = (new ReflectionMethod(Facade::class, 'registerSubscribers'))->getParameters();
        $first = $parameters[0] ?? null;

        $this->assertInstanceOf(ReflectionParameter::class, $first);
        $this->assertTrue($first->isVariadic());
    }

    public function test_the_subscribers_implement_the_events_they_are_registered_for(): void
    {
        $this->assertInstanceOf(PreparedSubscriber::class, new RecordCurrentTest());
        $this->assertInstanceOf(ExecutionFinishedSubscriber::class, new WriteReportWhenRunEnds());
    }

    public function test_the_events_still_expose_what_the_subscribers_read_from_them(): void
    {
        $this->assertTrue(method_exists(Prepared::class, 'test'));
        $this->assertTrue(method_exists(Test::class, 'id'));
        $this->assertTrue(interface_exists(ExecutionFinishedSubscriber::class));
        $this->assertTrue(class_exists(ExecutionFinished::class));
    }
}
