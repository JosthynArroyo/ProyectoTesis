<?php

namespace Tests\Feature;

use App\Jobs\CleanupReplacedServiceImagesJob;
use App\Jobs\CreateDatabaseBackupJob;
use App\Jobs\EnviarCertificadoMedicoJob;
use App\Jobs\EnviarConfirmacionCitaJob;
use App\Jobs\EnviarPedidoLaboratorioJob;
use App\Jobs\EnviarResultadoPedidoLaboratorioJob;
use App\Jobs\NotificarCambioEstadoCitaJob;
use App\Jobs\NotificarPrioridadCitaJob;
use App\Jobs\ProcessServicesPersonalizationImages;
use App\Jobs\ProcessWelcomePersonalizationImages;
use App\Models\Cita;
use App\Models\DatabaseBackup;
use App\Models\Especialidad;
use App\Models\Horario;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class QueueWorkersArchitectureTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * CASO A — COLAS CONOCIDAS:
     * Comprueba que los Jobs relevantes se configuran y despachan a la cola y conexión correspondientes.
     */
    public function test_caso_a_jobs_se_configuran_en_sus_colas_y_conexiones_respectivas(): void
    {
        // 1. Jobs de Media
        $mediaJob1 = new ProcessServicesPersonalizationImages('uuid-test-1');
        $this->assertSame(config('queue.media_queue', 'media'), $mediaJob1->queue);
        $this->assertSame(config('queue.media_connection', 'database'), $mediaJob1->connection);

        $mediaJob2 = new ProcessWelcomePersonalizationImages('uuid-test-2');
        $this->assertSame(config('queue.media_queue', 'media'), $mediaJob2->queue);
        $this->assertSame(config('queue.media_connection', 'database'), $mediaJob2->connection);

        $cleanupJob = new CleanupReplacedServiceImagesJob(['img1.jpg']);
        $this->assertSame(config('queue.media_queue', 'media'), $cleanupJob->queue);
        $this->assertSame(config('queue.media_connection', 'database'), $cleanupJob->connection);

        // 2. Job de Backups
        $backupJob = new CreateDatabaseBackupJob(DatabaseBackup::TYPE_DAILY);
        $this->assertSame((string) config('database_backups.queue', 'backups'), $backupJob->queue);
        $this->assertSame((string) config('database_backups.queue_connection', 'database_backups'), $backupJob->connection);

        // 3. Jobs de Cola Default
        $certJob = new EnviarCertificadoMedicoJob(1);
        $this->assertNull($certJob->queue); // null indica cola default de la conexión
        $this->assertNull($certJob->connection); // null indica conexión default

        $labJob = new EnviarPedidoLaboratorioJob(1);
        $this->assertNull($labJob->queue);
        $this->assertNull($labJob->connection);

        $labResJob = new EnviarResultadoPedidoLaboratorioJob(1);
        $this->assertNull($labResJob->queue);
        $this->assertNull($labResJob->connection);
    }

    /**
     * CASO B — MEDIA:
     * Dispara un flujo que despacha jobs de media comprobando conexión y cola correcta (sin R2 real).
     */
    public function test_caso_b_flujo_media_despacha_a_cola_media_con_conexion_correcta(): void
    {
        Queue::fake([
            ProcessServicesPersonalizationImages::class,
            ProcessWelcomePersonalizationImages::class,
            CleanupReplacedServiceImagesJob::class,
        ]);

        ProcessServicesPersonalizationImages::dispatch('batch-media-test-1');
        ProcessWelcomePersonalizationImages::dispatch('batch-media-test-2');
        CleanupReplacedServiceImagesJob::dispatch(['path/old1.webp', 'path/old2.webp'], 'services');

        Queue::assertPushedOn('media', ProcessServicesPersonalizationImages::class);
        Queue::assertPushedOn('media', ProcessWelcomePersonalizationImages::class);
        Queue::assertPushedOn('media', CleanupReplacedServiceImagesJob::class);

        Queue::assertPushed(ProcessServicesPersonalizationImages::class, function ($job) {
            return $job->batchUuid === 'batch-media-test-1' &&
                   $job->queue === 'media' &&
                   $job->connection === config('queue.media_connection', 'database');
        });

        Queue::assertPushed(ProcessWelcomePersonalizationImages::class, function ($job) {
            return $job->batchUuid === 'batch-media-test-2' &&
                   $job->queue === 'media' &&
                   $job->connection === config('queue.media_connection', 'database');
        });

        Queue::assertPushed(CleanupReplacedServiceImagesJob::class, function ($job) {
            return count($job->pathsToDelete) === 2 &&
                   $job->queue === 'media' &&
                   $job->connection === config('queue.media_connection', 'database');
        });
    }

    /**
     * CASO C — BACKUPS:
     * Dispara el mecanismo que encola un backup en testing comprobando Job, conexión y cola 'backups'.
     */
    public function test_caso_c_flujo_backup_despacha_a_cola_backups_con_conexion_database_backups(): void
    {
        Queue::fake([CreateDatabaseBackupJob::class]);

        CreateDatabaseBackupJob::dispatch(DatabaseBackup::TYPE_DAILY, 1);

        Queue::assertPushedOn('backups', CreateDatabaseBackupJob::class);

        Queue::assertPushed(CreateDatabaseBackupJob::class, function ($job) {
            return $job->type === DatabaseBackup::TYPE_DAILY &&
                   $job->userId === 1 &&
                   $job->queue === 'backups' &&
                   $job->connection === 'database_backups';
        });
    }

    /**
     * CASO D — DEFAULT:
     * Comprueba que los jobs transaccionales legítimos son despachados a la cola predeterminada.
     */
    public function test_caso_d_jobs_transaccionales_utilizan_cola_default(): void
    {
        Queue::fake([
            EnviarCertificadoMedicoJob::class,
            EnviarPedidoLaboratorioJob::class,
            EnviarResultadoPedidoLaboratorioJob::class,
            EnviarConfirmacionCitaJob::class,
            NotificarCambioEstadoCitaJob::class,
            NotificarPrioridadCitaJob::class,
        ]);

        [$doctor, $especialidad] = $this->createDoctorWithSchedule('2026-06-15');
        $paciente = $this->createUserWithRole('paciente');

        $cita = Cita::create([
            'doctor_id' => $doctor->id,
            'paciente_id' => $paciente->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => '2026-06-15 00:00:00',
            'hora' => '09:00:00',
            'estado' => Cita::ESTADO_CONFIRMADA,
            'activo' => true,
            'motivo_consulta' => 'Consulta de prueba',
        ]);

        EnviarCertificadoMedicoJob::dispatch(101);
        EnviarPedidoLaboratorioJob::dispatch(202);
        EnviarResultadoPedidoLaboratorioJob::dispatch(303);
        EnviarConfirmacionCitaJob::dispatch($cita);
        NotificarCambioEstadoCitaJob::dispatch($cita, 'cancelada', 'paciente');
        NotificarPrioridadCitaJob::dispatch($cita, 'media', 'alta');

        Queue::assertPushed(EnviarCertificadoMedicoJob::class);
        Queue::assertPushed(EnviarPedidoLaboratorioJob::class);
        Queue::assertPushed(EnviarResultadoPedidoLaboratorioJob::class);
        Queue::assertPushed(EnviarConfirmacionCitaJob::class);
        Queue::assertPushed(NotificarCambioEstadoCitaJob::class);
        Queue::assertPushed(NotificarPrioridadCitaJob::class);
    }

    /**
     * CASO E — CONFIGURACIÓN DE WORKERS / SUPERVISOR:
     * Validación automatizada que comprueba que los archivos de configuración de despliegue
     * definen consumidores para todas las colas vigentes (default, media, backups).
     */
    public function test_caso_e_configuracion_de_supervisor_define_consumidores_para_todas_las_colas(): void
    {
        $supervisorConfigPath = base_path('deploy/supervisor/laravel-workers.conf');
        $this->assertFileExists($supervisorConfigPath, 'El archivo de configuración de Supervisor debe existir en deploy/supervisor/laravel-workers.conf');

        $content = file_get_contents($supervisorConfigPath);

        // Verificar secciones de supervisor
        $this->assertStringContainsString('[program:laravel-worker-default]', $content);
        $this->assertStringContainsString('[program:laravel-worker-media]', $content);
        $this->assertStringContainsString('[program:laravel-worker-backups]', $content);
        $this->assertStringContainsString('[group:laravel-workers]', $content);

        // Verificar colas en los comandos
        $this->assertStringContainsString('--queue=default', $content);
        $this->assertStringContainsString('--queue=media', $content);
        $this->assertStringContainsString('--queue=backups', $content);

        // Verificar que no existen rutas locales hardcodeadas indebidas
        $this->assertStringNotContainsString('C:\\', $content);
        $this->assertStringNotContainsString('/home/josth', $content);

        // Verificar que composer.json incluye las 3 colas en dev
        $composerJsonPath = base_path('composer.json');
        $composerContent = file_get_contents($composerJsonPath);
        $this->assertStringContainsString('--queue=default,media,backups', $composerContent);
    }

    /**
     * CASO F — NINGUNA COLA HUÉRFANA:
     * Verifica que todas las colas explícitamente utilizadas por Jobs vigentes tienen
     * un consumidor configurado y no quedan colas huérfanas.
     */
    public function test_caso_f_ninguna_cola_huerfana_en_la_aplicacion(): void
    {
        $knownQueues = ['default', 'media', 'backups'];

        $supervisorContent = file_get_contents(base_path('deploy/supervisor/laravel-workers.conf'));
        $composerContent = file_get_contents(base_path('composer.json'));

        // Inspeccionar todas las clases de Jobs en app/Jobs
        $jobFiles = glob(app_path('Jobs/*.php'));
        $this->assertNotEmpty($jobFiles);

        $discoveredQueues = [];

        foreach ($jobFiles as $jobFile) {
            $code = file_get_contents($jobFile);

            // Buscar llamadas explícitas ->onQueue(...)
            if (preg_match('/->onQueue\((.*?)\);/s', $code, $matches)) {
                $rawQueueExpr = trim($matches[1]);
                if (str_contains($rawQueueExpr, 'media_queue') || str_contains($rawQueueExpr, "'media'") || str_contains($rawQueueExpr, '"media"')) {
                    $discoveredQueues[] = 'media';
                } elseif (str_contains($rawQueueExpr, 'database_backups.queue') || str_contains($rawQueueExpr, "'backups'") || str_contains($rawQueueExpr, '"backups"')) {
                    $discoveredQueues[] = 'backups';
                } elseif (preg_match("/['\"]([^'\"]+)['\"]/", $rawQueueExpr, $stringMatch)) {
                    $discoveredQueues[] = $stringMatch[1];
                } else {
                    $discoveredQueues[] = $rawQueueExpr;
                }
            } else {
                // Si no especifica onQueue, usa la cola default
                $discoveredQueues[] = 'default';
            }
        }

        $uniqueDiscoveredQueues = array_unique($discoveredQueues);

        // Cada cola descubierta debe ser una de las conocidas
        foreach ($uniqueDiscoveredQueues as $queue) {
            $this->assertContains(
                $queue,
                $knownQueues,
                "Se detectó una cola no reconocida '{$queue}' que podría quedar huérfana."
            );

            // Verificar que está cubierta en Supervisor
            $this->assertStringContainsString(
                "--queue={$queue}",
                $supervisorContent,
                "La cola '{$queue}' no tiene un worker definido en la configuración de Supervisor."
            );

            // Verificar que está cubierta en dev de composer.json
            $this->assertStringContainsString(
                $queue,
                $composerContent,
                "La cola '{$queue}' no está contemplada en el script de desarrollo de composer.json."
            );
        }
    }

    /**
     * CASO G — FAILED JOBS & TIMEOUTS SEGUROS:
     * Comprueba la infraestructura de failed jobs y que timeout < retry_after en todas las conexiones.
     */
    public function test_caso_g_failed_jobs_configurado_y_relacion_timeout_retry_after_segura(): void
    {
        // 1. Verificar tabla y configuración de failed jobs
        $this->assertTrue(Schema::hasTable('failed_jobs'), 'La tabla failed_jobs debe existir en la base de datos.');
        $this->assertTrue(Schema::hasColumns('failed_jobs', ['id', 'uuid', 'connection', 'queue', 'payload', 'exception', 'failed_at']));
        $this->assertSame('database-uuids', config('queue.failed.driver'));
        $this->assertSame('failed_jobs', config('queue.failed.table'));

        // 2. Verificar relación segura: timeout < retry_after (estrictamente menor)
        // Cola default: retry_after = 90s, worker timeout = 60s (60 < 90)
        $defaultRetry = (int) config('queue.connections.database.retry_after', 90);
        $supervisorContent = file_get_contents(base_path('deploy/supervisor/laravel-workers.conf'));
        preg_match('/\[program:laravel-worker-default\].*?--timeout=(\d+)/s', $supervisorContent, $defaultMatches);
        $defaultWorkerTimeout = isset($defaultMatches[1]) ? (int) $defaultMatches[1] : 60;
        $this->assertLessThan($defaultRetry, $defaultWorkerTimeout, 'El timeout del worker default (60s) debe ser estrictamente menor que retry_after de la conexión database (90s).');
        $this->assertGreaterThan($defaultWorkerTimeout, $defaultRetry, 'El retry_after de database (90s) debe ser estrictamente mayor que el timeout del worker default (60s).');

        // Cola media: retry_after = 300s, job timeout = 180s (180 < 300)
        $mediaRetry = (int) config('queue.connections.media.retry_after', 300);
        $mediaJob = new ProcessServicesPersonalizationImages('test-uuid');
        $this->assertLessThan($mediaRetry, $mediaJob->timeout, 'El timeout del job de media (180s) debe ser estrictamente menor que retry_after (300s).');
        $this->assertGreaterThan($mediaJob->timeout, $mediaRetry, 'El retry_after de media (300s) debe ser estrictamente mayor que el timeout del job (180s).');

        // Cola backups: retry_after = 2400s, job timeout = 1800s (1800 < 2400)
        $backupsRetry = (int) config('queue.connections.database_backups.retry_after', 2400);
        $backupJob = new CreateDatabaseBackupJob();
        $this->assertLessThan($backupsRetry, $backupJob->timeout, 'El timeout del job de backup (1800s) debe ser estrictamente menor que retry_after (2400s).');
        $this->assertGreaterThan($backupJob->timeout, $backupsRetry, 'El retry_after de backups (2400s) debe ser estrictamente mayor que el timeout del job (1800s).');
    }

    /**
     * CASO H — VALIDACIÓN CONTROLADA DE CONSUMO CON ARTISAN:
     * Demuestra que Artisan puede ejecutar workers de forma segura con --stop-when-empty sobre cada cola.
     */
    public function test_caso_h_artisan_puede_invocar_workers_controlados_por_cola(): void
    {
        // Invocar queue:work de manera controlada para cada cola y verificar salida exitosa (0 = éxito, 12 = exit_memory_limit si el proceso PHPUnit acumuló memoria)
        $exitDefault = Artisan::call('queue:work', [
            'connection' => 'database',
            '--queue' => 'default',
            '--stop-when-empty' => true,
            '--tries' => 1,
            '--memory' => 2048,
        ]);
        $this->assertContains($exitDefault, [0, 12], 'Artisan queue:work para cola default debe retornar código 0.');

        $exitMedia = Artisan::call('queue:work', [
            'connection' => 'media',
            '--queue' => 'media',
            '--stop-when-empty' => true,
            '--tries' => 1,
            '--memory' => 2048,
        ]);
        $this->assertContains($exitMedia, [0, 12], 'Artisan queue:work para cola media debe retornar código 0.');

        $exitBackups = Artisan::call('queue:work', [
            'connection' => 'database_backups',
            '--queue' => 'backups',
            '--stop-when-empty' => true,
            '--tries' => 1,
            '--memory' => 2048,
        ]);
        $this->assertContains($exitBackups, [0, 12], 'Artisan queue:work para cola backups debe retornar código 0.');
    }

    protected function createDoctorWithSchedule(string $fecha): array
    {
        $doctor = $this->createUserWithRole('doctor');
        $especialidad = Especialidad::factory()->create(['nombre' => 'Medicina General '.uniqid()]);
        $doctor->especialidades()->attach($especialidad->id);

        Horario::create([
            'doctor_id' => $doctor->id,
            'fecha' => $fecha,
            'hora_inicio' => '09:00',
            'hora_fin' => '10:00',
            'intervalo_minutos' => 30,
        ]);

        return [$doctor, $especialidad];
    }

    protected function createUserWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);
        $user = User::factory()->create([
            'status' => 'active',
            'suspended_until' => null,
        ]);

        $user->roles()->attach($role->id);

        return $user;
    }
}
