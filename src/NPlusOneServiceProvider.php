<?php

declare(strict_types=1);

namespace BigEnergy\NPlusOne;

use BigEnergy\NPlusOne\Commands\BaselineCommand;
use BigEnergy\NPlusOne\Commands\CheckCommand;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

final class NPlusOneServiceProvider extends ServiceProvider
{
    /**
     * Guards the shutdown hook only.
     *
     * Eloquent's strict-mode switches look like they belong here too — they are
     * static properties on Model rather than container bindings, so the obvious
     * reading is that they outlive the application Testbench rebuilds between
     * tests. They do not: Testbench resets them in its teardown, by way of
     * ApplicationTestingHooks::tearDownTheApplicationTestingHooks(). Installing
     * them once per process collects violations from the first test and
     * silently nothing after it, which is worse than not collecting at all.
     * LazyLoadingTest::test_strict_mode_is_installed_by_the_service_provider
     * exists to keep that from coming back.
     */
    private static bool $shutdownHookRegistered = false;

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/nplusone.php', 'nplusone');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/nplusone.php' => config_path('nplusone.php'),
        ], 'nplusone-config');

        if ($this->app->runningInConsole()) {
            $this->commands([CheckCommand::class, BaselineCommand::class]);
        }

        // Re-evaluated on every boot. The collector is process-wide, so an
        // application booting with the package switched off has to switch it
        // off rather than inherit the previous application's answer.
        if (! $this->shouldCollect()) {
            Collector::disable();

            return;
        }

        Collector::enable();
        Reporter::rememberDefaultPath($this->reportPath());

        // Re-installed on every boot, not once per process: see the note on
        // $shutdownHookRegistered.
        //
        // preventLazyLoading() is Laravel's own strict mode. On its own it
        // throws on the first violation, which is why it is unusable on an
        // existing codebase. handleLazyLoadingViolationUsing() replaces that
        // exception with a callback, so the suite runs to completion and we
        // end up with the full picture instead of the first symptom.
        Model::preventLazyLoading();

        Model::handleLazyLoadingViolationUsing(static function (Model $model, string $relation): void {
            // Parity with the exception path. Laravel does not throw for a
            // model that has no row behind it yet or was created moments ago,
            // but that guard sits in Model::handleLazyLoadingViolation() *after*
            // the callback check, so registering a callback skips it. In
            // practice Laravel 12's Builder::hydrate() keeps most of these
            // away from us on its own, by only arming strict mode on models
            // from a result set of more than one row. That gate is an
            // implementation detail of one version in the supported range, so
            // the guard stays.
            if (! $model->exists || $model->wasRecentlyCreated) {
                return;
            }

            Collector::record($model::class, $relation);
        });

        // This one really is once per process. Fallback for runners that never
        // load the PHPUnit extension: a bare script, a paratest worker,
        // PHPUnit 9. Without it the report would never reach disk. flush() is
        // idempotent and swallows its own errors, so the extension having
        // already written the same file is harmless — but registering the hook
        // per boot would queue one call per test in the suite.
        if (! self::$shutdownHookRegistered) {
            self::$shutdownHookRegistered = true;

            register_shutdown_function(static function (): void {
                Reporter::flush();
            });
        }
    }

    private function shouldCollect(): bool
    {
        if ($this->config()->get('nplusone.enabled') !== true) {
            return false;
        }

        $environments = $this->config()->get('nplusone.environments', ['testing']);

        return is_array($environments) && $this->app->environment($environments);
    }

    private function reportPath(): string
    {
        $configured = $this->config()->get('nplusone.report_path');

        return is_string($configured) && $configured !== ''
            ? $configured
            : $this->app->basePath('.nplusone/report.json');
    }

    private function config(): Repository
    {
        return $this->app->make(Repository::class);
    }
}
