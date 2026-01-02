<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DashboardWidget extends Model
{
    protected $fillable = [
        'user_id',
        'widget_type',
        'title',
        'config',
        'position_x',
        'position_y',
        'width',
        'height',
        'order',
    ];

    protected $casts = [
        'config' => 'array',
        'position_x' => 'integer',
        'position_y' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'order' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
