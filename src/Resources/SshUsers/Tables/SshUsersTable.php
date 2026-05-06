<?php

namespace ZPMPackages\FilamentSshManagement\Resources\SshUsers\Tables;

use App\Support\Ssh\SshManagerResolver;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class SshUsersTable
{
    public static function configure(Table $table): Table
    {
        $usesDatabase = app(SshManagerResolver::class)->usesDatabase();

        return $table
            ->columns([
                TextColumn::make('username')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('home_directory')
                    ->searchable()
                    ->toggleable(),
                IconColumn::make('is_root')
                    ->label('Root')
                    ->boolean(),
                IconColumn::make('can_manage_entries')
                    ->label('Can Manage')
                    ->boolean(),
                TextColumn::make('ssh_keys_count')
                    ->when(
                        $usesDatabase,
                        fn (TextColumn $column): TextColumn => $column->counts('sshKeys'),
                        fn (TextColumn $column): TextColumn => $column->state(fn (Model $record): int => (int) ($record->getAttribute('ssh_keys_count') ?? 0)),
                    )
                    ->label('SSH Keys'),
                TextColumn::make('managed_directories')
                    ->label('Manage Dirs')
                    ->badge()
                    ->separator(',')
                    ->state(fn (Model $record): array => $record->getAttribute('managed_directories') ?? [])
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
