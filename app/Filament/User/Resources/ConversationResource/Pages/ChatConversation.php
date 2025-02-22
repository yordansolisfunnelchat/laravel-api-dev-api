<?php

namespace App\Filament\User\Resources\ConversationResource\Pages;

use App\Filament\User\Resources\ConversationResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use App\Models\Conversation;

class ChatConversation extends ViewRecord
{
    protected static string $resource = ConversationResource::class;

    protected static string $view = 'filament.chat';

    public function getRecord(): Conversation
    {
        return Conversation::with('messages')->findOrFail(parent::getRecord()->id);
    }

    protected function getViewData(): array
    {
        return [
            'conversation' => $this->getRecord(),
        ];
    }
}