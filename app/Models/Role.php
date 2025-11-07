<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Role extends Model
{
    use HasFactory;

    // Habilitamos la asignación masiva en el campo 'name'
    protected $fillable = ['name'];

    // Si tu tabla roles no usa created_at y updated_at, descomenta esta línea:
    // public $timestamps = false;

    public function users()
    {
        return $this->belongsToMany(User::class);
    }
}
