<?php

namespace ZPMPackages\FilamentSshManagement\Resources\SshEntries;

use ZPMPackages\FilamentSshManagement\Resources\SshEntries\Pages\CreateSshEntry;
use ZPMPackages\FilamentSshManagement\Resources\SshEntries\Pages\EditSshEntry;
use ZPMPackages\FilamentSshManagement\Resources\SshEntries\Pages\ListSshEntries;
use ZPMPackages\FilamentSshManagement\Resources\SshEntries\Schemas\SshEntryForm;
use ZPMPackages\FilamentSshManagement\Resources\SshEntries\Tables\SshEntriesTable;
use App\Models\SshEntry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SshEntryResource extends Resource
{
    protected static ?string $model = SshEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'username';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getNavigationLabel(): string
    {
        return 'SSH Keys';
    }

    public static function getPluralLabel(): string
    {
        return 'SSH Keys';
    }

    public static function form(Schema $schema): Schema
    {
        return SshEntryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SshEntriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSshEntries::route('/'),
            'create' => CreateSshEntry::route('/create'),
            'edit' => EditSshEntry::route('/{record}/edit'),
        ];
    }
}
