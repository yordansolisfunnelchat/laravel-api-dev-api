<?php

namespace App\Filament\User\Resources\ResourceResource\Pages;

use App\Filament\User\Resources\ResourceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListResources extends ListRecords
{
    protected static string $resource = ResourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
