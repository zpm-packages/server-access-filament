<?php

namespace ZPMPackages\FilamentSshManagement\Resources\SshServers\Schemas;

use App\Support\Ssh\SshSystemUserService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class SshServerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Server')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                Select::make('operating_system')
                                    ->options([
                                        'windows' => 'Windows',
                                        'linux' => 'Linux',
                                        'macos' => 'macOS',
                                        'android' => 'Android',
                                    ])
                                    ->required(),
                                Toggle::make('is_current_system')
                                    ->label('Current system')
                                    ->live(),
                                TextInput::make('host')
                                    ->label('Host / IP')
                                    ->maxLength(255)
                                    ->visible(fn (Get $get): bool => ! (bool) $get('is_current_system')),
                                TextInput::make('port')
                                    ->numeric()
                                    ->default(22)
                                    ->visible(fn (Get $get): bool => ! (bool) $get('is_current_system')),
                                TextInput::make('manager_username')
                                    ->label('Manager User')
                                    ->maxLength(255)
                                    ->default(fn (): ?string => app(SshSystemUserService::class)->defaultCurrentSystemManagerUsername())
                                    ->datalist(fn (Get $get): array => (bool) $get('is_current_system')
                                        ? app(SshSystemUserService::class)->currentSystemManagerCandidates()
                                        : [])
                                    ->helperText(fn (Get $get): string => (bool) $get('is_current_system')
                                        ? 'Use the Change Manager User action to verify and switch the current-system manager user.'
                                        : 'Username used to connect to the remote server.'),
                            ]),
                    ]),
            ]);
    }
}