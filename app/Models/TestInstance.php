<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestInstance extends Model
{
    protected $table = 'test_instances';
    
    protected $fillable = [
        'user_id',
        'name',
        'phone_number',
        'status',
        'qr_code',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function webhookEvents()
    {
        return $this->hasMany(WebhookEvent::class, 'instance_name', 'name');
    }
}