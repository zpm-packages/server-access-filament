<?php

namespace ZPMPackages\FilamentSshManagement\Resources\SshEntries\Pages;

use ZPMPackages\FilamentSshManagement\Resources\SshEntries\SshEntryResource;
use App\Models\SshEntry;
use App\Support\Ssh\SshActorAuthorizer;
use App\Support\Ssh\SshUserManagerService;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditSshEntry extends EditRecord
{
    protected static string $resource = SshEntryResource::class;

    protected function afterFill(): void
    {
        $actingAs = session('ssh-manager.acting_as');

        if (($actingAs['user_id'] ?? null) !== (string) $this->getRecord()->ssh_user_id) {
            return;
        }

        $notice = session()->pull('ssh-manager.act_as_notice');

        if (! is_string($notice) || blank($notice)) {
            return;
        }

        Notification::make()
            ->success()
            ->title($notice)
            ->send();
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $message = app(SshActorAuthorizer::class)->denialMessage(
            targetKey: $this->getRecord(),
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

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $key = $record instanceof SshEntry ? $record : SshEntry::query()->findOrFail($record->getKey());

        $key->fill($data);
        $key->save();

        $user = $key->sshUser()->firstOrFail();

        app(SshUserManagerService::class)->syncUser($user);

        return SshEntry::query()
            ->where('ssh_user_id', $user->getKey())
            ->where('public_key', $key->public_key)
            ->firstOrFail();
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->action(function (DeleteAction $action): void {
                    $message = app(SshActorAuthorizer::class)->denialMessage(targetKey: $this->getRecord());

                    if ($message !== null) {
                        Notification::make()
                            ->danger()
                            ->title($message)
                            ->send();

                        $action->halt();
                    }

                    $user = $this->getRecord()->sshUser()->first();

                    $this->getRecord()->delete();

                    if ($user !== null) {
                        app(SshUserManagerService::class)->syncUser($user);
                    }
                })
                ->successRedirectUrl(SshEntryResource::getUrl('index')),
        ];
    }
}
