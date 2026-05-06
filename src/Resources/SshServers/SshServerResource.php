<?php

namespace ZPMPackages\FilamentSshManagement\Resources\SshServers;

use ZPMPackages\FilamentSshManagement\Resources\SshServers\Pages\CreateSshServer;
use ZPMPackages\FilamentSshManagement\Resources\SshServers\Pages\EditSshServer;
use ZPMPackages\FilamentSshManagement\Resources\SshServers\Pages\ListSshServers;
use ZPMPackages\FilamentSshManagement\Resources\SshServers\RelationManagers\SshUsersRelationManager;
use ZPMPackages\FilamentSshManagement\Resources\SshServers\Schemas\SshServerForm;
use ZPMPackages\FilamentSshManagement\Resources\SshServers\Tables\SshServersTable;
use App\Models\SshServer;
use App\Support\Ssh\SshDirectDataService;
use App\Support\Ssh\SshManagerResolver;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Closure;
use Illuminate\Database\Eloquent\Model;

class SshServerResource extends Resource
{
    protected static ?string $model = SshServer::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return 'SSH Servers';
    }

    public static function getPluralLabel(): string
    {
        return 'SSH Servers';
    }

    public static function canCreate(): bool
    {
        return app(SshManagerResolver::class)->usesDatabase();
    }

    public static function canEdit(Model $record): bool
    {
        return app(SshManagerResolver::class)->usesDatabase();
    }

    public static function resolveRecordRouteBinding(int | string $key, ?Closure $modifyQuery = null): ?Model
    {
        if (! app(SshManagerResolver::class)->usesDatabase()) {
            return app(SshDirectDataService::class)->findServer((string) $key);
        }

        return parent::resolveRecordRouteBinding($key, $modifyQuery);
    }

    public static function form(Schema $schema): Schema
    {
        return SshServerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SshServersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            'ssh-users' => SshUsersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSshServers::route('/'),
            'create' => CreateSshServer::route('/create'),
            'edit' => EditSshServer::route('/{record}/edit'),
        ];
    }
}