<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Cambiar valor por defecto de validation_status a 'provisional'
        Schema::table('laboratory_components', function (Blueprint $table) {
            $table->string('validation_status')->default('provisional')->change();
        });

        Schema::table('laboratory_reference_ranges', function (Blueprint $table) {
            $table->string('validation_status')->default('provisional')->change();
        });

        Schema::table('laboratory_result_option_sets', function (Blueprint $table) {
            $table->string('validation_status')->default('provisional')->change();
        });

        Schema::table('laboratory_result_options', function (Blueprint $table) {
            $table->string('validation_status')->default('provisional')->change();
        });

        // 2. Agregar campos de sujeto (titular vs dependiente) a laboratory_result_values
        Schema::table('laboratory_result_values', function (Blueprint $table) {
            $table->string('subject_type')->nullable()->after('extra_data'); // 'titular' o 'dependiente'
            $table->unsignedBigInteger('subject_id')->nullable()->after('subject_type');
        });

        // 3. Actualizar registros existentes creados por el seeder de 'validated' a 'provisional'
        DB::table('laboratory_components')->where('validation_status', 'validated')->whereNull('validated_by')->update(['validation_status' => 'provisional']);
        DB::table('laboratory_reference_ranges')->where('validation_status', 'validated')->whereNull('validated_by')->update(['validation_status' => 'provisional']);
        DB::table('laboratory_result_option_sets')->where('validation_status', 'validated')->whereNull('validated_by')->update(['validation_status' => 'provisional']);
        DB::table('laboratory_result_options')->where('validation_status', 'validated')->whereNull('validated_by')->update(['validation_status' => 'provisional']);
    }

    public function down(): void
    {
        Schema::table('laboratory_result_values', function (Blueprint $table) {
            $table->dropColumn(['subject_type', 'subject_id']);
        });

        Schema::table('laboratory_options', function (Blueprint $table) {
            $table->string('validation_status')->default('pending')->change();
        });
    }
};
