<?php

namespace App\Filament\Admin\Resources\ConfigurationResource\Pages;

use App\Filament\Admin\Resources\ConfigurationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListConfigurations extends ListRecords
{
    protected static string $resource = ConfigurationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
