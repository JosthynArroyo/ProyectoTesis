<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PatientFlag extends Model
{
    use HasFactory;

    protected $table = 'patient_flags';

    protected $fillable = [
        'user_id',
        'adulto_mayor',
        'embarazo',
        'discapacidad',
        'cronico',
    ];

    protected $casts = [
        'adulto_mayor' => 'boolean',
        'embarazo' => 'boolean',
        'discapacidad' => 'boolean',
        'cronico' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
