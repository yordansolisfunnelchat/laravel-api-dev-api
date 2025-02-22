<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappInstance extends Model
{
    protected $fillable = [
        'user_id',
        'instance_name',
        'instance_key',
        'status'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}