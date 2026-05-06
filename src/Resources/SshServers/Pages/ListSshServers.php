<?php

namespace ZPMPackages\FilamentSshManagement\Resources\SshServers\Pages;

use ZPMPackages\FilamentSshManagement\Resources\SshServers\SshServerResource;
use App\Support\Ssh\SshDirectDataService;
use App\Support\Ssh\SshManagerResolver;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ListSshServers extends ListRecords
{
    protected static string $resource = SshServerResource::class;

    protected function getHeaderActions(): array
    {
        if (! app(SshManagerResolver::class)->usesDatabase()) {
            return [];
        }

        return [
            CreateAction::make(),
        ];
    }

    protected function makeTable(): Table
    {
        if (app(SshManagerResolver::class)->usesDatabase()) {
            return parent::makeTable();
        }

        $table = $this->makeBaseTable()
            ->records(fn (): array => app(SshDirectDataService::class)->listServers())
            ->recordAction(fn (): ?string => null)
            ->recordUrl(fn (Model $record): string => \ZPMPackages\FilamentSshManagement\Resources\SshUsers\SshUserResource::getUrl('index', ['ssh_server' => $record]));

        static::getResource()::configureTable($table);

        return $table;
    }
}