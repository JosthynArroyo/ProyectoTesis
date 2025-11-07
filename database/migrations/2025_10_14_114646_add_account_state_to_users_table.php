<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $t) {
            if (!Schema::hasColumn('users','status'))           $t->string('status')->default('active')->index(); // active|blocked|inactive
            if (!Schema::hasColumn('users','last_login_at'))     $t->timestamp('last_login_at')->nullable()->index();
            if (!Schema::hasColumn('users','last_activity_at'))  $t->timestamp('last_activity_at')->nullable()->index();
            if (!Schema::hasColumn('users','suspended_until'))   $t->timestamp('suspended_until')->nullable()->index();
            if (!Schema::hasColumn('users','deactivation_reason')) $t->text('deactivation_reason')->nullable();
        });
    }

    public function down(): void {
        Schema::table('users', function (Blueprint $t) {
            foreach (['status','last_login_at','last_activity_at','suspended_until','deactivation_reason'] as $c) {
                if (Schema::hasColumn('users',$c)) $t->dropColumn($c);
            }
        });
    }
};
