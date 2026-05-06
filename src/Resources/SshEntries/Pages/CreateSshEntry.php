<?php

namespace ZPMPackages\FilamentSshManagement\Resources\SshEntries\Pages;

use ZPMPackages\FilamentSshManagement\Resources\SshEntries\SshEntryResource;
use App\Models\SshEntry;
use App\Models\SshUser;
use App\Support\Ssh\SshActorAuthorizer;
use App\Support\Ssh\SshUserManagerService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class CreateSshEntry extends CreateRecord
{
    protected static string $resource = SshEntryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $targetUser = filled($data['ssh_user_id'] ?? null)
            ? SshUser::query()->find((string) $data['ssh_user_id'])
            : null;

        $message = app(SshActorAuthorizer::class)->denialMessage(
            targetUser: $targetUser,
            targetHomeDirectory: $targetUser?->home_directory,
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
        try {
            if (($data['creation_mode'] ?? 'generate') === 'generate') {
                $user = SshUser::query()->findOrFail((string) $data['ssh_user_id']);

                return app(SshUserManagerService::class)->generateKey(
                    $user,
                    $user->sshServer,
                    filled($data['name'] ?? null) ? (string) $data['name'] : null,
                    filled($data['key_type'] ?? null) ? (string) $data['key_type'] : 'ed25519',
                    filled($data['key_bits'] ?? null) ? (int) $data['key_bits'] : null,
                    filled($data['comment'] ?? null) ? (string) $data['comment'] : null,
                    filled($data['public_key_path'] ?? null) ? (string) $data['public_key_path'] : null,
                    filled($data['private_key_path'] ?? null) ? (string) $data['private_key_path'] : null,
                );
            }

            $record = SshEntry::query()->create($data + ['is_managed' => false]);
            $user = $record->sshUser()->firstOrFail();

            app(SshUserManagerService::class)->syncUser($user);

            return SshEntry::query()
                ->where('ssh_user_id', $user->getKey())
                ->where('public_key', $record->public_key)
                ->firstOrFail();
        } catch (Throwable $exception) {
            $message = $this->resolveKeyCreationFailureMessage($exception);

            Notification::make()
                ->danger()
                ->title($message)
                ->send();

            $this->halt();

            return new SshEntry();
        }
    }

    private function resolveKeyCreationFailureMessage(Throwable $exception): string
    {
        $message = trim($exception->getMessage());
        $normalizedMessage = strtolower($message);

        if (str_contains($normalizedMessage, 'unable to resolve the home directory for windows user [')) {
            return 'Unable to create the SSH key because Windows could not resolve the target user profile directory. The key was not created.';
        }

        if (str_contains($normalizedMessage, 'custom key paths must stay inside the target user .ssh directory.')) {
            return 'Custom key paths must stay inside the selected OS user .ssh directory.';
        }

        if (str_contains($normalizedMessage, 'the public key path must match the private key path with a .pub suffix.')) {
            return 'The public key path must match the private key path with a .pub suffix.';
        }

        if (str_contains($normalizedMessage, 'permission denied') || str_contains($normalizedMessage, 'unable to create directory [')) {
            return 'Unable to create the SSH key because Windows denied access to the target .ssh directory. "Act As" only changes panel authorization; it does not elevate the Herd/PHP process. Run the process with the needed OS privileges or configure manager credentials for this server.';
        }

        return filled($message)
            ? 'Unable to create the SSH key. ' . $message
            : 'Unable to create the SSH key because an unexpected error occurred.';
    }
}
