<?php

namespace App\Filament\Admin\Resources\SystemInstructionResource\Pages;

use App\Filament\Admin\Resources\SystemInstructionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSystemInstruction extends EditRecord
{
    protected static string $resource = SystemInstructionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
