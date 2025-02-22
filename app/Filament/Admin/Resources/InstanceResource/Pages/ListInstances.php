<?php

namespace App\Filament\Admin\Resources\InstanceResource\Pages;

use App\Filament\Admin\Resources\InstanceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInstances extends ListRecords
{
    protected static string $resource = InstanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
