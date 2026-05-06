<?php

namespace ZPMPackages\FilamentSshManagement\Resources\SshEntries\Schemas;

use App\Models\SshUser;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class SshEntryForm
{
    public static function configure(Schema $schema, bool $includeUserSelect = true): Schema
    {
        $components = [];

        if ($includeUserSelect) {
            $components[] = Select::make('ssh_user_id')
                ->label('OS User')
                ->relationship('sshUser', 'username')
                ->searchable()
                ->preload()
                ->required();
        }

        return $schema
            ->components([
                Section::make('SSH Key')
                    ->schema([
                        Grid::make(2)
                            ->schema(array_merge($components, [
                                Select::make('creation_mode')
                                    ->label('Creation Mode')
                                    ->options([
                                        'generate' => 'Generate key pair',
                                        'manual' => 'Paste public key',
                                    ])
                                    ->default('generate')
                                    ->live()
                                    ->dehydrated(false)
                                    ->visible(fn (string $operation): bool => $operation === 'create')
                                    ->columnSpanFull(),
                                TextInput::make('name')
                                    ->maxLength(255),
                                Select::make('key_type')
                                    ->options([
                                        'ed25519' => 'ed25519',
                                        'rsa' => 'rsa',
                                        'ecdsa' => 'ecdsa',
                                    ])
                                    ->default('ed25519')
                                    ->required(),
                                TextInput::make('key_bits')
                                    ->numeric()
                                    ->minValue(256)
                                    ->visible(fn (Get $get): bool => $get('key_type') !== 'ed25519'),
                                TextInput::make('public_key_path')
                                    ->label('Public Key Path')
                                    ->prefix(fn (Get $get, mixed $livewire): ?string => self::resolveSshDirectoryPrefix($get, $livewire))
                                    ->visible(fn (Get $get, string $operation): bool => $operation === 'create' && $get('creation_mode') === 'generate'),
                                TextInput::make('private_key_path')
                                    ->label('Private Key Path')
                                    ->prefix(fn (Get $get, mixed $livewire): ?string => self::resolveSshDirectoryPrefix($get, $livewire))
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state, ?string $old): void {
                                        $currentPublicKeyPath = $get('public_key_path');

                                        if (! self::shouldAutofillPublicKeyPath($currentPublicKeyPath, $old)) {
                                            return;
                                        }

                                        $set('public_key_path', self::derivePublicKeyPath($state));
                                    })
                                    ->visible(fn (Get $get, string $operation): bool => $operation === 'create' && $get('creation_mode') === 'generate'),
                                Textarea::make('comment')
                                    ->rows(3)
                                    ->columnSpanFull(),
                                Textarea::make('public_key')
                                    ->rows(6)
                                    ->required(fn (Get $get, string $operation): bool => $operation !== 'create' || $get('creation_mode') === 'manual')
                                    ->visible(fn (Get $get, string $operation): bool => $operation !== 'create' || $get('creation_mode') === 'manual')
                                    ->helperText('Store one public key per child record. The parent OS user sync collects these into authorized_keys.')
                                    ->columnSpanFull(),
                            ]))->columnSpanFull(),
                    ])->columnSpanFull(),
            ]);
    }

    private static function derivePublicKeyPath(?string $privateKeyPath): ?string
    {
        $normalizedPath = filled($privateKeyPath) ? trim((string) $privateKeyPath) : null;

        if (blank($normalizedPath)) {
            return null;
        }

        return str_ends_with($normalizedPath, '.pub')
            ? $normalizedPath
            : $normalizedPath . '.pub';
    }

    private static function shouldAutofillPublicKeyPath(mixed $currentPublicKeyPath, ?string $oldPrivateKeyPath): bool
    {
        $currentPublicKeyPath = filled($currentPublicKeyPath) ? trim((string) $currentPublicKeyPath) : null;

        if (blank($currentPublicKeyPath)) {
            return true;
        }

        return $currentPublicKeyPath === self::derivePublicKeyPath($oldPrivateKeyPath);
    }

    private static function resolveSshDirectoryPrefix(Get $get, mixed $livewire): ?string
    {
        $homeDirectory = null;

        if (is_object($livewire) && method_exists($livewire, 'getOwnerRecord')) {
            $ownerRecord = $livewire->getOwnerRecord();
            $homeDirectory = $ownerRecord?->getAttribute('home_directory');
        }

        if (blank($homeDirectory) && filled($get('ssh_user_id'))) {
            $homeDirectory = SshUser::query()
                ->whereKey((string) $get('ssh_user_id'))
                ->value('home_directory');
        }

        if (blank($homeDirectory)) {
            return null;
        }

        $separator = str_contains((string) $homeDirectory, '\\') ? '\\' : '/';

        return rtrim((string) $homeDirectory, '\\/') . $separator . '.ssh' . $separator;
    }
}
