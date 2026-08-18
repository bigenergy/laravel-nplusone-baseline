<?php

declare(strict_types=1);

namespace BigEnergy\NPlusOne\Tests\Unit;

use BigEnergy\NPlusOne\Violation;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ViolationTest extends TestCase
{
    public function test_the_fingerprint_is_the_model_and_the_relation_and_nothing_else(): void
    {
        $violation = new Violation('App\Models\Invoice', 'lines');
        $violation->hit('Tests\Feature\InvoiceTest::test_show', 'app/Models/Invoice.php:12');

        $this->assertSame('App\Models\Invoice::lines', $violation->fingerprint());
        $this->assertSame(
            $violation->fingerprint(),
            Violation::fingerprintFor('App\Models\Invoice', 'lines'),
        );
    }

    public function test_the_same_test_and_site_are_only_recorded_once(): void
    {
        $violation = new Violation('App\Models\Order', 'manager');
        $violation->hit('Tests\Feature\OrderApiTest::test_index', 'app/Models/Order.php:41');
        $violation->hit('Tests\Feature\OrderApiTest::test_index', 'app/Models/Order.php:41');

        $this->assertSame(2, $violation->count());
        $this->assertSame(['Tests\Feature\OrderApiTest::test_index'], $violation->tests());
        $this->assertSame(['app/Models/Order.php:41'], $violation->sites());
    }

    public function test_context_is_capped_so_one_noisy_relation_cannot_bloat_the_baseline(): void
    {
        $violation = new Violation('App\Models\Order', 'manager');

        for ($i = 0; $i < 12; $i++) {
            $violation->hit("Tests\Feature\OrderApiTest::test_{$i}", "app/Models/Order.php:{$i}");
        }

        $this->assertSame(12, $violation->count());
        $this->assertCount(5, $violation->toArray()['tests']);
        $this->assertCount(5, $violation->toArray()['sites']);
    }

    public function test_a_violation_survives_the_round_trip_through_its_array_form(): void
    {
        $violation = new Violation('App\Models\Order', 'manager');
        $violation->hit('Tests\Feature\OrderApiTest::test_index', 'app/Models/Order.php:41');
        $violation->hit('Tests\Feature\OrderApiTest::test_show', 'app/Models/Order.php:52');

        $restored = Violation::fromArray($violation->toArray());

        $this->assertSame($violation->fingerprint(), $restored->fingerprint());
        $this->assertSame($violation->count(), $restored->count());
        $this->assertSame($violation->tests(), $restored->tests());
        $this->assertSame($violation->sites(), $restored->sites());
    }

    public function test_an_entry_without_a_model_or_relation_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Violation::fromArray(['count' => 3]);
    }
}
