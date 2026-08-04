<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LaboratoryExam extends Model
{
    use HasFactory;

    protected $table = 'laboratory_exams';

    protected $fillable = [
        'code',
        'name',
        'category',
        'specimen_type',
        'is_panel',
        'active',
        'version',
    ];

    protected $casts = [
        'is_panel' => 'boolean',
        'active' => 'boolean',
    ];

    public function components()
    {
        return $this->belongsToMany(LaboratoryComponent::class, 'laboratory_exam_components', 'exam_id', 'component_id')
            ->withPivot('display_order', 'required')
            ->orderBy('laboratory_exam_components.display_order');
    }

    public function examComponents()
    {
        return $this->hasMany(LaboratoryExamComponent::class, 'exam_id')->orderBy('display_order');
    }
}
