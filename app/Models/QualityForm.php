<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QualityForm extends Model
{
    protected $fillable = ['campaign_id', 'name', 'description', 'created_by'];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function versions()
    {
        return $this->hasMany(QualityFormVersion::class);
    }

    public function latestVersion()
    {
        return $this->hasOne(QualityFormVersion::class)->latestOfMany();
    }
}
