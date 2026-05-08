<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\StudentPanelProvider;
use App\Providers\Filament\TeacherPanelProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    StudentPanelProvider::class,
    TeacherPanelProvider::class,
];
