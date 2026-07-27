<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecoverSuperadmin extends Command implements Isolatable
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:recover-superadmin';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recupera el acceso del superadministrador restableciendo su contraseña de forma segura';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // 1. Check no-interaction mode
        if ($this->option('no-interaction')) {
            $this->error('El comando debe ser ejecutado interactivamente.');
            return 1;
        }

        // 2. Count superadmins
        $superadmins = User::whereHas('roles', function ($q) {
            $q->where('name', 'superadmin');
        })->get();

        if ($superadmins->count() !== 1) {
            $this->error('Error: Debe existir exactamente un superadministrador en el sistema para realizar la recuperación (encontrados: ' . $superadmins->count() . ').');
            return 1;
        }

        $superadmin = $superadmins->first();

        // 3. Show warning and ask confirmation
        $this->warn('ADVERTENCIA: Esta acción restablecerá la contraseña del superadministrador: ' . $superadmin->email);
        $confirmText = $this->ask('Para continuar, escriba exactamente "RECUPERAR SUPERADMIN"');

        if ($confirmText !== 'RECUPERAR SUPERADMIN') {
            $this->error('Cancelado. El texto ingresado no coincide.');
            return 1;
        }

        // 4. Prompt for new password
        $password = $this->secret('Nueva contraseña');
        $passwordConfirmation = $this->secret('Confirmar nueva contraseña');

        // 5. Validation
        $email = $superadmin->email;
        $validator = Validator::make([
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], [
            'password' => [
                'required',
                'string',
                'min:12',
                'confirmed',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[^A-Za-z0-9]/',
                function ($attribute, $value, $fail) use ($email) {
                    if ($value === $email) {
                        $fail('La contraseña no puede ser igual al correo electrónico.');
                    }
                }
            ],
        ], [
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 12 caracteres.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
            'password.regex' => 'La contraseña debe contener al menos una letra mayúscula, una minúscula, un número y un símbolo especial.',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return 1;
        }

        try {
            DB::transaction(function () use ($superadmin, $password) {
                $superadmin->update([
                    'password' => $password, // cast "hashed" handles the hashing
                    'must_change_password' => true,
                    'remember_token' => null,
                ]);

                // Audit log
                Log::info('AUDIT: Superadmin recovered', [
                    'action' => 'superadmin_recovery',
                    'timestamp' => now()->toIso8601String(),
                    'channel' => 'console',
                    'actor' => 'system',
                    'user_id' => $superadmin->id,
                ]);
            });

            $this->info('Superadministrador recuperado correctamente. Se requerirá cambio de contraseña en su próximo inicio de sesión.');
            return 0;
        } catch (\Throwable $e) {
            $this->error('Error al recuperar el superadministrador: ' . $e->getMessage());
            return 1;
        }
    }
}
