<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'price',
        'is_default',
        'images',
        'external_link',
        'discounts',
        'currency',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'images' => 'array',
        'discounts' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(Agent::class, 'agent_products')
                    ->withPivot('is_primary')
                    ->withTimestamps();
    }
    
    public function resources(): BelongsToMany
    {
        return $this->belongsToMany(Resource::class);
    }
}