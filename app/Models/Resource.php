<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;


class Resource extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'agent_id',
        'name',
        'type',
        'url',
        'description',
        'auto_sync',
        'sync_frequency',
        'sync_status',
        'last_synced_at',
        'content',
        'file',
    ];

    protected $casts = [
        'auto_sync' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }
}