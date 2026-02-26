<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QualityAttribute extends Model
{
    protected $fillable = ['form_version_id', 'name', 'weight', 'concept', 'guidelines', 'sort_order'];
    
    protected $casts = ['weight' => 'decimal:2'];

    public function formVersion()
    {
        return $this->belongsTo(QualityFormVersion::class, 'form_version_id');
    }

    public function subAttributes()
    {
        return $this->hasMany(QualitySubAttribute::class, 'attribute_id')->orderBy('sort_order');
    }
}
