<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lab_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lab_order_id');
            $table->unsignedBigInteger('lab_test_id');
            $table->text('preparacion_snapshot')->nullable();
            $table->string('resultado_url')->nullable();
            $table->timestamps();

            $table->foreign('lab_order_id')->references('id')->on('lab_orders')->onDelete('cascade');
            $table->foreign('lab_test_id')->references('id')->on('lab_tests')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_order_items');
    }
};
