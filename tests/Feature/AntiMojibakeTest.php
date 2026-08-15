<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AntiMojibakeTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Scan user-facing source code files to ensure no mojibake sequences exist.
     */
    public function test_user_facing_files_contain_no_mojibake(): void
    {
        $directoriesToScan = [
            base_path('app'),
            base_path('resources/views'),
            base_path('resources/js'),
            base_path('routes'),
            base_path('config'),
            base_path('database/migrations'),
        ];

        $mojibakePatterns = [
            'Ã¡', 'Ã©', 'Ã­', 'Ã³', 'Ãº', 'Ã±',
            'Ã', 'Ã‰', 'Ã“', 'Ãš', 'Ã‘',
            'Â¿', 'Â¡', 'â€“', 'â€”', 'â€™'
        ];

        $filesWithMojibake = [];

        foreach ($directoriesToScan as $dir) {
            if (! is_dir($dir)) {
                continue;
            }

            $dirIterator = new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS);
            $iterator = new \RecursiveIteratorIterator($dirIterator);

            foreach ($iterator as $file) {
                if ($file->isDir()) {
                    continue;
                }

                $path = $file->getPathname();
                $extension = strtolower($file->getExtension());

                if (! in_array($extension, ['php', 'blade', 'js', 'css', 'json'], true)) {
                    continue;
                }

                $content = file_get_contents($path);

                foreach ($mojibakePatterns as $pattern) {
                    if (str_contains($content, $pattern)) {
                        $filesWithMojibake[] = [
                            'file' => str_replace(base_path() . DIRECTORY_SEPARATOR, '', $path),
                            'pattern' => $pattern,
                        ];
                        break;
                    }
                }
            }
        }

        $this->assertEmpty(
            $filesWithMojibake,
            'Se encontraron secuencias de mojibake en los siguientes archivos: ' . json_encode($filesWithMojibake, JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * Test that blocked/inactive/suspended user gets properly UTF-8 encoded error message upon login.
     */
    public function test_blocked_or_suspended_login_message_is_utf8_encoded(): void
    {
        $email = 'bloqueado_'.uniqid().'@example.com';
        Role::firstOrCreate(['name' => 'paciente']);
        $user = User::factory()->create([
            'email' => $email,
            'status' => User::STATUS_BLOCKED,
        ]);

        $response = $this->post('/login', [
            'email' => $email,
            'password' => 'password',
            'remember' => true,
        ]);

        $response->assertRedirect(url('/?login=1'));
        $response->assertSessionHasErrors(['email'], errorBag: 'login');

        $errors = session('errors')->getBag('login')->get('email');
        $this->assertNotEmpty($errors);
        $this->assertSame('Tu cuenta está deshabilitada o suspendida.', $errors[0]);
        $this->assertStringNotContainsString('estÃ¡', $errors[0]);
    }
}
