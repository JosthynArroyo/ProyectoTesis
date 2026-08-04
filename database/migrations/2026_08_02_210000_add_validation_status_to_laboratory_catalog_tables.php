<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('laboratory_components', function (Blueprint $table) {
            $table->string('validation_status')->default('pending')->index()->after('active');
            $table->string('source_note')->nullable()->after('validation_status');
            $table->timestamp('validated_at')->nullable()->after('source_note');
            $table->foreignId('validated_by')->nullable()->after('validated_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('laboratory_reference_ranges', function (Blueprint $table) {
            $table->string('validation_status')->default('pending')->index()->after('reference_text');
            $table->string('source_note')->nullable()->after('validation_status');
            $table->timestamp('validated_at')->nullable()->after('source_note');
            $table->foreignId('validated_by')->nullable()->after('validated_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('laboratory_result_option_sets', function (Blueprint $table) {
            $table->string('validation_status')->default('pending')->index()->after('name');
            $table->string('source_note')->nullable()->after('validation_status');
            $table->timestamp('validated_at')->nullable()->after('source_note');
            $table->foreignId('validated_by')->nullable()->after('validated_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('laboratory_result_options', function (Blueprint $table) {
            $table->string('validation_status')->default('pending')->index()->after('display_order');
            $table->string('source_note')->nullable()->after('validation_status');
            $table->timestamp('validated_at')->nullable()->after('source_note');
            $table->foreignId('validated_by')->nullable()->after('validated_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('laboratory_result_options', function (Blueprint $table) {
            $table->dropForeign(['validated_by']);
            $table->dropColumn(['validation_status', 'source_note', 'validated_at', 'validated_by']);
        });

        Schema::table('laboratory_result_option_sets', function (Blueprint $table) {
            $table->dropForeign(['validated_by']);
            $table->dropColumn(['validation_status', 'source_note', 'validated_at', 'validated_by']);
        });

        Schema::table('laboratory_reference_ranges', function (Blueprint $table) {
            $table->dropForeign(['validated_by']);
            $table->dropColumn(['validation_status', 'source_note', 'validated_at', 'validated_by']);
        });

        Schema::table('laboratory_components', function (Blueprint $table) {
            $table->dropForeign(['validated_by']);
            $table->dropColumn(['validation_status', 'source_note', 'validated_at', 'validated_by']);
        });
    }
};
