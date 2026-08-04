<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LaboratoryResultOption extends Model
{
    use HasFactory;

    protected $table = 'laboratory_result_options';

    protected $fillable = [
        'option_set_id',
        'code',
        'label',
        'default_classification',
        'display_order',
    ];

    public function optionSet()
    {
        return $this->belongsTo(LaboratoryResultOptionSet::class, 'option_set_id');
    }
}
