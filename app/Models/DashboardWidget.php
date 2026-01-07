<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DashboardWidget extends Model
{
    protected $fillable = [
        'user_id',
        'widget_type', // stats_card, line_chart, bar_chart, pie_chart, table
        'title',
        'config', // JSON field for specific settings
        'width', // sm, md, lg, full
        'sort_order',
    ];

    protected $casts = [
        'config' => 'array',
        'sort_order' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
