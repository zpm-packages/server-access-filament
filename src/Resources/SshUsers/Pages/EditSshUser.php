<?php

namespace ZPMPackages\FilamentSshManagement\Resources\SshUsers\Pages;

use ZPMPackages\FilamentSshManagement\Resources\SshServers\SshServerResource;
use ZPMPackages\FilamentSshManagement\Resources\SshUsers\SshUserResource;
use App\Models\SshServer;
use App\Models\SshUser;
use App\Support\Ssh\SshActorAuthorizer;
use App\Support\Ssh\SshDirectDataService;
use App\Support\Ssh\SshManagerResolver;
use App\Support\Ssh\SshSystemUserService;
use App\Support\Ssh\SshUserManagerService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Throwable;
use Illuminate\Validation\ValidationException;

class EditSshUser extends EditRecord
{
    protected static string $resource = SshUserResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['groups'] = SshUser::normalizeList($data['groups'] ?? []);
        $data['managed_directories'] = SshUser::normalizeList($data['managed_directories'] ?? []);

        return $data;
    }

    protected function afterFill(): void
    {
        $actingAs = session('ssh-manager.acting_as');

        if (($actingAs['user_id'] ?? null) !== (string) $this->getRecord()->getKey()) {
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
        $data['groups'] = SshUser::normalizeList($data['groups'] ?? []);
        $data['managed_directories'] = SshUser::normalizeList($data['managed_directories'] ?? []);
        $server = $this->getRecord()->sshServer;

        $message = app(SshActorAuthorizer::class)->denialMessage(
            targetServer: $server,
            targetUser: $this->getRecord(),
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

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! app(SshManagerResolver::class)->usesDatabase()) {
            return app(SshDirectDataService::class)->updateUser($record, $data);
        }

        $user = $record instanceof SshUser ? $record : SshUser::query()->findOrFail($record->getKey());
        $server = $user->sshServer;

        $user->fill($data);
        $user->save();

        return app(SshUserManagerService::class)->syncUser($user, $server);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('actAs')
                ->label('Act As')
                ->icon(Heroicon::OutlinedUserCircle)
                ->color('gray')
                ->action(function (): void {
                    $server = $this->resolveServer();
                    $record = $this->getRecord();

                    session([
                        'ssh-manager.acting_as' => [
                            'server_id' => (string) $record->getAttribute('ssh_server_id'),
                            'user_id' => (string) $record->getKey(),
                            'username' => (string) $record->getAttribute('username'),
                        ],
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Acting as ' . $record->getAttribute('username') . ' on ' . ($server?->getAttribute('name') ?? 'this server') . '.')
                        ->send();
                }),
            Action::make('editAuthorizedKeys')
                ->label('Authorized Keys')
                ->fillForm(fn (): array => [
                    'authorized_keys' => array_map(
                        static fn (string $key): array => ['key' => $key],
                        app(SshSystemUserService::class)->readAuthorizedKeys($this->getRecord()),
                    ),
                ])
                ->schema([
                    Repeater::make('authorized_keys')
                        ->hiddenLabel()
                        ->schema([
                            TextInput::make('key')
                                ->label('Public Key')
                                ->required(),
                        ])
                        ->addActionLabel('Add Key')
                        ->defaultItems(0)
                        ->reorderable(false)
                        ->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    try {
                        app(SshSystemUserService::class)->writeAuthorizedKeys(
                            $this->getRecord(),
                            array_map(
                                static fn (array $item): string => (string) ($item['key'] ?? ''),
                                $data['authorized_keys'] ?? [],
                            ),
                        );
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->danger()
                            ->title(collect($exception->errors())->flatten()->first() ?? 'Unable to update authorized keys.')
                            ->send();

                        return;
                    } catch (Throwable $exception) {
                        Notification::make()
                            ->danger()
                            ->title('Unable to update authorized keys. ' . trim($exception->getMessage()))
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->success()
                        ->title('Authorized keys updated.')
                        ->send();
                }),
            Action::make('editSshConfig')
                ->label('SSH Config')
                ->fillForm(fn (): array => [
                    'ssh_config' => app(SshSystemUserService::class)->readSshConfig($this->getRecord()),
                ])
                ->schema([
                    Textarea::make('ssh_config')
                        ->label('~/.ssh/config')
                        ->rows(16)
                        ->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    try {
                        app(SshSystemUserService::class)->writeSshConfig(
                            $this->getRecord(),
                            filled($data['ssh_config'] ?? null) ? (string) $data['ssh_config'] : null,
                        );
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->danger()
                            ->title(collect($exception->errors())->flatten()->first() ?? 'Unable to update the SSH config file.')
                            ->send();

                        return;
                    } catch (Throwable $exception) {
                        Notification::make()
                            ->danger()
                            ->title('Unable to update the SSH config file. ' . trim($exception->getMessage()))
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->success()
                        ->title('SSH config updated.')
                        ->send();
                }),
            Action::make('updatePassword')
                ->label('Update Password')
                ->schema([
                    TextInput::make('new_password')
                        ->label('New Password')
                        ->password()
                        ->revealable()
                        ->required()
                        ->minLength(8)
                        ->same('new_password_confirmation'),
                    TextInput::make('new_password_confirmation')
                        ->label('Confirm New Password')
                        ->password()
                        ->revealable()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    try {
                        app(SshSystemUserService::class)->updateUserPassword(
                            $this->getRecord(),
                            (string) $data['new_password'],
                        );
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->danger()
                            ->title(collect($exception->errors())->flatten()->first() ?? 'Unable to update the OS user password.')
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->success()
                        ->title('OS user password updated.')
                        ->send();
                }),
            DeleteAction::make()
                ->schema([
                    TextInput::make('password')
                        ->label('Confirm Root Password')
                        ->password()
                        ->revealable()
                        ->required(fn (): bool => method_exists($this->getRecord(), 'isRootUser') && $this->getRecord()->isRootUser())
                        ->visible(fn (): bool => method_exists($this->getRecord(), 'isRootUser') && $this->getRecord()->isRootUser()),
                ])
                ->action(function (DeleteAction $action, array $data): void {
                    $message = app(SshActorAuthorizer::class)->denialMessage(
                        targetServer: $this->getRecord()->sshServer,
                        targetUser: $this->getRecord(),
                    );

                    if ($message !== null) {
                        Notification::make()
                            ->danger()
                            ->title($message)
                            ->send();

                        $action->halt();

                    return;
                }

                if (method_exists($this->getRecord(), 'isRootUser') && $this->getRecord()->isRootUser()) {
                    if (! app(SshSystemUserService::class)->verifyUserPassword($this->getRecord(), (string) ($data['password'] ?? ''))) {
                        Notification::make()
                            ->danger()
                            ->title('The root user password confirmation failed.')
                            ->send();

                        $action->halt();

                        return;
                    }
                    }

                    if (! app(SshManagerResolver::class)->usesDatabase()) {
                        app(SshDirectDataService::class)->deleteUser($this->getRecord());

                        return;
                    }

                    app(SshUserManagerService::class)->deleteUser($this->getRecord(), $this->getRecord()->sshServer);
                })
                ->successRedirectUrl(SshUserResource::getUrl('index', ['ssh_server' => $this->getRecord()->sshServer])),
        ];
    }

    public function getBreadcrumbs(): array
    {
        $server = $this->resolveServer();
        $breadcrumbs = [];

        if ($server instanceof Model) {
            $breadcrumbs[SshServerResource::getUrl('edit', ['record' => $server])] = SshServerResource::getRecordTitle($server);
            $breadcrumbs[SshUserResource::getUrl('index', ['ssh_server' => $server])] = SshUserResource::getBreadcrumb();
            $breadcrumbs[SshUserResource::getUrl('edit', ['ssh_server' => $server, 'record' => $this->getRecord()])] = $this->getRecordTitle();
        }

        $breadcrumbs[] = $this->getBreadcrumb();

        return $breadcrumbs;
    }

    private function resolveServer(): ?Model
    {
        $record = $this->getRecord();
        $server = $this->getParentRecord();

        if ($server instanceof Model) {
            return $server;
        }

        $server = $record->getRelationValue('sshServer');

        if ($server instanceof Model) {
            return $server;
        }

        return filled($record->getAttribute('ssh_server_id'))
            ? SshServer::query()->find((string) $record->getAttribute('ssh_server_id'))
            : null;
    }
}
