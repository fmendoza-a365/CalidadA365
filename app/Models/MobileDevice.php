<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobileDevice extends Model
{
    protected $fillable = [
        'user_id',
        'fcm_token',
        'device_id',
        'platform',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
