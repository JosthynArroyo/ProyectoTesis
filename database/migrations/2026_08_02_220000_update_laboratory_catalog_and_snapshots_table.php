<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('laboratory_components', function (Blueprint $table) {
            $table->json('authorized_methods')->nullable()->after('default_method');
        });

        Schema::table('laboratory_reference_ranges', function (Blueprint $table) {
            $table->string('reference_type')->default('interval')->after('component_id');
        });

        Schema::table('laboratory_result_values', function (Blueprint $table) {
            $table->string('reference_type_snapshot')->nullable()->after('reference_snapshot');
            $table->string('patient_sex_snapshot', 20)->nullable()->after('extra_data');
            $table->date('patient_birth_date_snapshot')->nullable()->after('patient_sex_snapshot');
            $table->integer('patient_age_days_snapshot')->nullable()->after('patient_birth_date_snapshot');
            $table->dateTime('age_calculation_date_snapshot')->nullable()->after('patient_age_days_snapshot');
            $table->foreignId('reference_rule_id')->nullable()->after('age_calculation_date_snapshot')->constrained('laboratory_reference_ranges')->nullOnDelete();
            $table->boolean('reference_was_overridden')->default(false)->after('reference_rule_id');
            $table->boolean('method_was_overridden')->default(false)->after('reference_was_overridden');
            $table->boolean('unit_was_overridden')->default(false)->after('method_was_overridden');
            $table->text('override_reason')->nullable()->after('unit_was_overridden');
        });
    }

    public function down(): void
    {
        Schema::table('laboratory_result_values', function (Blueprint $table) {
            $table->dropForeign(['reference_rule_id']);
            $table->dropColumn([
                'reference_type_snapshot',
                'patient_sex_snapshot',
                'patient_birth_date_snapshot',
                'patient_age_days_snapshot',
                'age_calculation_date_snapshot',
                'reference_rule_id',
                'reference_was_overridden',
                'method_was_overridden',
                'unit_was_overridden',
                'override_reason',
            ]);
        });

        Schema::table('laboratory_reference_ranges', function (Blueprint $table) {
            $table->dropColumn('reference_type');
        });

        Schema::table('laboratory_components', function (Blueprint $table) {
            $table->dropColumn('authorized_methods');
        });
    }
};
