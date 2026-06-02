<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SamplingOrderAuditEvent extends Model
{
    protected $fillable = [
        'sampling_order_id',
        'actor_id',
        'event',
        'from_status',
        'to_status',
        'metadata',
        'occurred_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(SamplingOrder::class, 'sampling_order_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
