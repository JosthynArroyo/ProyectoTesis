<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LabOrderItem extends Model
{
    use HasFactory;

    protected $table = 'lab_order_items';

    protected $fillable = [
        'lab_order_id',
        'lab_test_id',
        'preparacion_snapshot',
        'indicaciones_snapshot',
        'resultado_url',
    ];

    public function order()
    {
        return $this->belongsTo(LabOrder::class, 'lab_order_id');
    }

    public function test()
    {
        return $this->belongsTo(LabTest::class, 'lab_test_id');
    }
}
