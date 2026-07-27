<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateSuperadmin extends Command implements Isolatable
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:create-superadmin';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crea de forma segura el superadministrador inicial del sistema';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // 1. Check if superadmin role exists
        $role = Role::where('name', 'superadmin')->first();
        if (!$role) {
            $this->error('El rol "superadmin" no existe. Por favor ejecute ProductionSeeder primero.');
            return 1;
        }

        // 2. Check if a superadmin already exists
        $exists = User::whereHas('roles', function ($q) {
            $q->where('name', 'superadmin');
        })->exists();

        if ($exists) {
            $this->error('Ya existe un usuario con el rol de superadministrador.');
            return 1;
        }

        // 3. Environment check
        if (config('app.env') === 'production' || env('APP_ENV') === 'production') {
            if ($this->option('no-interaction')) {
                $this->error('El comando debe ser ejecutado interactivamente en producción.');
                return 1;
            }

            $this->warn('ADVERTENCIA: Se va a crear el usuario de superadministrador con el máximo privilegio en producción.');
            $confirmText = $this->ask('Para continuar, escriba exactamente "CREAR SUPERADMIN"');

            if ($confirmText !== 'CREAR SUPERADMIN') {
                $this->error('Cancelado. El texto ingresado no coincide.');
                return 1;
            }
        }

        // 4. Interactive prompts
        $name = $this->ask('Nombre completo');
        $email = $this->ask('Correo electrónico');

        // Normalize email
        $email = $email !== null ? trim(strtolower($email)) : '';

        $password = $this->secret('Contraseña');
        $passwordConfirmation = $this->secret('Confirmar contraseña');

        // 5. Validations
        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], [
            'name' => ['required', 'string', 'max:255', 'regex:/^[^\x00-\x1F\x7F]+$/u'],
            'email' => ['required', 'email', 'unique:users,email'],
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
            'name.required' => 'El nombre completo es obligatorio.',
            'name.regex' => 'El nombre completo no debe contener caracteres de control.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El formato del correo electrónico es inválido.',
            'email.unique' => 'El correo electrónico ya está registrado.',
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

        // 6. Final confirmation
        if (!$this->confirm('¿Está seguro de que desea crear este superadministrador?', true)) {
            $this->info('Creación cancelada.');
            return 0;
        }

        try {
            DB::transaction(function () use ($name, $email, $password, $role) {
                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'password' => $password,
                    'status' => User::STATUS_ACTIVE,
                    'active' => true,
                    'must_change_password' => true,
                ]);

                $user->email_verified_at = now();
                $user->save();

                $user->roles()->sync([$role->id]);

                // Audit log
                Log::info('AUDIT: Superadmin created', [
                    'action' => 'superadmin_initial_creation',
                    'timestamp' => now()->toIso8601String(),
                    'channel' => 'console',
                    'actor' => 'system',
                    'user_id' => $user->id,
                ]);
            });

            $this->info('Superadministrador creado correctamente.');
            return 0;
        } catch (\Throwable $e) {
            $this->error('Error al crear el superadministrador: ' . $e->getMessage());
            return 1;
        }
    }
}
