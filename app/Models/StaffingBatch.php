<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffingBatch extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'name',
        'period_start',
        'period_end',
        'campaign_id',
        'campaign_name',
        'status',
        'rows_count',
        'active_count',
        'source_filename',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
    ];

    public function members()
    {
        return $this->hasMany(StaffingMember::class);
    }

    public function activeMembers()
    {
        return $this->hasMany(StaffingMember::class)->where('status', StaffingMember::STATUS_ACTIVE);
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
}
