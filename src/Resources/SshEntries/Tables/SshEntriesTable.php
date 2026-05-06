<?php

namespace ZPMPackages\FilamentSshManagement\Resources\SshEntries\Tables;

use App\Models\SshEntry;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SshEntriesTable
{
    public static function configure(Table $table, bool $includeUserColumn = true): Table
    {
        $columns = [];

        if ($includeUserColumn) {
            $columns[] = TextColumn::make('sshUser.username')
                ->label('OS User')
                ->searchable()
                ->sortable();
        }

        $columns = array_merge($columns, [
            TextColumn::make('name')
                ->searchable()
                ->toggleable(),
            TextColumn::make('key_type')
                ->badge()
                ->toggleable(),
            TextColumn::make('is_managed')
                ->label('Managed')
                ->badge()
                ->formatStateUsing(fn (bool $state): string => $state ? 'managed' : 'authorized')
                ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
            TextColumn::make('public_key')
                ->label('Public Key')
                ->limit(60)
                ->wrap(),
            TextColumn::make('public_key_path')
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('updated_at')
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ]);

        return $table
            ->columns($columns)
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
