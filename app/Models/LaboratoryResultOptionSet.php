<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LaboratoryResultOptionSet extends Model
{
    use HasFactory;

    protected $table = 'laboratory_result_option_sets';

    protected $fillable = [
        'code',
        'name',
    ];

    public function options()
    {
        return $this->hasMany(LaboratoryResultOption::class, 'option_set_id')->orderBy('display_order');
    }
}
