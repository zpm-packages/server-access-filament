<?php

namespace ZPMPackages\FilamentSshManagement\Resources\SshServers\Pages;

use ZPMPackages\FilamentSshManagement\Resources\SshServers\SshServerResource;
use App\Models\SshServer;
use App\Support\Ssh\SshSystemUserService;
use Filament\Resources\Pages\CreateRecord;

class CreateSshServer extends CreateRecord
{
    protected static string $resource = SshServerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if ((bool) ($data['is_current_system'] ?? false) && blank($data['manager_username'] ?? null)) {
            $data['manager_username'] = app(SshSystemUserService::class)->defaultCurrentSystemManagerUsername();
        }

        $data['host'] = (bool) ($data['is_current_system'] ?? false) ? null : ($data['host'] ?? null);
        $data['port'] = (bool) ($data['is_current_system'] ?? false) ? 22 : ($data['port'] ?? 22);
        $data['manager_password'] = null;

        if ((bool) ($data['is_current_system'] ?? false)) {
            SshServer::query()->update(['is_current_system' => false]);
        }

        return $data;
    }
}