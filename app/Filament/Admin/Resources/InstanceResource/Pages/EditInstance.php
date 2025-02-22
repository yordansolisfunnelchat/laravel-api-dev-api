<?php

namespace App\Filament\Admin\Resources\InstanceResource\Pages;

use App\Filament\Admin\Resources\InstanceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInstance extends EditRecord
{
    protected static string $resource = InstanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //Actions\DeleteAction::make(),
        ];
    }
}
