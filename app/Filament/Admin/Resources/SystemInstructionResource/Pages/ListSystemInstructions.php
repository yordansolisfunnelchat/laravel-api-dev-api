<?php

namespace App\Filament\Admin\Resources\SystemInstructionResource\Pages;

use App\Filament\Admin\Resources\SystemInstructionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSystemInstructions extends ListRecords
{
    protected static string $resource = SystemInstructionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
