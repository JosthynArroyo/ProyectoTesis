<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('allergies_status', 20)->default('unknown');
            $table->text('clinical_summary')->nullable();
            $table->timestamp('last_reviewed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('clinical_record_allergies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinical_record_id')->constrained('clinical_records')->cascadeOnDelete();
            $table->string('allergen');
            $table->text('reaction')->nullable();
            $table->string('severity', 20)->default('unknown');
            $table->string('status', 20)->default('active');
            $table->date('noted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['clinical_record_id', 'status']);
        });

        Schema::create('clinical_record_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinical_record_id')->constrained('clinical_records')->cascadeOnDelete();
            $table->string('category', 30);
            $table->string('title');
            $table->string('relation_label')->nullable();
            $table->text('description')->nullable();
            $table->date('occurred_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['clinical_record_id', 'category']);
        });

        Schema::create('clinical_record_problems', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinical_record_id')->constrained('clinical_records')->cascadeOnDelete();
            $table->foreignId('source_nota_soap_id')->nullable()->constrained('notas_soap')->nullOnDelete();
            $table->string('name');
            $table->string('cie10', 20)->nullable();
            $table->string('status', 20)->default('active');
            $table->boolean('is_chronic')->default(false);
            $table->date('started_at')->nullable();
            $table->date('resolved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['clinical_record_id', 'status']);
        });

        Schema::create('clinical_record_medications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinical_record_id')->constrained('clinical_records')->cascadeOnDelete();
            $table->foreignId('source_receta_id')->nullable()->constrained('recetas')->nullOnDelete();
            $table->foreignId('prescribed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('presentation')->nullable();
            $table->string('dosage')->nullable();
            $table->string('frequency')->nullable();
            $table->string('route')->nullable();
            $table->text('instructions')->nullable();
            $table->string('status', 20)->default('active');
            $table->date('started_at')->nullable();
            $table->date('ended_at')->nullable();
            $table->timestamps();

            $table->index(['clinical_record_id', 'status']);
        });

        Schema::create('clinical_record_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinical_record_id')->constrained('clinical_records')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 30)->default('clinical');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('severity', 20)->default('warning');
            $table->boolean('is_active')->default(true);
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->timestamps();

            $table->index(['clinical_record_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_record_alerts');
        Schema::dropIfExists('clinical_record_medications');
        Schema::dropIfExists('clinical_record_problems');
        Schema::dropIfExists('clinical_record_histories');
        Schema::dropIfExists('clinical_record_allergies');
        Schema::dropIfExists('clinical_records');
    }
};
