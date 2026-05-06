<?php

namespace ZPMPackages\FilamentSshManagement\Resources\SshServers\Tables;

use ZPMPackages\FilamentSshManagement\Resources\SshUsers\SshUserResource;
use App\Support\Ssh\SshManagerResolver;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class SshServersTable
{
    public static function configure(Table $table): Table
    {
        $usesDatabase = app(SshManagerResolver::class)->usesDatabase();

        $recordActions = [
            Action::make('users')
                ->label('Users')
                ->url(fn (Model $record): string => SshUserResource::getUrl('index', ['ssh_server' => $record])),
        ];

        if ($usesDatabase) {
            $recordActions[] = EditAction::make();
        }

        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('operating_system')
                    ->badge(),
                IconColumn::make('is_current_system')
                    ->label('Current')
                    ->boolean(),
                TextColumn::make('host')
                    ->placeholder('local system')
                    ->toggleable(),
                TextColumn::make('manager_username')
                    ->label('Manager User')
                    ->toggleable(),
                TextColumn::make('ssh_users_count')
                    ->when(
                        $usesDatabase,
                        fn (TextColumn $column): TextColumn => $column->counts('sshUsers'),
                        fn (TextColumn $column): TextColumn => $column->state(fn (Model $record): int => (int) ($record->getAttribute('ssh_users_count') ?? 0)),
                    )
                    ->label('Users'),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions($recordActions);
    }
}