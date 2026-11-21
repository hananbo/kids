<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\ParentPanelProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\Games\HebrewQuizServiceProvider;
use App\Providers\HorizonServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    ParentPanelProvider::class,
    FortifyServiceProvider::class,
    HebrewQuizServiceProvider::class,
    HorizonServiceProvider::class,
];
