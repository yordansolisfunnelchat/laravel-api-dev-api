<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentMapping extends Model
{
    protected $fillable = [
        'laravel_agent_id',
        'fastapi_agent_id',
        'user_id'
    ];

    public function agent()
    {
        return $this->belongsTo(Agent::class, 'laravel_agent_id');
    }
}