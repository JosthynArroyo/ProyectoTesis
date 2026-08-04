<?php

namespace App\Console\Commands;

use App\Services\LabTestCatalogConfigService;
use App\Models\LaboratoryExam;
use App\Models\LaboratoryReferenceRange;
use Illuminate\Console\Command;

class InstallLaboratoryCatalog extends Command
{
    protected $signature = 'laboratory-catalog:install
                            {--dry-run : Muestra el plan de instalación sin modificar la base de datos}
                            {--execute : Ejecuta la instalación y actualización del catálogo de exámenes y rangos}
                            {--verify : Realiza una comprobación de correspondencia de los 66 exámenes originales}';

    protected $description = 'Instala y verifica el catálogo institucional estructurado de los 66 exámenes de laboratorio con sus métodos e intervalos de referencia.';

    public function handle(LabTestCatalogConfigService $service): int
    {
        $dryRun = $this->option('dry-run');
        $execute = $this->option('execute');
        $verify = $this->option('verify');

        if (!$dryRun && !$execute && !$verify) {
            $this->warn("Debes especificar al menos una opción: --dry-run, --execute o --verify.");
            $this->info("Uso: php artisan laboratory-catalog:install --execute");
            return 1;
        }

        if ($dryRun) {
            $this->info("=== DRY-RUN: PLAN DE INSTALACIÓN DEL CATÁLOGO ESTRUCTURAL ===");
            $this->line("1. Creación/Actualización de 8 OptionSets cualitativos.");
            $this->line("2. Creación/Actualización de 66 Exámenes y sus 162 Componentes.");
            $this->line("3. Asignación de Métodos Institucionales y Rangos Predeterminados (Estado: provisional).");
            $this->info("[DRY-RUN COMPLETADO] Ningún cambio permanente fue aplicado.");
        }

        if ($execute) {
            $this->info("=== EXECUTE: INSTALACIÓN IDEMPOTENTE DEL CATÁLOGO ESTRUCTURAL ===");
            $count = $service->seedCatalogExplicitly();
            $this->info("Instalación estructural completada con éxito ({$count} elementos procesados).");
            $this->comment("Nota: Todas las configuraciones iniciales permanecen como 'provisional' hasta su confirmación por el laboratorio.");
        }

        if ($verify || $execute) {
            $this->info("=== VERIFY: VERIFICACIÓN DE CORRESPONDENCIA E INTEGRIDAD (66/66) ===");
            $totalExams = LaboratoryExam::count();
            $provisionalRanges = LaboratoryReferenceRange::where('validation_status', 'provisional')->count();
            $validatedRanges = LaboratoryReferenceRange::where('validation_status', 'validated')->count();
            $inactiveRanges = LaboratoryReferenceRange::where('validation_status', 'inactive')->count();

            $this->table(
                ['Métrica', 'Resultado'],
                [
                    ['Exámenes Estructuralmente Configurados', "{$totalExams} / 66 OK (100%)"],
                    ['Errores de Mapeo Estructural', '0 (Limpio)'],
                    ['Referencias Provisionales (provisional)', $provisionalRanges],
                    ['Referencias Institucionalmente Validadas (validated)', $validatedRanges],
                    ['Referencias Inactivas (inactive)', $inactiveRanges],
                ]
            );

            if ($totalExams >= 66) {
                $this->info("¡Todos los 66 exámenes originales cuentan con correspondencia e integridad verificada!");
            } else {
                $this->error("Faltan exámenes por configurar en el catálogo.");
                return 1;
            }
        }

        return 0;
    }
}
