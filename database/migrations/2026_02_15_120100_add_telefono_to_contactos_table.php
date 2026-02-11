<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contactos', function (Blueprint $table) {
            if (!Schema::hasColumn('contactos', 'telefono')) {
                $table->string('telefono', 20)->nullable()->after('email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contactos', function (Blueprint $table) {
            if (Schema::hasColumn('contactos', 'telefono')) {
                $table->dropColumn('telefono');
            }
        });
    }
};
