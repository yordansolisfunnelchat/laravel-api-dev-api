<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Events\ConversationPausedEvent;
use Illuminate\Support\Facades\Log;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'agent_id',
        'customer_phone',
        'status',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    protected static function booted()
    {
        static::updated(function ($conversation) {
            if ($conversation->status === 'paused' && $conversation->wasChanged('status')) {
                \Log::info('Conversation status changed to paused for conversation ID: ' . $conversation->id);
                event(new ConversationPausedEvent($conversation));
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}