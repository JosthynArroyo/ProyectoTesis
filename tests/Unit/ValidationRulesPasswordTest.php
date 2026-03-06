<?php

namespace Tests\Unit;

use App\Support\ValidationRules;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ValidationRulesPasswordTest extends TestCase
{
    public function test_password_rule_accepts_valid_password(): void
    {
        $payload = [
            'password' => 'Admin123!',
            'password_confirmation' => 'Admin123!',
        ];

        $validator = Validator::make($payload, [
            'password' => ValidationRules::passwordRequired(),
        ]);

        $this->assertFalse($validator->fails(), 'La contraseña válida no debería fallar.');
    }

    public function test_password_rule_rejects_password_without_special_char(): void
    {
        $payload = [
            'password' => 'Admin1234',
            'password_confirmation' => 'Admin1234',
        ];

        $validator = Validator::make($payload, [
            'password' => ValidationRules::passwordRequired(),
        ]);

        $this->assertTrue($validator->fails(), 'La contraseña sin carácter especial debe fallar.');
    }
}
