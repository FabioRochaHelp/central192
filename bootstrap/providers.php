<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\TenancyServiceProvider;

return [
    TenancyServiceProvider::class,
    AppServiceProvider::class,
    FortifyServiceProvider::class,
];
