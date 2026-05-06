<?php

namespace ZPMPackages\FilamentSshManagement\Resources\SshUsers;

use ZPMPackages\FilamentSshManagement\Resources\SshServers\SshServerResource;
use ZPMPackages\FilamentSshManagement\Resources\SshUsers\Pages\CreateSshUser;
use ZPMPackages\FilamentSshManagement\Resources\SshUsers\Pages\EditSshUser;
use ZPMPackages\FilamentSshManagement\Resources\SshUsers\Pages\ListSshUsers;
use ZPMPackages\FilamentSshManagement\Resources\SshUsers\RelationManagers\SshEntriesRelationManager;
use ZPMPackages\FilamentSshManagement\Resources\SshUsers\Schemas\SshUserForm;
use ZPMPackages\FilamentSshManagement\Resources\SshUsers\Tables\SshUsersTable;
use App\Models\SshUser;
use App\Support\Ssh\SshDirectDataService;
use App\Support\Ssh\SshManagerResolver;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Closure;
use Illuminate\Database\Eloquent\Model;

class SshUserResource extends Resource
{
    protected static ?string $model = SshUser::class;

    protected static ?string $parentResource = SshServerResource::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $recordTitleAttribute = 'username';

    public static function getNavigationLabel(): string
    {
        return 'Users';
    }

    public static function getPluralLabel(): string
    {
        return 'Users';
    }

    public static function resolveRecordRouteBinding(int | string $key, ?Closure $modifyQuery = null): ?Model
    {
        if (! app(SshManagerResolver::class)->usesDatabase()) {
            return app(SshDirectDataService::class)->findUser(
                request()->route('ssh_server'),
                (string) $key,
            );
        }

        return parent::resolveRecordRouteBinding($key, $modifyQuery);
    }

    public static function form(Schema $schema): Schema
    {
        return SshUserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SshUsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            SshEntriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSshUsers::route('/'),
            'create' => CreateSshUser::route('/create'),
            'edit' => EditSshUser::route('/{record}/edit'),
        ];
    }
}
