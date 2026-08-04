<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LaboratoryExamComponent extends Model
{
    use HasFactory;

    protected $table = 'laboratory_exam_components';

    protected $fillable = [
        'exam_id',
        'component_id',
        'display_order',
        'required',
    ];

    protected $casts = [
        'required' => 'boolean',
        'display_order' => 'integer',
    ];

    public function exam()
    {
        return $this->belongsTo(LaboratoryExam::class, 'exam_id');
    }

    public function component()
    {
        return $this->belongsTo(LaboratoryComponent::class, 'component_id');
    }
}
