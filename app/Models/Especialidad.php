<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Especialidad extends Model
{
    use HasFactory;

    protected $table = 'especialidades';

    protected $fillable = ['nombre', 'descripcion'];

    public function doctores()
    {
        return $this->belongsToMany(User::class, 'doctor_especialidad', 'especialidad_id', 'user_id')
            ->withTimestamps();
    }
}
