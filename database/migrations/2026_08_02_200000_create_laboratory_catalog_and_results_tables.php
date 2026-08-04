<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laboratory_exams', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('category')->index();
            $table->string('specimen_type')->nullable();
            $table->boolean('is_panel')->default(false);
            $table->boolean('active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        Schema::create('laboratory_result_option_sets', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('laboratory_result_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('option_set_id')->constrained('laboratory_result_option_sets')->cascadeOnDelete();
            $table->string('code');
            $table->string('label');
            $table->string('default_classification')->default('normal');
            $table->integer('display_order')->default(0);
            $table->timestamps();
        });

        Schema::create('laboratory_components', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('result_type')->default('numeric'); // numeric, coded, text, titer, blood_group, microscopy, culture, calculated, panel
            $table->string('default_unit')->nullable();
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->foreignId('option_set_id')->nullable()->constrained('laboratory_result_option_sets')->nullOnDelete();
            $table->string('default_method')->nullable();
            $table->string('formula')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('laboratory_exam_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('laboratory_exams')->cascadeOnDelete();
            $table->foreignId('component_id')->constrained('laboratory_components')->cascadeOnDelete();
            $table->integer('display_order')->default(0);
            $table->boolean('required')->default(true);
            $table->timestamps();
        });

        Schema::create('laboratory_reference_ranges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('component_id')->constrained('laboratory_components')->cascadeOnDelete();
            $table->string('method')->nullable();
            $table->string('sex')->default('both'); // male, female, both
            $table->integer('minimum_age_days')->default(0);
            $table->integer('maximum_age_days')->default(36500); // 100 years
            $table->string('pregnancy_stage')->nullable();
            $table->decimal('lower_limit', 12, 4)->nullable();
            $table->decimal('upper_limit', 12, 4)->nullable();
            $table->decimal('critical_lower_limit', 12, 4)->nullable();
            $table->decimal('critical_upper_limit', 12, 4)->nullable();
            $table->string('reference_text')->nullable();
            $table->dateTime('valid_from')->nullable();
            $table->dateTime('valid_until')->nullable();
            $table->timestamps();
        });

        Schema::create('laboratory_result_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('result_id')->constrained('pedido_laboratorio_resultados')->cascadeOnDelete();
            $table->unsignedBigInteger('order_item_id')->nullable()->index();
            $table->foreignId('component_id')->constrained('laboratory_components')->cascadeOnDelete();
            $table->decimal('value_numeric', 12, 4)->nullable();
            $table->string('comparator', 10)->nullable(); // <, >, <=, >=
            $table->string('value_code')->nullable();
            $table->text('value_text')->nullable();
            $table->string('titer_denominator')->nullable();
            $table->string('unit_snapshot')->nullable();
            $table->string('method_snapshot')->nullable();
            $table->text('reference_snapshot')->nullable();
            $table->string('classification')->default('normal');
            $table->text('observation')->nullable();
            $table->boolean('is_calculated')->default(false);
            $table->json('extra_data')->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laboratory_result_values');
        Schema::dropIfExists('laboratory_reference_ranges');
        Schema::dropIfExists('laboratory_exam_components');
        Schema::dropIfExists('laboratory_components');
        Schema::dropIfExists('laboratory_result_options');
        Schema::dropIfExists('laboratory_result_option_sets');
        Schema::dropIfExists('laboratory_exams');
    }
};
