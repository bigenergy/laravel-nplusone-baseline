# Contributing

## Getting set up

```bash
composer install
```

That is everything. The tests run against an in-memory SQLite database through
[Orchestra Testbench](https://packages.tools/testbench), so there is nothing to
provision — you need PHP 8.2+ with `pdo_sqlite`.

```bash
composer test      # phpunit
composer analyse   # phpstan, level max, via larastan
composer lint      # pint --test, reports without rewriting
composer format    # pint, rewrites
```

## How the suite is laid out

- `tests/Unit` — pure logic, no framework. Aggregation, fingerprinting, the
  report round trip, and every baseline comparison rule.
- `tests/Integration` — real Eloquent models, real lazy loads, real Artisan
  commands, and one test (`ReportIsWrittenTest`) that shells out to a second
  PHPUnit process because "the report is written when the run finishes" cannot
  be observed from inside the run that is finishing.
- `tests/Fixtures/Suite` — the child suite that second process executes. It is
  deliberately outside the `Unit`/`Integration` test suites so the parent run
  never picks it up.

## Things that are deliberate

Please raise an issue before changing any of these; each one is a decision, not
an oversight.

- **The fingerprint is `Model::relation` and nothing else.** Call sites and test
  names move during refactoring; the model/relation pair does not. Both are
  recorded and displayed, but neither participates in the comparison.
- **Counts are never compared.** The same relation fires a different number of
  times depending on how many rows the factories created. Comparing counts
  produces red builds unrelated to the change under review, which is the fastest
  way to get a CI check switched off.
- **Stale baseline entries do not fail the build** unless `--prune` is passed.
- **The README says plainly that detection is Laravel's own `preventLazyLoading`.**
  The package adds collection and a baseline; it does not detect anything itself.

## Releasing

1. Update `CHANGELOG.md`: move everything under `## [Unreleased]` into a new
   version heading with today's date, and refresh the link definitions at the
   bottom.
2. Make sure CI is green on `master` — tests across the whole PHP/Laravel
   matrix, PHPStan, and Pint.
3. Tag and push:

   ```bash
   git tag -a v0.1.0 -m "v0.1.0"
   git push origin master --follow-tags
   ```

4. Publish a GitHub release for the tag, pasting in the changelog entry.
5. First release only: submit the package at
   [packagist.org/packages/submit](https://packagist.org/packages/submit) with
   the repository URL `https://github.com/bigenergy/laravel-nplusone-baseline`.
6. First release only: enable auto-updates so Packagist picks up later tags on
   its own. On Packagist, open the package page, press **Manage Hooks**, and
   follow it to GitHub — or add it by hand under
   **Settings → Webhooks → Add webhook** on the repository:

   - Payload URL: `https://packagist.org/api/github?username=<packagist-username>`
   - Content type: `application/json`
   - Secret: your Packagist API token
   - Events: just the push event

   With the hook in place, pushing a tag is the whole release.
