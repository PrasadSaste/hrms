<?php

use App\Providers\AppServiceProvider;
use App\Providers\InstallServiceProvider;

return [
    // First: on a server that is not set up yet this is what makes the
    // application bootable at all, and AppServiceProvider goes looking for
    // settings in a database that may not exist.
    InstallServiceProvider::class,
    AppServiceProvider::class,
];
