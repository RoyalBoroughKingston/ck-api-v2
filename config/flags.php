<?php
/*
|--------------------------------------------------------------------------
| Application Flags
|--------------------------------------------------------------------------
|
| Flags to control if optional app functionality is avaibale or not
|
*/
return [
    /**
     * Flag to allow use the CQC location ID field on Services.
     */
    'cqc_location' => env('CQC_LOCATION', false),
];
