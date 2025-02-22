<?php
# Agent.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agent extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'instance_id',
        'name',
        'custom_instructions',
        'activation_mode',
        'keywords',
        'status',
        'pause_condition',
        'has_waiting_time',
        'sync_status',
        'sync_error'
    ];

    protected $casts = [
        'status' => 'boolean',
        'keywords' => 'array',
        'has_waiting_time' => 'boolean', // Añadir esta línea
    ];

    public function shouldActivate(string $message): bool
    {
        if (!$this->status) {
            return false;
        }

        return match ($this->activation_mode) {
            'always' => true,
            'keywords' => $this->containsKeywords($message),
            default => false,
        };
    }

    private function containsKeywords(string $message): bool
    {
        return collect($this->keywords)
            ->contains(fn ($keyword) => str_contains(strtolower($message), strtolower($keyword)));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(Instance::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'agent_products')
                    ->withPivot('is_primary')
                    ->withTimestamps();
    }

    public function resources(): HasMany
    {
        return $this->hasMany(Resource::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}