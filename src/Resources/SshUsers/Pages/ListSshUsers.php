<?php

namespace ZPMPackages\FilamentSshManagement\Resources\SshUsers\Pages;

use ZPMPackages\FilamentSshManagement\Resources\SshUsers\SshUserResource;
use App\Models\SshServer;
use App\Support\Ssh\DatabaseSshRepository;
use App\Support\Ssh\SshDirectDataService;
use App\Support\Ssh\SshManagerResolver;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ListSshUsers extends ListRecords
{
    protected static string $resource = SshUserResource::class;

    public function mount(): void
    {
        parent::mount();

        if (! app(SshManagerResolver::class)->usesDatabase()) {
            return;
        }

        $server = $this->getParentRecord();

        if (! $server instanceof SshServer || ! $server->is_current_system) {
            return;
        }

        $this->importSystemUsers($server);
    }

    protected function getHeaderActions(): array
    {
        $usesDatabase = app(SshManagerResolver::class)->usesDatabase();

        return [
            Action::make('scanSystemUsers')
                ->label('Scan System Users')
                ->action(function () use ($usesDatabase): void {
                    $server = $this->getParentRecord();

                    if (! $server instanceof Model) {
                        return;
                    }

                    $entries = $usesDatabase
                        ? ($server instanceof SshServer ? $this->importSystemUsers($server) : [])
                        : app(SshDirectDataService::class)->listUsers($server);
                    $count = count($entries);

                    $this->resetTable();

                    Notification::make()
                        ->title(Str::plural('System user', $count) . ' scanned')
                        ->body('Imported ' . $count . ' OS users with their SSH keys for this server.')
                        ->success()
                        ->send();
                }),
            CreateAction::make()
                ->visible(fn (): bool => true),
        ];
    }

    protected function makeTable(): Table
    {
        if (app(SshManagerResolver::class)->usesDatabase()) {
            return parent::makeTable();
        }

        $table = $this->makeBaseTable()
            ->records(fn (): array => app(SshDirectDataService::class)->listUsers($this->getParentRecord()))
            ->recordAction(function (Model $record, Table $table): ?string {
                foreach (['edit'] as $actionName) {
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
            ->recordUrl(fn (Model $record): string => SshUserResource::getUrl('edit', ['ssh_server' => $this->getParentRecord(), 'record' => $record]));

        static::getResource()::configureTable($table);

        return $table;
    }

    protected function importSystemUsers(SshServer $server): array
    {
        $repository = new DatabaseSshRepository($server);
        $entries = app(SshManagerResolver::class)->forServer($server)->scanSystemUsers();

        foreach ($entries as $entry) {
            if ($repository->find($entry->getId()) !== null) {
                $repository->update($entry);

                continue;
            }

            $repository->create($entry);
        }

        return $entries;
    }
}
