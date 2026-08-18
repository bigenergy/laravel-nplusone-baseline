<?php

declare(strict_types=1);

namespace BigEnergy\NPlusOne;

use InvalidArgumentException;

/**
 * A single lazy-loading violation, aggregated by model + relation.
 *
 * The fingerprint deliberately does NOT include the call site or the test name.
 * Those move around during refactoring; the model/relation pair does not.
 * They are still recorded as context so the report tells you where to look.
 */
final class Violation
{
    private int $count = 0;

    /** @var array<string, true> */
    private array $tests = [];

    /** @var array<string, true> */
    private array $sites = [];

    public function __construct(
        public readonly string $model,
        public readonly string $relation,
    ) {}

    public static function fingerprintFor(string $model, string $relation): string
    {
        return $model.'::'.$relation;
    }

    /**
     * Rebuild a violation from the shape toArray() produced.
     *
     * The test run and the check run in separate processes, so every violation
     * makes a round trip through JSON on the way to nplusone:check.
     *
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $model = $data['model'] ?? null;
        $relation = $data['relation'] ?? null;

        if (! is_string($model) || ! is_string($relation)) {
            throw new InvalidArgumentException('A violation needs both a model and a relation.');
        }

        $violation = new self($model, $relation);

        $count = $data['count'] ?? 0;
        $violation->count = is_int($count) && $count > 0 ? $count : 0;

        foreach (self::stringList($data['tests'] ?? []) as $test) {
            $violation->tests[$test] = true;
        }

        foreach (self::stringList($data['sites'] ?? []) as $site) {
            $violation->sites[$site] = true;
        }

        return $violation;
    }

    public function fingerprint(): string
    {
        return self::fingerprintFor($this->model, $this->relation);
    }

    public function hit(?string $test, ?string $site): void
    {
        $this->count++;

        if ($test !== null) {
            $this->tests[$test] = true;
        }

        if ($site !== null) {
            $this->sites[$site] = true;
        }
    }

    public function count(): int
    {
        return $this->count;
    }

    /** @return list<string> */
    public function tests(): array
    {
        return array_keys($this->tests);
    }

    /** @return list<string> */
    public function sites(): array
    {
        return array_keys($this->sites);
    }

    /** @return array{model: string, relation: string, count: int, tests: list<string>, sites: list<string>} */
    public function toArray(): array
    {
        return [
            'model' => $this->model,
            'relation' => $this->relation,
            'count' => $this->count,
            'tests' => array_slice($this->tests(), 0, 5),
            'sites' => array_slice($this->sites(), 0, 5),
        ];
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $strings = [];

        foreach ($value as $item) {
            if (is_string($item)) {
                $strings[] = $item;
            }
        }

        return $strings;
    }
}
