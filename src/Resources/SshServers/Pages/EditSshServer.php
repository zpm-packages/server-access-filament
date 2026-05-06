<?php

namespace ZPMPackages\FilamentSshManagement\Resources\SshServers\Pages;

use ZPMPackages\FilamentSshManagement\Resources\SshServers\SshServerResource;
use App\Models\SshServer;
use App\Support\Ssh\SshSystemUserService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditSshServer extends EditRecord
{
    protected static string $resource = SshServerResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if ((bool) ($data['is_current_system'] ?? false) && blank($data['manager_username'] ?? null)) {
            $data['manager_username'] = app(SshSystemUserService::class)->defaultCurrentSystemManagerUsername();
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ((bool) ($data['is_current_system'] ?? false) && blank($data['manager_username'] ?? null)) {
            $data['manager_username'] = app(SshSystemUserService::class)->defaultCurrentSystemManagerUsername();
        }

        $data['host'] = (bool) ($data['is_current_system'] ?? false) ? null : ($data['host'] ?? null);
        $data['port'] = (bool) ($data['is_current_system'] ?? false) ? 22 : ($data['port'] ?? 22);
        $data['manager_password'] = null;

        if (
            $this->getRecord()->is_current_system
            && ($data['manager_username'] ?? null) !== $this->getRecord()->manager_username
        ) {
            Notification::make()
                ->danger()
                ->title('Use Change Manager User to verify the selected OS user before switching managers.')
                ->send();

            $this->halt();

            return $data;
        }

        if ((bool) ($data['is_current_system'] ?? false)) {
            SshServer::query()
                ->whereKeyNot($this->getRecord()->getKey())
                ->update(['is_current_system' => false]);
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        $managerCandidates = app(SshSystemUserService::class)->currentSystemManagerCandidates();

        return [
            Action::make('changeManagerUser')
                ->label('Change Manager User')
                ->visible(fn (): bool => (bool) $this->getRecord()->is_current_system)
                ->schema([
                    Select::make('manager_username')
                        ->label('Manager User')
                        ->options(array_combine($managerCandidates, $managerCandidates))
                        ->searchable()
                        ->required()
                        ->default($this->getRecord()->manager_username),
                    TextInput::make('password')
                        ->label('OS User Password')
                        ->password()
                        ->revealable()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    try {
                        app(SshSystemUserService::class)->changeCurrentSystemManagerUser(
                            $this->getRecord(),
                            (string) $data['manager_username'],
                            (string) $data['password'],
                        );
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->danger()
                            ->title(collect($exception->errors())->flatten()->first() ?? 'Unable to change the manager user.')
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->success()
                        ->title('Manager user updated.')
                        ->send();

                    $this->redirect(static::getResource()::getUrl('edit', ['record' => $this->getRecord()->refresh()]));
                }),
            DeleteAction::make(),
        ];
    }
}