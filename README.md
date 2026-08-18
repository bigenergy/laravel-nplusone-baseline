# Laravel N+1 Baseline

Catch N+1 regressions in CI, on a codebase that already has hundreds of them.

[![tests](https://github.com/bigenergy/laravel-nplusone-baseline/actions/workflows/tests.yml/badge.svg)](https://github.com/bigenergy/laravel-nplusone-baseline/actions/workflows/tests.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

---

## The problem

You fix an N+1 on the orders endpoint. 120 queries become 8. Three months later
somebody adds `{{ $order->manager->name }}` to a Blade template. Nothing breaks,
tests stay green, the diff is one harmless line — and the endpoint is back to 120
queries. You find out six months later when a user complains the page is slow.

Laravel already ships the detection mechanism for this. `Model::preventLazyLoading()`
throws a `LazyLoadingViolationException` the moment a relation is lazy loaded, and
`Model::handleLazyLoadingViolationUsing()` lets you replace that exception with your
own callback. **This package does not reimplement any of that** — it builds the two
things on top that are missing when you try to use strict mode on a real project:

1. **It collects instead of throwing.** Strict mode aborts on the first violation, so
   on an existing codebase you learn about violation #1 out of 300 and nothing else.
   Here the suite runs to completion and you get the whole picture at once.
2. **It has a baseline.** The 300 violations you already have get recorded as accepted.
   CI stays quiet about them and fails on #301. This is the part that makes the check
   adoptable in an afternoon rather than after a multi-week cleanup — the same reason
   PHPStan ships a baseline.

Net effect: **N+1s stop being found in production and start being found in the pull request.**

## Installation

```bash
composer require --dev bigenergy/laravel-nplusone-baseline
```

Register the PHPUnit extension in `phpunit.xml` so violations can be attributed to
the test that triggered them:

```xml
<extensions>
    <bootstrap class="BigEnergy\NPlusOne\PHPUnit\NPlusOneExtension"/>
</extensions>
```

Optionally publish the config:

```bash
php artisan vendor:publish --tag=nplusone-config
```

## Adopting it on an existing project

```bash
php artisan test              # collects violations, writes .nplusone/report.json
php artisan nplusone:baseline # accepts them all
git add .nplusone/baseline.json && git commit -m "Baseline current N+1 queries"
```

That is the whole adoption step. Nothing had to be fixed first.

## In CI

```yaml
- run: php artisan test
- run: php artisan nplusone:check
```

`nplusone:check` exits non-zero only when the run contains a fingerprint that is not
in the baseline:

```
  ERROR  1 new N+1 query introduced.

  ✗ App\Models\Order::customer ×14
      at app/Http/Resources/OrderResource.php:22
      in Tests\Feature\OrderApiTest::test_index

  Fix them with eager loading, or accept them with: php artisan nplusone:baseline
```

When somebody fixes an N+1, its baseline entry is reported as stale so the file can be
pruned and does not keep silently granting permission for something that was cleaned up
months ago. Stale entries do not fail the build unless you pass `--prune`.

## Design decisions

**Fingerprint is `Model::relation`, not the call site.** Call sites and test names move
during refactoring; the model/relation pair does not. Both are still recorded and shown
in the report so you know where to look, but they do not participate in the comparison.

**Counts are not compared.** The same endpoint lazy loading the same relation fires a
different number of times depending on how many rows your factories happened to create.
Comparing counts produces red builds that have nothing to do with the code under review,
which is the fastest way to get a CI check disabled.

**Your test suite is the scanner.** Your tests already exercise the controllers that
cause the lazy loads, so no separate crawling step or instrumentation is needed. The
flip side is the obvious one: coverage of this check equals coverage of your test suite.
Code no test touches is invisible to it.

**Testing environment only.** This is a CI tool, not an APM. For finding N+1s in
production traffic you want something that samples real requests; Telescope writes
inline to the request lifecycle and does not aggregate, so it is not that either.

## Requirements

- PHP 8.2, 8.3 or 8.4
- Laravel 11 / 12
- PHPUnit 10, 11 or 12 for test attribution (on PHPUnit 9 violations are still
  collected, just without test names, and the report is written by a shutdown hook
  instead of by the extension)

Pest works — it runs on PHPUnit, so the extension applies unchanged.

## Known limitations

- **Single-row queries are invisible.** Laravel arms strict mode on a model only when
  it came out of a result set with more than one row (`Builder::hydrate()`), on the
  reasoning that one model cannot be an N+1. A relation lazy loaded on a model from
  `find()`, `first()` or `firstOrFail()` therefore never counts as a violation — for
  Laravel's own exception, and so for this package. Coverage here is exactly Laravel's.
- Blade violations are resolved back to the `.blade.php` source via the `PATH` comment
  Laravel embeds in compiled views. If that comment is absent, the compiled file path
  is reported instead.
- Tests running with process isolation collect per-process; the report is written by
  whichever process finishes last, so results may be incomplete.
- Relations lazy loaded inside queued jobs dispatched with `Queue::fake()` are captured;
  jobs on a real worker in a separate process are not.

## License

MIT
