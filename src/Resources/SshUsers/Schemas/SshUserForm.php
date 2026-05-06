<?php

namespace ZPMPackages\FilamentSshManagement\Resources\SshUsers\Schemas;

use App\Support\Ssh\SshManagerResolver;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SshUserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('OS User')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('username')
                                    ->required()
                                    ->maxLength(255)
                                    ->when(
                                        app(SshManagerResolver::class)->usesDatabase(),
                                        fn (TextInput $component): TextInput => $component->unique(ignoreRecord: true),
                                    ),
                                TextInput::make('name')
                                    ->maxLength(255),
                                TextInput::make('password')
                                    ->label('Password')
                                    ->password()
                                    ->revealable()
                                    ->confirmed()
                                    ->dehydrated(fn (?string $state): bool => filled($state))
                                    ->visible(fn (string $operation): bool => $operation === 'create'),
                                TextInput::make('password_confirmation')
                                    ->label('Confirm Password')
                                    ->password()
                                    ->revealable()
                                    ->dehydrated(false)
                                    ->visible(fn (string $operation): bool => $operation === 'create'),
                                TextInput::make('home_directory')
                                    ->helperText('Used for directory-scoped access checks and OS account sync.')
                                    ->maxLength(255),
                                TagsInput::make('groups')
                                    ->separator(','),
                                Toggle::make('is_root')
                                    ->label('Root user')
                                    ->helperText('Root users bypass directory scoping in secured mode.'),
                                Textarea::make('comment')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),
                    ]),
                Section::make('Manage Access')
                    ->description('These permissions define which OS users and SSH keys this acted OS user may manage when secured mode is enabled.')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Toggle::make('can_read_entries')
                                    ->label('Can read users and keys')
                                    ->default(true),
                                Toggle::make('can_write_entries')
                                    ->label('Can write users and keys')
                                    ->default(false),
                                Toggle::make('can_manage_entries')
                                    ->label('Can manage users and keys')
                                    ->default(false),
                                TagsInput::make('managed_directories')
                                    ->separator(',')
                                    ->helperText('Leave empty for unrestricted access. Otherwise management is limited to matching home-directory prefixes.')
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
