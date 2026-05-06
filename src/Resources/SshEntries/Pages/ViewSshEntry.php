<?php

namespace ZPMPackages\FilamentSshManagement\Resources\SshEntries\Pages;

use ZPMPackages\FilamentSshManagement\Resources\SshEntries\SshEntryResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSshEntry extends ViewRecord
{
    protected static string $resource = SshEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
