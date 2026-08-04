<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('database_backups', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type', 20)->index(); // manual, daily, weekly, monthly
            $table->string('status', 20)->default('pending')->index(); // pending, processing, completed, failed, verified
            $table->string('disk', 50)->default('r2_backups');
            $table->string('file_path', 500)->nullable();
            $table->string('manifest_path', 500)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('sha256', 64)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->decimal('duration_seconds', 8, 2)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();

            $table->index(['created_at', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('database_backups');
    }
};
