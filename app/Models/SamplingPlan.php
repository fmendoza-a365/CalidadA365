<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SamplingPlan extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'name',
        'week_start',
        'week_end',
        'business_days',
        'start_hour',
        'end_hour',
        'campaign_id',
        'staffing_batch_id',
        'campaign_filter',
        'seed',
        'quotas',
        'unique_day',
        'rotate_methods',
        'staff_count',
        'orders_count',
        'status',
        'staff_csv',
        'created_by',
    ];

    protected $casts = [
        'week_start' => 'date',
        'week_end' => 'date',
        'quotas' => 'array',
        'unique_day' => 'boolean',
        'rotate_methods' => 'boolean',
    ];

    public function orders()
    {
        return $this->hasMany(SamplingOrder::class);
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function staffingBatch()
    {
        return $this->belongsTo(StaffingBatch::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
