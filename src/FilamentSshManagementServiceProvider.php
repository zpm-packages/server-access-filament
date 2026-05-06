<?php

namespace ZPMPackages\FilamentSshManagement;

use Illuminate\Support\ServiceProvider;

class FilamentSshManagementServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FilamentSshManagementPlugin::class);
    }

    public function boot(): void
    {
    }
}
