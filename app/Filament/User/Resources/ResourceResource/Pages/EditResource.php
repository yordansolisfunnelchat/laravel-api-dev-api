<?php

namespace App\Filament\User\Resources\ResourceResource\Pages;

use App\Filament\User\Resources\ResourceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditResource extends EditRecord
{
    protected static string $resource = ResourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
