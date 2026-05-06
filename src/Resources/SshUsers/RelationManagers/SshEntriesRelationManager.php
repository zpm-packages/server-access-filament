<?php

namespace ZPMPackages\FilamentSshManagement\Resources\SshUsers\RelationManagers;

use ZPMPackages\FilamentSshManagement\Resources\SshEntries\SshEntryResource;
use ZPMPackages\FilamentSshManagement\Resources\SshEntries\Schemas\SshEntryForm;
use ZPMPackages\FilamentSshManagement\Resources\SshEntries\Tables\SshEntriesTable;
use App\Models\SshEntry;
use App\Support\Ssh\SshActorAuthorizer;
use App\Support\Ssh\SshDirectDataService;
use App\Support\Ssh\SshManagerResolver;
use App\Support\Ssh\SshUserManagerService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class SshEntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'sshKeys';

    protected static ?string $relatedResource = SshEntryResource::class;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        if (! app(SshManagerResolver::class)->usesDatabase()) {
            return true;
        }

        return parent::canViewForRecord($ownerRecord, $pageClass);
    }

    public function form(Schema $schema): Schema
    {
        return SshEntryForm::configure($schema, false);
    }

    public function getDefaultActionUrl(Action $action): ?string
    {
        return null;
    }

    public function table(Table $table): Table
    {
        $usesDatabase = app(SshManagerResolver::class)->usesDatabase();

        return SshEntriesTable::configure($table, false)
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data) use ($usesDatabase): Model {
                        $message = app(SshActorAuthorizer::class)->denialMessage(targetUser: $this->getOwnerRecord());

                        if ($message !== null) {
                            Notification::make()
                                ->danger()
                                ->title($message)
                                ->send();

                            throw new Halt;
                        }

                        try {
                            if (! $usesDatabase) {
                                return app(SshDirectDataService::class)->createKey($this->getOwnerRecord(), $data);
                            }

                            if (($data['creation_mode'] ?? 'generate') === 'generate') {
                                return app(SshUserManagerService::class)->generateKey(
                                    $this->getOwnerRecord(),
                                    $this->getOwnerRecord()->sshServer,
                                    filled($data['name'] ?? null) ? (string) $data['name'] : null,
                                    filled($data['key_type'] ?? null) ? (string) $data['key_type'] : 'ed25519',
                                    filled($data['key_bits'] ?? null) ? (int) $data['key_bits'] : null,
                                    filled($data['comment'] ?? null) ? (string) $data['comment'] : null,
                                    filled($data['public_key_path'] ?? null) ? (string) $data['public_key_path'] : null,
                                    filled($data['private_key_path'] ?? null) ? (string) $data['private_key_path'] : null,
                                );
                            }

                            $record = $this->getRelationship()->create($data + ['is_managed' => false]);

                            $ownerRecord = $this->getOwnerRecord()->fresh('sshKeys');

                            app(SshUserManagerService::class)->syncUser($ownerRecord, $ownerRecord->sshServer);

                            return $record->refresh();
                        } catch (Throwable $exception) {
                            $message = $this->resolveKeyCreationFailureMessage($exception);

                            Notification::make()
                                ->danger()
                                ->title($message)
                                ->send();

                            throw new Halt;

                            return new SshEntry();
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (Model $record, array $data) use ($usesDatabase): Model {
                        if (! $usesDatabase) {
                            return app(SshDirectDataService::class)->updateKey($this->getOwnerRecord(), (string) $record->getKey(), $data);
                        }

                        $record->fill($data);
                        $record->save();

                        $ownerRecord = $this->getOwnerRecord()->fresh('sshKeys');

                        app(SshUserManagerService::class)->syncUser($ownerRecord, $ownerRecord->sshServer);

                        return $record->refresh();
                    }),
                DeleteAction::make()
                    ->action(function (Model $record) use ($usesDatabase): void {
                        $message = app(SshActorAuthorizer::class)->denialMessage(targetKey: $record);

                        if ($message !== null) {
                            Notification::make()
                                ->danger()
                                ->title($message)
                                ->send();

                            throw new Halt;
                        }

                        if (! $usesDatabase) {
                            app(SshDirectDataService::class)->deleteKey($this->getOwnerRecord(), (string) $record->getKey());
                            $this->resetTable();

                            return;
                        }

                        $record->delete();

                        $ownerRecord = $this->getOwnerRecord()->fresh('sshKeys');

                        app(SshUserManagerService::class)->syncUser($ownerRecord, $ownerRecord->sshServer);
                    }),
            ]);
    }

    protected function makeTable(): Table
    {
        if (app(SshManagerResolver::class)->usesDatabase()) {
            return parent::makeTable();
        }

        $table = $this->makeBaseTable()
            ->records(fn (): array => app(SshDirectDataService::class)->listKeys($this->getOwnerRecord()))
            ->queryStringIdentifier('sshEntriesRelationManager')
            ->recordAction(function (Model $record, Table $table): ?string {
                foreach (['edit', 'delete'] as $actionName) {
                    $action = $table->getAction($actionName);

                    if (! $action) {
                        continue;
                    }

                    $action->record($record);

                    if ($action->isHidden() || $action->getUrl()) {
                        continue;
                    }

                    return $action->getName();
                }

                return null;
            })
            ->recordUrl(fn (): ?string => null);

        return $this->table($table);
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