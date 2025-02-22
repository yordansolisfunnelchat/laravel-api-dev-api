<?php

namespace App\Filament\User\Resources\ConversationResource\Pages;

use App\Filament\User\Resources\ConversationResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateConversation extends CreateRecord
{
    protected static string $resource = ConversationResource::class;
}
