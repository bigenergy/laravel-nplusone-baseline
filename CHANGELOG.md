# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.0] - 2026-08-18

### Added

- Collects Eloquent lazy-loading violations across a whole test run instead of
  throwing on the first one, by handing `Model::handleLazyLoadingViolationUsing()`
  a callback that records rather than raises.
- A committed baseline of accepted violations, so the check can be switched on
  for a codebase that already has hundreds of them.
- `nplusone:check`, which exits non-zero only when the run contains a
  `Model::relation` fingerprint the baseline does not list. Counts are never
  compared; stale entries are reported but only fail the build with `--prune`.
- `nplusone:baseline`, which accepts the current run as the new baseline.
- A PHPUnit 10/11/12 extension that attributes each violation to the test that
  triggered it and writes the report once the run finishes.
- Blade violations are resolved back to the `.blade.php` source through the
  `PATH` comment Laravel embeds in compiled views.

[Unreleased]: https://github.com/bigenergy/laravel-nplusone-baseline/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/bigenergy/laravel-nplusone-baseline/releases/tag/v0.1.0
