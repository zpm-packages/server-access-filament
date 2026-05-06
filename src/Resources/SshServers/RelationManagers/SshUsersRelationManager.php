<?php

namespace ZPMPackages\FilamentSshManagement\Resources\SshServers\RelationManagers;

use ZPMPackages\FilamentSshManagement\Resources\SshUsers\SshUserResource;
use ZPMPackages\FilamentSshManagement\Resources\SshUsers\Tables\SshUsersTable;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class SshUsersRelationManager extends RelationManager
{
    protected static string $relationship = 'sshUsers';

    protected static ?string $relatedResource = SshUserResource::class;

    public function table(Table $table): Table
    {
        return SshUsersTable::configure($table)
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}