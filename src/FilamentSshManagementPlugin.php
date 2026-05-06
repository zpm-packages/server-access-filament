<?php

namespace ZPMPackages\FilamentSshManagement;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Illuminate\Container\Container;
use ZPMPackages\FilamentSshManagement\Resources\SshEntries\SshEntryResource;
use ZPMPackages\FilamentSshManagement\Resources\SshServers\SshServerResource;
use ZPMPackages\FilamentSshManagement\Resources\SshUsers\SshUserResource;

class FilamentSshManagementPlugin implements Plugin
{
    public static function make(): static
    {
        return Container::getInstance()->make(static::class);
    }

    public function getId(): string
    {
        return 'ssh-management';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            SshServerResource::class,
            SshUserResource::class,
            SshEntryResource::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
    }
}
