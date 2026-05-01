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
        Schema::dropIfExists('face_profiles');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('face_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('descriptor'); // vector 128-float
            $table->json('descriptors')->nullable();
            $table->float('threshold')->default(0.42);
            $table->unsignedTinyInteger('failed_attempts')->default(0);
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamp('last_enrolled_at')->nullable();
            $table->string('last_enroll_ip', 45)->nullable();
            $table->string('last_enroll_user_agent', 255)->nullable();
            $table->timestamps();
        });
    }
};
