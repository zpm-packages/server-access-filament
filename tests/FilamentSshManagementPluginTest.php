<?php

declare(strict_types=1);

namespace ZPMPackages\FilamentSshManagement\Tests;

use Filament\Panel;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;
use ZPMPackages\FilamentSshManagement\FilamentSshManagementPlugin;
use ZPMPackages\FilamentSshManagement\Resources\SshEntries\SshEntryResource;
use ZPMPackages\FilamentSshManagement\Resources\SshServers\SshServerResource;
use ZPMPackages\FilamentSshManagement\Resources\SshUsers\SshUserResource;

class FilamentSshManagementPluginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Container::setInstance(new Container());
    }

    public function test_it_has_a_stable_plugin_id(): void
    {
        $plugin = new FilamentSshManagementPlugin();

        $this->assertSame('ssh-management', $plugin->getId());
    }

    public function test_it_registers_all_ssh_resources_on_the_panel(): void
    {
        $plugin = new FilamentSshManagementPlugin();
        $panel = new class extends Panel {
            /**
             * @var array<class-string>
             */
            public array $registeredResources = [];

            public function resources(array $resources): static
            {
                $this->registeredResources = $resources;

                return $this;
            }

            public function getResources(): array
            {
                return $this->registeredResources;
            }
        };

        $plugin->register($panel);

        $this->assertSame([
            SshServerResource::class,
            SshUserResource::class,
            SshEntryResource::class,
        ], $panel->getResources());
    }

    public function test_make_resolves_the_plugin_from_the_container(): void
    {
        $instance = new FilamentSshManagementPlugin();

        Container::getInstance()->instance(FilamentSshManagementPlugin::class, $instance);

        $this->assertSame($instance, FilamentSshManagementPlugin::make());
    }
}