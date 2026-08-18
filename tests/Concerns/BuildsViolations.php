<?php

declare(strict_types=1);

namespace BigEnergy\NPlusOne\Tests\Concerns;

use BigEnergy\NPlusOne\Violation;

trait BuildsViolations
{
    protected function violation(string $model, string $relation, int $hits = 1): Violation
    {
        $violation = new Violation($model, $relation);

        for ($i = 0; $i < $hits; $i++) {
            $violation->hit(
                'Tests\Feature\ExampleTest::test_index',
                'app/Http/Controllers/ExampleController.php:31',
            );
        }

        return $violation;
    }

    /**
     * @return array<string, Violation>
     */
    protected function keyed(Violation ...$violations): array
    {
        $keyed = [];

        foreach ($violations as $violation) {
            $keyed[$violation->fingerprint()] = $violation;
        }

        ksort($keyed);

        return $keyed;
    }
}
