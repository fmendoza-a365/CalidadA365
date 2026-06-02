<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffingMember extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'staffing_batch_id',
        'employee_code',
        'full_name',
        'user_id',
        'supervisor_code',
        'supervisor_name',
        'supervisor_id',
        'campaign_id',
        'campaign_name',
        'quartile',
        'status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function batch()
    {
        return $this->belongsTo(StaffingBatch::class, 'staffing_batch_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }
}
