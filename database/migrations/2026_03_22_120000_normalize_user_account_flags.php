<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $users = DB::table('users')
            ->select('id', 'active', 'status')
            ->orderBy('id')
            ->get();

        foreach ($users as $user) {
            $status = trim(strtolower((string) $user->status));
            $active = (bool) $user->active;

            if ($status === '') {
                $status = $active ? User::STATUS_ACTIVE : User::STATUS_INACTIVE;
            }

            if ($status === User::STATUS_ACTIVE && ! $active) {
                $status = User::STATUS_INACTIVE;
            }

            if (! in_array($status, [User::STATUS_ACTIVE, User::STATUS_INACTIVE, User::STATUS_BLOCKED], true)) {
                $status = User::STATUS_ACTIVE;
            }

            DB::table('users')
                ->where('id', $user->id)
                ->update([
                    'status' => $status,
                    'active' => $status === User::STATUS_ACTIVE,
                    'suspended_until' => $status === User::STATUS_ACTIVE
                        ? DB::raw('suspended_until')
                        : null,
                ]);
        }
    }

    public function down(): void
    {
        //
    }
};
