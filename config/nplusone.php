<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Collection
    |--------------------------------------------------------------------------
    |
    | When enabled, Eloquent's strict lazy-loading mode is switched on and every
    | violation is recorded instead of thrown. Keep this off in production: the
    | package is a CI tool, not an APM.
    |
    */

    'enabled' => env('NPLUSONE_ENABLED', true),

    'environments' => ['testing'],

    /*
    |--------------------------------------------------------------------------
    | Paths
    |--------------------------------------------------------------------------
    |
    | The report is regenerated on every run and should be gitignored. The
    | baseline is the accepted state of the project and must be committed.
    |
    */

    'report_path' => base_path('.nplusone/report.json'),

    'baseline_path' => base_path('.nplusone/baseline.json'),

];
