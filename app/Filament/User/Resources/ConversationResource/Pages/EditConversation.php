<?php

namespace App\Filament\User\Resources\ConversationResource\Pages;

use App\Filament\User\Resources\ConversationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditConversation extends EditRecord
{
    protected static string $resource = ConversationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
