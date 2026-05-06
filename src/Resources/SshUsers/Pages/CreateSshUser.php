<?php

namespace ZPMPackages\FilamentSshManagement\Resources\SshUsers\Pages;

use ZPMPackages\FilamentSshManagement\Resources\SshUsers\SshUserResource;
use App\Models\SshServer;
use App\Models\SshUser;
use App\Support\Ssh\SshActorAuthorizer;
use App\Support\Ssh\SshDirectDataService;
use App\Support\Ssh\SshManagerResolver;
use App\Support\Ssh\SshSystemUserService;
use App\Support\Ssh\SshUserManagerService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateSshUser extends CreateRecord
{
    protected static string $resource = SshUserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = $this->normalizeFormData($data);
        $server = $this->getParentRecord();

        if ($server instanceof SshServer) {
            $data['ssh_server_id'] = $server->getKey();
        }

        $message = app(SshActorAuthorizer::class)->denialMessage(
            targetServer: $server instanceof SshServer ? $server : null,
            targetHomeDirectory: filled($data['home_directory'] ?? null) ? (string) $data['home_directory'] : null,
        );

        if ($message === null) {
            return $data;
        }

        Notification::make()
            ->danger()
            ->title($message)
            ->send();

        $this->halt();

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $server = $this->getParentRecord();
        $password = filled($data['password'] ?? null) ? (string) $data['password'] : null;

        if ($password !== null) {
            $data['ssh_key_passphrase'] = $password;
        }

        unset($data['password'], $data['password_confirmation']);

        if (! app(SshManagerResolver::class)->usesDatabase()) {
            try {
                $user = app(SshDirectDataService::class)->createUser($data, $server);

                $this->applyInitialPasswordIfProvided($user, $password);

                return $user;
            } catch (\RuntimeException $exception) {
                throw ValidationException::withMessages([
                    'data.username' => $exception->getMessage(),
                ]);
            }
        }

        $user = SshUser::query()->create($data);

        $user = app(SshUserManagerService::class)->syncUser(
            $user,
            $server instanceof SshServer ? $server : null,
            creating: true,
            keyPassphrase: $password,
        );

        $this->applyInitialPasswordIfProvided($user, $password);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeFormData(array $data): array
    {
        $data['groups'] = SshUser::normalizeList($data['groups'] ?? []);
        $data['managed_directories'] = SshUser::normalizeList($data['managed_directories'] ?? []);

        return $data;
    }

    private function applyInitialPasswordIfProvided(Model $user, ?string $password): void
    {
        if (blank($password)) {
            return;
        }

        try {
            app(SshSystemUserService::class)->updateUserPassword($user, $password);
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title(collect($exception->errors())->flatten()->first() ?? 'The OS user was created, but the password could not be set.')
                ->send();
        }
    }
}
