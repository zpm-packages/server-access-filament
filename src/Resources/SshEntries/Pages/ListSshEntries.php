<?php

namespace ZPMPackages\FilamentSshManagement\Resources\SshEntries\Pages;

use ZPMPackages\FilamentSshManagement\Resources\SshEntries\SshEntryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSshEntries extends ListRecords
{
    protected static string $resource = SshEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
