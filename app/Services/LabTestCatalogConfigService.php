<?php

namespace App\Services;

use App\Enums\LabResultClassification;
use App\Enums\LabResultType;
use App\Models\LaboratoryComponent;
use App\Models\LaboratoryExam;
use App\Models\LaboratoryExamComponent;
use App\Models\LaboratoryReferenceRange;
use App\Models\LaboratoryResultOption;
use App\Models\LaboratoryResultOptionSet;
use App\Models\LaboratoryResultValue;
use App\Models\PedidoLaboratorio;
use App\Models\PedidoLaboratorioResultado;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class LabTestCatalogConfigService
{
    /**
     * Consulta PURA DE SOLO LECTURA para construir la estructura del pedido.
     * Identifica expresamente al SUJETO REAL DE ATENCIÓN (Dependiente o Titular).
     */
    public function resolvePedidoItems(PedidoLaboratorio $pedido, ?PedidoLaboratorioResultado $resultado = null): array
    {
        $examKeys = (array) $pedido->examenes;

        // Identificar Sujeto Real (Dependiente o Titular)
        $isDependiente = (bool) ($pedido->cita?->dependiente_id && $pedido->cita?->dependiente);
        $pacienteReal = $isDependiente ? $pedido->cita->dependiente : ($pedido->cita?->paciente ?? $pedido->paciente);

        $subjectType = $isDependiente ? 'dependiente' : 'titular';
        $subjectId = $pacienteReal?->id;
        $sex = strtolower((string) ($pacienteReal?->sexo ?? 'both'));
        $birthDate = $pacienteReal?->fecha_nacimiento;

        // Prioridad estricta para fecha de cálculo de edad
        if ($pedido->sample_collected_at) {
            $sampleDate = Carbon::parse($pedido->sample_collected_at);
            $ageSource = 'sample_collection';
        } elseif ($pedido->processed_at) {
            $sampleDate = Carbon::parse($pedido->processed_at);
            $ageSource = 'processing';
        } else {
            $sampleDate = $pedido->created_at ? Carbon::parse($pedido->created_at) : now();
            $ageSource = 'order_created_fallback';
        }

        $ageDays = $birthDate ? (int) Carbon::parse($birthDate)->diffInDays($sampleDate) : 10950;

        $savedValues = collect();
        if ($resultado) {
            $savedValues = LaboratoryResultValue::where('result_id', $resultado->id)
                ->with('component')
                ->get()
                ->keyBy(fn ($v) => $v->component?->code);
        }

        $jsonSnapshot = collect($resultado->resultado_items ?? [])->keyBy('key');

        $examStructure = [];

        foreach ($examKeys as $examKey) {
            $exam = LaboratoryExam::where('code', $examKey)
                ->with(['components' => function ($q) {
                    $q->with(['optionSet.options', 'referenceRanges']);
                }])
                ->first();

            if (!$exam || $exam->components->isEmpty()) {
                $label = app(LabTestCatalogService::class)->label($examKey);
                $componentItems = [[
                    'component_id' => null,
                    'code' => $examKey,
                    'name' => $label,
                    'result_type' => LabResultType::TEXT->value,
                    'default_unit' => 'No aplica',
                    'default_method' => 'Método institucional',
                    'authorized_methods' => ['Método institucional'],
                    'reference_text' => 'No aplica',
                    'reference_type' => 'not_applicable',
                    'validation_status' => 'provisional',
                    'validation_badge' => 'Predeterminado provisional',
                    'lower_limit' => null,
                    'upper_limit' => null,
                    'critical_lower' => null,
                    'critical_upper' => null,
                    'options' => [],
                    'saved' => $this->formatSavedData(null, $jsonSnapshot->get($examKey)),
                ]];

                $examStructure[] = [
                    'exam_code' => $examKey,
                    'exam_name' => $label,
                    'is_panel' => false,
                    'components' => $componentItems,
                ];
                continue;
            }

            $componentItems = [];

            foreach ($exam->components as $comp) {
                $savedRecord = $savedValues->get($comp->code);
                $oldJson = $jsonSnapshot->get($comp->code) ?? $jsonSnapshot->get($examKey);

                $applicableRef = $this->resolveReferenceRange($comp, $sex, $ageDays, $comp->default_method);

                $optionsData = [];
                if ($comp->optionSet) {
                    $optionsData = $comp->optionSet->options->map(fn ($opt) => [
                        'code' => $opt->code,
                        'label' => $opt->label,
                        'default_classification' => $opt->default_classification,
                    ])->toArray();
                }

                $authorizedMethods = $comp->methods_list;
                $valStatus = $applicableRef['validation_status'];

                $componentItems[] = [
                    'component_id' => $comp->id,
                    'code' => $comp->code,
                    'name' => $comp->name,
                    'result_type' => $comp->result_type->value,
                    'default_unit' => $comp->default_unit ?? 'No aplica',
                    'default_method' => $comp->default_method ?? ($authorizedMethods[0] ?? 'Método institucional'),
                    'authorized_methods' => $authorizedMethods,
                    'validation_status' => $valStatus,
                    'validation_badge' => $valStatus === 'validated' ? 'Configuración institucional validada' : 'Predeterminado provisional',
                    'reference_text' => $applicableRef['text'],
                    'reference_type' => $applicableRef['type'],
                    'reference_rule_id' => $applicableRef['rule_id'],
                    'lower_limit' => $applicableRef['lower'],
                    'upper_limit' => $applicableRef['upper'],
                    'critical_lower' => $applicableRef['critical_lower'],
                    'critical_upper' => $applicableRef['critical_upper'],
                    'options' => $optionsData,
                    'formula' => $comp->formula,
                    'subject_type' => $subjectType,
                    'subject_id' => $subjectId,
                    'saved' => $this->formatSavedData($savedRecord, $oldJson, $comp, $applicableRef),
                ];
            }

            $examStructure[] = [
                'exam_code' => $exam->code,
                'exam_name' => $exam->name,
                'is_panel' => $exam->is_panel,
                'components' => $componentItems,
            ];
        }

        return $examStructure;
    }

    public function resolveReferenceRange(LaboratoryComponent $component, string $sex, int $ageDays, ?string $method = null): array
    {
        $ranges = $component->referenceRanges;

        if ($ranges->isEmpty()) {
            return [
                'text' => 'No aplica',
                'type' => 'not_applicable',
                'rule_id' => null,
                'validation_status' => 'provisional',
                'lower' => null,
                'upper' => null,
                'critical_lower' => null,
                'critical_upper' => null,
            ];
        }

        $matched = $ranges->first(function ($r) use ($sex, $ageDays, $method) {
            $sexOk = ($r->sex === 'both' || strtolower($r->sex) === strtolower($sex));
            $ageOk = ($ageDays >= $r->minimum_age_days && $ageDays <= $r->maximum_age_days);
            $methodOk = (!$method || !$r->method || strtolower($r->method) === strtolower($method));
            return $sexOk && $ageOk && $methodOk;
        }) ?? $ranges->first();

        $type = $matched->reference_type ?? 'interval';
        $valStatus = $matched->validation_status ?? 'provisional';
        $text = $matched->reference_text;

        if (!$text) {
            if ($type === 'interval' && $matched->lower_limit !== null && $matched->upper_limit !== null) {
                $text = "{$matched->lower_limit} – {$matched->upper_limit}";
            } elseif ($type === 'upper_limit' || ($matched->upper_limit !== null && $matched->lower_limit === null)) {
                $text = "< {$matched->upper_limit}";
            } elseif ($type === 'lower_limit' || ($matched->lower_limit !== null && $matched->upper_limit === null)) {
                $text = "≥ {$matched->lower_limit}";
            } elseif ($type === 'qualitative_expected') {
                $text = 'No reactivo';
            } else {
                $text = 'No aplica';
            }
        }

        return [
            'text' => $text,
            'type' => $type,
            'rule_id' => $matched->id,
            'validation_status' => $valStatus,
            'lower' => $matched->lower_limit ? (float) $matched->lower_limit : null,
            'upper' => $matched->upper_limit ? (float) $matched->upper_limit : null,
            'critical_lower' => $matched->critical_lower_limit ? (float) $matched->critical_lower_limit : null,
            'critical_upper' => $matched->critical_upper_limit ? (float) $matched->critical_upper_limit : null,
        ];
    }

    private function formatSavedData(?LaboratoryResultValue $record, ?array $oldJson = null, ?LaboratoryComponent $comp = null, array $refData = []): array
    {
        if ($record) {
            return [
                'value_numeric' => $record->value_numeric !== null ? (string) (float) $record->value_numeric : '',
                'comparator' => LaboratoryResultValue::normalizeComparator($record->comparator),
                'value_code' => $record->value_code ?? '',
                'value_text' => $record->value_text ?? '',
                'titer_denominator' => $record->titer_denominator ?? '',
                'unit' => $record->unit_snapshot ?? ($comp?->default_unit ?? 'No aplica'),
                'method' => $record->method_snapshot ?? ($comp?->default_method ?? 'Jaffé cinético'),
                'reference' => $record->reference_snapshot ?? ($refData['text'] ?? 'No aplica'),
                'classification' => $record->classification->value ?? '',
                'observation' => $record->observation ?? '',
                'extra_data' => $record->extra_data ?? [],
                'override_reason' => $record->override_reason ?? '',
            ];
        }

        if ($oldJson) {
            $res = (string) ($oldJson['resultado'] ?? '');
            $compFromRes = '=';
            $numVal = '';

            if (preg_match('/^(=|<|>|<=|>=|≤|≥)\s*(.+)$/u', $res, $matches)) {
                $compFromRes = LaboratoryResultValue::normalizeComparator($matches[1]);
                $res = trim($matches[2]);
            }

            if (is_numeric($res)) {
                $numVal = $res;
            }
            $codeVal = !is_numeric($res) ? $res : '';

            return [
                'value_numeric' => $numVal,
                'comparator' => $compFromRes,
                'value_code' => $codeVal,
                'value_text' => $res,
                'titer_denominator' => '',
                'unit' => $oldJson['unidad'] ?? ($comp?->default_unit ?? 'No aplica'),
                'method' => $oldJson['metodo'] ?? ($comp?->default_method ?? 'Método institucional'),
                'reference' => $oldJson['referencia'] ?? ($refData['text'] ?? 'No aplica'),
                'classification' => $oldJson['clasificacion'] ?? '',
                'observation' => $oldJson['observaciones'] ?? '',
                'extra_data' => [],
                'override_reason' => '',
            ];
        }

        return [
            'value_numeric' => '',
            'comparator' => '',
            'value_code' => '',
            'value_text' => '',
            'titer_denominator' => '',
            'unit' => $comp?->default_unit ?? 'No aplica',
            'method' => $comp?->default_method ?? ($comp?->methods_list[0] ?? 'Método institucional'),
            'reference' => $refData['text'] ?? 'No aplica',
            'classification' => '',
            'observation' => '',
            'extra_data' => [],
            'override_reason' => '',
        ];
    }

    public function seedCatalogExplicitly(): int
    {
        $this->seedOptionSets();
        return $this->seedExamsAndComponents();
    }

    private function seedOptionSets(): void
    {
        $sets = [
            'pos_neg' => [
                'name' => 'Positivo / Negativo',
                'options' => [
                    ['code' => 'positivo', 'label' => 'Positivo', 'classification' => 'abnormal'],
                    ['code' => 'negativo', 'label' => 'Negativo', 'classification' => 'normal'],
                ]
            ],
            'react_no_react' => [
                'name' => 'Reactivo / No reactivo',
                'options' => [
                    ['code' => 'reactivo', 'label' => 'Reactivo', 'classification' => 'abnormal'],
                    ['code' => 'no_reactivo', 'label' => 'No reactivo', 'classification' => 'normal'],
                ]
            ],
            'det_no_det' => [
                'name' => 'Detectado / No detectado',
                'options' => [
                    ['code' => 'detectado', 'label' => 'Detectado', 'classification' => 'abnormal'],
                    ['code' => 'no_detectado', 'label' => 'No detectado', 'classification' => 'normal'],
                ]
            ],
            'baar' => [
                'name' => 'BAAR Escala',
                'options' => [
                    ['code' => 'negativo', 'label' => 'Negativo', 'classification' => 'normal'],
                    ['code' => '1_plus', 'label' => '1+', 'classification' => 'abnormal'],
                    ['code' => '2_plus', 'label' => '2+', 'classification' => 'abnormal'],
                    ['code' => '3_plus', 'label' => '3+', 'classification' => 'abnormal'],
                ]
            ],
            'orina_aspecto' => [
                'name' => 'Aspecto de Orina',
                'options' => [
                    ['code' => 'limpido', 'label' => 'Límpido', 'classification' => 'normal'],
                    ['code' => 'ligeramente_turbio', 'label' => 'Ligeramente turbio', 'classification' => 'normal'],
                    ['code' => 'turbio', 'label' => 'Turbio', 'classification' => 'abnormal'],
                ]
            ],
            'orina_color' => [
                'name' => 'Color de Orina',
                'options' => [
                    ['code' => 'amarillo', 'label' => 'Amarillo', 'classification' => 'normal'],
                    ['code' => 'amarillo_claro', 'label' => 'Amarillo claro', 'classification' => 'normal'],
                    ['code' => 'ambar', 'label' => 'Ámbar', 'classification' => 'normal'],
                    ['code' => 'rojo', 'label' => 'Rojizo / Hemático', 'classification' => 'abnormal'],
                ]
            ],
            'escasa_moderada_abundante' => [
                'name' => 'Escaso / Moderado / Abundante',
                'options' => [
                    ['code' => 'no_se_observa', 'label' => 'No se observa', 'classification' => 'normal'],
                    ['code' => 'escasas', 'label' => 'Escasas / Escaso', 'classification' => 'normal'],
                    ['code' => 'moderadas', 'label' => 'Moderadas / Moderado', 'classification' => 'abnormal'],
                    ['code' => 'abundantes', 'label' => 'Abundantes / Abundante', 'classification' => 'abnormal'],
                ]
            ],
            'cultivo_estado' => [
                'name' => 'Estado de Cultivo Microbiológico',
                'options' => [
                    ['code' => 'sin_crecimiento', 'label' => 'Sin crecimiento bacteriano', 'classification' => 'normal'],
                    ['code' => 'crecimiento_significativo', 'label' => 'Crecimiento significativo', 'classification' => 'abnormal'],
                    ['code' => 'crecimiento_mixto', 'label' => 'Crecimiento mixto / Posible contaminación', 'classification' => 'indeterminate'],
                    ['code' => 'muestra_no_apta', 'label' => 'Muestra no apta', 'classification' => 'not_applicable'],
                ]
            ],
        ];

        foreach ($sets as $code => $data) {
            $set = LaboratoryResultOptionSet::firstOrCreate(
                ['code' => $code],
                ['name' => $data['name'], 'validation_status' => 'provisional']
            );

            foreach ($data['options'] as $idx => $opt) {
                LaboratoryResultOption::firstOrCreate(
                    ['option_set_id' => $set->id, 'code' => $opt['code']],
                    [
                        'label' => $opt['label'],
                        'default_classification' => $opt['classification'],
                        'display_order' => $idx + 1,
                        'validation_status' => 'provisional',
                    ]
                );
            }
        }
    }

    private function seedExamsAndComponents(): int
    {
        $optSets = LaboratoryResultOptionSet::pluck('id', 'code');
        $processed = 0;

        $examsData = [
            'biometria_hematica' => [
                'name' => 'Biometría Hemática completa',
                'category' => 'HEMATOLOGIA',
                'is_panel' => true,
                'components' => [
                    ['code' => 'hemoglobina', 'name' => 'Hemoglobina', 'type' => 'numeric', 'unit' => 'g/dL', 'method' => 'Citometría de flujo / Espectrofotometría', 'methods' => ['Citometría de flujo / Espectrofotometría'], 'ref_type' => 'interval', 'lower' => 12.0, 'upper' => 16.5, 'text' => '12.0 – 16.5 g/dL'],
                    ['code' => 'hematocrito', 'name' => 'Hematocrito', 'type' => 'numeric', 'unit' => '%', 'method' => 'Cálculo por volumen eritrocitario', 'methods' => ['Cálculo por volumen eritrocitario'], 'ref_type' => 'interval', 'lower' => 37.0, 'upper' => 50.0, 'text' => '37.0 – 50.0 %'],
                    ['code' => 'eritrositos', 'name' => 'Eritrocitos', 'type' => 'numeric', 'unit' => 'M/µL', 'method' => 'Impedancia eléctrica', 'methods' => ['Impedancia eléctrica'], 'ref_type' => 'interval', 'lower' => 4.0, 'upper' => 5.8, 'text' => '4.0 – 5.8 M/µL'],
                    ['code' => 'leucocitos', 'name' => 'Leucocitos', 'type' => 'numeric', 'unit' => '/µL', 'method' => 'Impedancia eléctrica', 'methods' => ['Impedancia eléctrica'], 'ref_type' => 'interval', 'lower' => 4500, 'upper' => 11000, 'text' => '4500 – 11000 /µL'],
                    ['code' => 'neutrofilos_abs', 'name' => 'Neutrófilos absolutos', 'type' => 'numeric', 'unit' => '/µL', 'method' => 'Focalización hidrodinámica', 'methods' => ['Focalización hidrodinámica'], 'ref_type' => 'interval', 'lower' => 1800, 'upper' => 7700, 'text' => '1800 – 7700 /µL'],
                    ['code' => 'neutrofilos_pct', 'name' => 'Neutrófilos %', 'type' => 'numeric', 'unit' => '%', 'method' => 'Citometría de flujo', 'methods' => ['Citometría de flujo'], 'ref_type' => 'interval', 'lower' => 40.0, 'upper' => 70.0, 'text' => '40.0 – 70.0 %'],
                    ['code' => 'linfocitos_abs', 'name' => 'Linfocitos absolutos', 'type' => 'numeric', 'unit' => '/µL', 'method' => 'Focalización hidrodinámica', 'methods' => ['Focalización hidrodinámica'], 'ref_type' => 'interval', 'lower' => 1000, 'upper' => 4800, 'text' => '1000 – 4800 /µL'],
                    ['code' => 'linfocitos_pct', 'name' => 'Linfocitos %', 'type' => 'numeric', 'unit' => '%', 'method' => 'Citometría de flujo', 'methods' => ['Citometría de flujo'], 'ref_type' => 'interval', 'lower' => 20.0, 'upper' => 45.0, 'text' => '20.0 – 45.0 %'],
                    ['code' => 'monocitos', 'name' => 'Monocitos', 'type' => 'numeric', 'unit' => '%', 'method' => 'Citometría de flujo', 'methods' => ['Citometría de flujo'], 'ref_type' => 'interval', 'lower' => 2.0, 'upper' => 10.0, 'text' => '2.0 – 10.0 %'],
                    ['code' => 'eosinofilos', 'name' => 'Eosinófilos', 'type' => 'numeric', 'unit' => '%', 'method' => 'Citometría de flujo', 'methods' => ['Citometría de flujo'], 'ref_type' => 'interval', 'lower' => 1.0, 'upper' => 5.0, 'text' => '1.0 – 5.0 %'],
                    ['code' => 'basofilos', 'name' => 'Basófilos', 'type' => 'numeric', 'unit' => '%', 'method' => 'Citometría de flujo', 'methods' => ['Citometría de flujo'], 'ref_type' => 'interval', 'lower' => 0.0, 'upper' => 2.0, 'text' => '0.0 – 2.0 %'],
                    ['code' => 'plaquetas_comp', 'name' => 'Plaquetas', 'type' => 'numeric', 'unit' => '/µL', 'method' => 'Impedancia eléctrica', 'methods' => ['Impedancia eléctrica'], 'ref_type' => 'interval', 'lower' => 150000, 'upper' => 450000, 'text' => '150000 – 450000 /µL'],
                    ['code' => 'vcm', 'name' => 'VCM', 'type' => 'numeric', 'unit' => 'fL', 'method' => 'Cálculo automatizado', 'methods' => ['Cálculo automatizado'], 'ref_type' => 'interval', 'lower' => 80.0, 'upper' => 100.0, 'text' => '80.0 – 100.0 fL'],
                    ['code' => 'hcm', 'name' => 'HCM', 'type' => 'numeric', 'unit' => 'pg', 'method' => 'Cálculo automatizado', 'methods' => ['Cálculo automatizado'], 'ref_type' => 'interval', 'lower' => 27.0, 'upper' => 33.0, 'text' => '27.0 – 33.0 pg'],
                    ['code' => 'chcm', 'name' => 'CHCM', 'type' => 'numeric', 'unit' => 'g/dL', 'method' => 'Cálculo automatizado', 'methods' => ['Cálculo automatizado'], 'ref_type' => 'interval', 'lower' => 32.0, 'upper' => 36.0, 'text' => '32.0 – 36.0 g/dL'],
                    ['code' => 'rdw', 'name' => 'RDW', 'type' => 'numeric', 'unit' => '%', 'method' => 'Histograma eritrocitario', 'methods' => ['Histograma eritrocitario'], 'ref_type' => 'interval', 'lower' => 11.5, 'upper' => 14.5, 'text' => '11.5 – 14.5 %'],
                ]
            ],
            'plaquetas' => ['name' => 'Plaquetas', 'category' => 'HEMATOLOGIA', 'is_panel' => false, 'components' => [['code' => 'plaquetas_ind', 'name' => 'Recuento de Plaquetas', 'type' => 'numeric', 'unit' => '/µL', 'method' => 'Impedancia eléctrica', 'methods' => ['Impedancia eléctrica'], 'ref_type' => 'interval', 'lower' => 150000, 'upper' => 450000, 'text' => '150000 – 450000 /µL']]],
            'eritrosedimentacion' => ['name' => 'Eritrosedimentación (VSG)', 'category' => 'HEMATOLOGIA', 'is_panel' => false, 'components' => [['code' => 'vsg', 'name' => 'Eritrosedimentación', 'type' => 'numeric', 'unit' => 'mm/h', 'method' => 'Westergren', 'methods' => ['Westergren'], 'ref_type' => 'upper_limit', 'upper' => 20, 'text' => '< 20 mm/h']]],
            'inv_hematozoario' => ['name' => 'Inv. de hematozoario', 'category' => 'HEMATOLOGIA', 'is_panel' => false, 'components' => [['code' => 'hematozoario', 'name' => 'Gota gruesa / Hematozoario', 'type' => 'microscopy', 'unit' => 'No aplica', 'method' => 'Microscopía óptica (Gota gruesa)', 'methods' => ['Microscopía óptica (Gota gruesa)'], 'ref_type' => 'qualitative_expected', 'text' => 'No se observan hematozoarios']]],
            'grupo_sanguineo' => ['name' => 'Grupo sanguíneo', 'category' => 'HEMATOLOGIA', 'is_panel' => false, 'components' => [['code' => 'grupo_rh', 'name' => 'Grupo Sanguíneo y Factor Rh', 'type' => 'blood_group', 'unit' => 'No aplica', 'method' => 'Hemaglutinación en lámina/tubo', 'methods' => ['Hemaglutinación en lámina/tubo'], 'ref_type' => 'not_applicable', 'text' => 'No aplica']]],
            'reticulocitos' => ['name' => 'Reticulocitos', 'category' => 'HEMATOLOGIA', 'is_panel' => false, 'components' => [['code' => 'reticulocitos_comp', 'name' => 'Recuento de Reticulocitos', 'type' => 'numeric', 'unit' => '%', 'method' => 'Tinción supravital (Azul de cresilo brillante)', 'methods' => ['Tinción supravital (Azul de cresilo brillante)'], 'ref_type' => 'interval', 'lower' => 0.5, 'upper' => 2.5, 'text' => '0.5 – 2.5 %']]],
            'glucosa' => ['name' => 'Glucosa', 'category' => 'QUIMICA CINETICA', 'is_panel' => false, 'components' => [['code' => 'glucosa_comp', 'name' => 'Glucosa en ayunas', 'type' => 'numeric', 'unit' => 'mg/dL', 'method' => 'Hexoquinasa', 'methods' => ['Hexoquinasa', 'Glucosa oxidasa'], 'ref_type' => 'interval', 'lower' => 70, 'upper' => 100, 'text' => '70 – 100 mg/dL']]],
            'glucosa_2pp' => ['name' => 'Glucosa 2PP', 'category' => 'QUIMICA CINETICA', 'is_panel' => false, 'components' => [['code' => 'glucosa_2pp_comp', 'name' => 'Glucosa 2 horas post-prandial', 'type' => 'numeric', 'unit' => 'mg/dL', 'method' => 'Hexoquinasa', 'methods' => ['Hexoquinasa', 'Glucosa oxidasa'], 'ref_type' => 'upper_limit', 'upper' => 140, 'text' => '< 140 mg/dL']]],
            'urea' => ['name' => 'Urea', 'category' => 'QUIMICA CINETICA', 'is_panel' => false, 'components' => [['code' => 'urea_comp', 'name' => 'Urea sérica', 'type' => 'numeric', 'unit' => 'mg/dL', 'method' => 'Ureasa / GLDH', 'methods' => ['Ureasa / GLDH'], 'ref_type' => 'interval', 'lower' => 15, 'upper' => 45, 'text' => '15 – 45 mg/dL']]],
            'creatinina' => ['name' => 'Creatinina', 'category' => 'QUIMICA CINETICA', 'is_panel' => false, 'components' => [['code' => 'creatinina_comp', 'name' => 'Creatinina sérica', 'type' => 'numeric', 'unit' => 'mg/dL', 'method' => 'Jaffé cinético', 'methods' => ['Jaffé cinético', 'Enzimático'], 'ref_type' => 'interval', 'lower' => 0.50, 'upper' => 0.95, 'text' => '0.50 – 0.95 mg/dL']]],
            'acido_urico' => ['name' => 'Ácido úrico', 'category' => 'QUIMICA CINETICA', 'is_panel' => false, 'components' => [['code' => 'acido_urico_comp', 'name' => 'Ácido úrico', 'type' => 'numeric', 'unit' => 'mg/dL', 'method' => 'Uricasa / POD', 'methods' => ['Uricasa / POD'], 'ref_type' => 'interval', 'lower' => 3.4, 'upper' => 7.2, 'text' => '3.4 – 7.2 mg/dL']]],
            'colesterol_total' => ['name' => 'Colesterol total', 'category' => 'QUIMICA CINETICA', 'is_panel' => false, 'components' => [['code' => 'colesterol_total_comp', 'name' => 'Colesterol total', 'type' => 'numeric', 'unit' => 'mg/dL', 'method' => 'Enzimático colorimétrico (CHOD-PAP)', 'methods' => ['Enzimático colorimétrico (CHOD-PAP)'], 'ref_type' => 'upper_limit', 'upper' => 200, 'text' => '< 200 mg/dL']]],
            'colesterol_hdl' => ['name' => 'Colesterol HDL', 'category' => 'QUIMICA CINETICA', 'is_panel' => false, 'components' => [['code' => 'colesterol_hdl_comp', 'name' => 'Colesterol HDL', 'type' => 'numeric', 'unit' => 'mg/dL', 'method' => 'Enzimático directo selectivo', 'methods' => ['Enzimático directo selectivo'], 'ref_type' => 'lower_limit', 'lower' => 40, 'text' => '≥ 40 mg/dL']]],
            'colesterol_ldl' => ['name' => 'Colesterol LDL', 'category' => 'QUIMICA CINETICA', 'is_panel' => false, 'components' => [['code' => 'colesterol_ldl_comp', 'name' => 'Colesterol LDL', 'type' => 'numeric', 'unit' => 'mg/dL', 'method' => 'Enzimático homogéneo directo', 'methods' => ['Enzimático homogéneo directo', 'Friedewald'], 'ref_type' => 'upper_limit', 'upper' => 100, 'text' => '< 100 mg/dL']]],
            'trigliceridos' => ['name' => 'Triglicéridos', 'category' => 'QUIMICA CINETICA', 'is_panel' => false, 'components' => [['code' => 'trigliceridos_comp', 'name' => 'Triglicéridos', 'type' => 'numeric', 'unit' => 'mg/dL', 'method' => 'Enzimático colorimétrico (GPO-PAP)', 'methods' => ['Enzimático colorimétrico (GPO-PAP)'], 'ref_type' => 'upper_limit', 'upper' => 150, 'text' => '< 150 mg/dL']]],
            'bilirrubinas' => [
                'name' => 'Bilirrubinas total, dir. e indir.',
                'category' => 'QUIMICA CINETICA',
                'is_panel' => true,
                'components' => [
                    ['code' => 'bilirrubina_total', 'name' => 'Bilirrubina Total', 'type' => 'numeric', 'unit' => 'mg/dL', 'method' => 'Dichlorophenyldiazonium (DPD)', 'methods' => ['Dichlorophenyldiazonium (DPD)'], 'ref_type' => 'interval', 'lower' => 0.2, 'upper' => 1.2, 'text' => '0.2 – 1.2 mg/dL'],
                    ['code' => 'bilirrubina_directa', 'name' => 'Bilirrubina Directa', 'type' => 'numeric', 'unit' => 'mg/dL', 'method' => 'Dichlorophenyldiazonium (DPD)', 'methods' => ['Dichlorophenyldiazonium (DPD)'], 'ref_type' => 'upper_limit', 'upper' => 0.3, 'text' => '< 0.3 mg/dL'],
                    ['code' => 'bilirrubina_indirecta', 'name' => 'Bilirrubina Indirecta', 'type' => 'calculated', 'unit' => 'mg/dL', 'method' => 'Cálculo por resta (Total - Directa)', 'methods' => ['Cálculo por resta (Total - Directa)'], 'ref_type' => 'interval', 'lower' => 0.1, 'upper' => 0.9, 'text' => '0.1 – 0.9 mg/dL', 'formula' => 'total - directa'],
                ]
            ],
            'tgo_tgp' => ['name' => 'T.G.O. / T.G.P.', 'category' => 'ENZIMAS CINETICA', 'is_panel' => true, 'components' => [['code' => 'tgo', 'name' => 'T.G.O. (AST)', 'type' => 'numeric', 'unit' => 'U/L', 'method' => 'IFCC sin piridoxal fosfato', 'methods' => ['IFCC sin piridoxal fosfato'], 'ref_type' => 'upper_limit', 'upper' => 35, 'text' => '< 35 U/L'], ['code' => 'tgp', 'name' => 'T.G.P. (ALT)', 'type' => 'numeric', 'unit' => 'U/L', 'method' => 'IFCC sin piridoxal fosfato', 'methods' => ['IFCC sin piridoxal fosfato'], 'ref_type' => 'upper_limit', 'upper' => 45, 'text' => '< 45 U/L']]],
            'fosfatasa_alcalina' => ['name' => 'Fosfatasa alcalina', 'category' => 'ENZIMAS CINETICA', 'is_panel' => false, 'components' => [['code' => 'fosfatasa_alc', 'name' => 'Fosfatasa alcalina', 'type' => 'numeric', 'unit' => 'U/L', 'method' => 'IFCC AMP buffer', 'methods' => ['IFCC AMP buffer'], 'ref_type' => 'interval', 'lower' => 40, 'upper' => 130, 'text' => '40 – 130 U/L']]],
            'amilasa' => ['name' => 'Amilasa', 'category' => 'ENZIMAS CINETICA', 'is_panel' => false, 'components' => [['code' => 'amilasa_comp', 'name' => 'Amilasa sérica', 'type' => 'numeric', 'unit' => 'U/L', 'method' => 'CNPG3 sustrato', 'methods' => ['CNPG3 sustrato'], 'ref_type' => 'interval', 'lower' => 28, 'upper' => 100, 'text' => '28 – 100 U/L']]],
            'lipasa' => ['name' => 'Lipasa', 'category' => 'ENZIMAS CINETICA', 'is_panel' => false, 'components' => [['code' => 'lipasa_comp', 'name' => 'Lipasa sérica', 'type' => 'numeric', 'unit' => 'U/L', 'method' => 'Enzimático colorimétrico con colipasa', 'methods' => ['Enzimático colorimétrico con colipasa'], 'ref_type' => 'interval', 'lower' => 13, 'upper' => 60, 'text' => '13 – 60 U/L']]],
            'cpk' => ['name' => 'C.P.K.', 'category' => 'ENZIMAS CINETICA', 'is_panel' => false, 'components' => [['code' => 'cpk_comp', 'name' => 'CPK Total', 'type' => 'numeric', 'unit' => 'U/L', 'method' => 'IFCC cinético a 37°C', 'methods' => ['IFCC cinético a 37°C'], 'ref_type' => 'interval', 'lower' => 26, 'upper' => 192, 'text' => '26 – 192 U/L']]],
            'ck_mb' => ['name' => 'C.K. Mb', 'category' => 'ENZIMAS CINETICA', 'is_panel' => false, 'components' => [['code' => 'ck_mb_comp', 'name' => 'CK-MB', 'type' => 'numeric', 'unit' => 'U/L', 'method' => 'Inmunoinhibición cinético', 'methods' => ['Inmunoinhibición cinético'], 'ref_type' => 'upper_limit', 'upper' => 25, 'text' => '< 25 U/L']]],
            't3_ft3_t4_ft4_tsh' => ['name' => 'Perfil Tiroideo completo', 'category' => 'HORMONAS', 'is_panel' => true, 'components' => [['code' => 't3', 'name' => 'T3 Total', 'type' => 'numeric', 'unit' => 'ng/dL', 'method' => 'Quimioluminiscencia (CLIA)', 'methods' => ['Quimioluminiscencia (CLIA)'], 'ref_type' => 'interval', 'lower' => 80, 'upper' => 200, 'text' => '80 – 200 ng/dL'], ['code' => 'ft3', 'name' => 'T3 Libre (FT3)', 'type' => 'numeric', 'unit' => 'pg/mL', 'method' => 'Quimioluminiscencia (CLIA)', 'methods' => ['Quimioluminiscencia (CLIA)'], 'ref_type' => 'interval', 'lower' => 2.0, 'upper' => 4.4, 'text' => '2.0 – 4.4 pg/mL'], ['code' => 't4', 'name' => 'T4 Total', 'type' => 'numeric', 'unit' => 'µg/dL', 'method' => 'Quimioluminiscencia (CLIA)', 'methods' => ['Quimioluminiscencia (CLIA)'], 'ref_type' => 'interval', 'lower' => 4.5, 'upper' => 12.0, 'text' => '4.5 – 12.0 µg/dL'], ['code' => 'ft4', 'name' => 'T4 Libre (FT4)', 'type' => 'numeric', 'unit' => 'ng/dL', 'method' => 'Quimioluminiscencia (CLIA)', 'methods' => ['Quimioluminiscencia (CLIA)'], 'ref_type' => 'interval', 'lower' => 0.9, 'upper' => 1.7, 'text' => '0.9 – 1.7 ng/dL'], ['code' => 'tsh', 'name' => 'TSH Ultrasensible', 'type' => 'numeric', 'unit' => 'µUI/mL', 'method' => 'Quimioluminiscencia (CLIA)', 'methods' => ['Quimioluminiscencia (CLIA)'], 'ref_type' => 'interval', 'lower' => 0.4, 'upper' => 4.2, 'text' => '0.4 – 4.2 µUI/mL']]],
            'anti_tpo' => ['name' => 'Anti-TPO', 'category' => 'HORMONAS', 'is_panel' => false, 'components' => [['code' => 'anti_tpo_comp', 'name' => 'Anticuerpos Anti-TPO', 'type' => 'numeric', 'unit' => 'UI/mL', 'method' => 'Quimioluminiscencia (CLIA)', 'methods' => ['Quimioluminiscencia (CLIA)'], 'ref_type' => 'upper_limit', 'upper' => 34, 'text' => '< 34 UI/mL']]],
            'lh_fsh' => ['name' => 'LH / FSH', 'category' => 'HORMONAS', 'is_panel' => true, 'components' => [['code' => 'lh', 'name' => 'Hormona Luteinizante (LH)', 'type' => 'numeric', 'unit' => 'mUI/mL', 'method' => 'Quimioluminiscencia (CLIA)', 'methods' => ['Quimioluminiscencia (CLIA)'], 'ref_type' => 'interval', 'lower' => 1.7, 'upper' => 8.6, 'text' => '1.7 – 8.6 mUI/mL'], ['code' => 'fsh', 'name' => 'Hormona Folículo Estimulante (FSH)', 'type' => 'numeric', 'unit' => 'mUI/mL', 'method' => 'Quimioluminiscencia (CLIA)', 'methods' => ['Quimioluminiscencia (CLIA)'], 'ref_type' => 'interval', 'lower' => 1.5, 'upper' => 12.4, 'text' => '1.5 – 12.4 mUI/mL']]],
            'prolactina' => ['name' => 'Prolactina', 'category' => 'HORMONAS', 'is_panel' => false, 'components' => [['code' => 'prolactina_comp', 'name' => 'Prolactina', 'type' => 'numeric', 'unit' => 'ng/mL', 'method' => 'Quimioluminiscencia (CLIA)', 'methods' => ['Quimioluminiscencia (CLIA)'], 'ref_type' => 'interval', 'lower' => 4.8, 'upper' => 23.3, 'text' => '4.8 – 23.3 ng/mL']]],
            'insulina' => ['name' => 'Insulina', 'category' => 'HORMONAS', 'is_panel' => false, 'components' => [['code' => 'insulina_comp', 'name' => 'Insulina basal', 'type' => 'numeric', 'unit' => 'µUI/mL', 'method' => 'Quimioluminiscencia (CLIA)', 'methods' => ['Quimioluminiscencia (CLIA)'], 'ref_type' => 'interval', 'lower' => 2.6, 'upper' => 24.9, 'text' => '2.6 – 24.9 µUI/mL']]],
            'estradiol' => ['name' => 'Estradiol', 'category' => 'HORMONAS', 'is_panel' => false, 'components' => [['code' => 'estradiol_comp', 'name' => 'Estradiol (E2)', 'type' => 'numeric', 'unit' => 'pg/mL', 'method' => 'Quimioluminiscencia (CLIA)', 'methods' => ['Quimioluminiscencia (CLIA)'], 'ref_type' => 'interval', 'lower' => 15, 'upper' => 350, 'text' => '15 – 350 pg/mL']]],
            'progesterona' => ['name' => 'Progesterona', 'category' => 'HORMONAS', 'is_panel' => false, 'components' => [['code' => 'progesterona_comp', 'name' => 'Progesterona', 'type' => 'numeric', 'unit' => 'ng/mL', 'method' => 'Quimioluminiscencia (CLIA)', 'methods' => ['Quimioluminiscencia (CLIA)'], 'ref_type' => 'interval', 'lower' => 0.2, 'upper' => 25.0, 'text' => '0.2 – 25.0 ng/mL']]],
            'testosterona' => ['name' => 'Testosterona', 'category' => 'HORMONAS', 'is_panel' => false, 'components' => [['code' => 'testosterona_comp', 'name' => 'Testosterona Total', 'type' => 'numeric', 'unit' => 'ng/dL', 'method' => 'Quimioluminiscencia (CLIA)', 'methods' => ['Quimioluminiscencia (CLIA)'], 'ref_type' => 'interval', 'lower' => 240, 'upper' => 870, 'text' => '240 – 870 ng/dL']]],
            'hcg_beta' => ['name' => 'H.C.G. Beta (Embarazo)', 'category' => 'HORMONAS', 'is_panel' => false, 'components' => [['code' => 'hcg_beta_comp', 'name' => 'HCG Beta Cuantitativa', 'type' => 'numeric', 'unit' => 'mUI/mL', 'method' => 'Quimioluminiscencia (CLIA)', 'methods' => ['Quimioluminiscencia (CLIA)'], 'ref_type' => 'upper_limit', 'upper' => 5, 'text' => '< 5 mUI/mL']]],
            'asto_pcr_fr' => ['name' => 'A.S.T.O. / P.C.R. / F.R.', 'category' => 'SERO INMUNOLOGIA', 'is_panel' => true, 'components' => [['code' => 'asto', 'name' => 'A.S.T.O. (Antiestreptolisina O)', 'type' => 'numeric', 'unit' => 'UI/mL', 'method' => 'Inmunoturbidimetría', 'methods' => ['Inmunoturbidimetría'], 'ref_type' => 'upper_limit', 'upper' => 200, 'text' => '< 200 UI/mL'], ['code' => 'pcr_cuant', 'name' => 'Proteína C Reactiva (PCR)', 'type' => 'numeric', 'unit' => 'mg/L', 'method' => 'Inmunoturbidimetría de alta sensibilidad', 'methods' => ['Inmunoturbidimetría de alta sensibilidad'], 'ref_type' => 'upper_limit', 'upper' => 5, 'text' => '< 5 mg/L'], ['code' => 'factor_reumatoideo', 'name' => 'Factor Reumatoideo (FR)', 'type' => 'numeric', 'unit' => 'UI/mL', 'method' => 'Inmunoturbidimetría', 'methods' => ['Inmunoturbidimetría'], 'ref_type' => 'upper_limit', 'upper' => 14, 'text' => '< 14 UI/mL']]],
            'vdrl' => ['name' => 'V.D.R.L.', 'category' => 'SERO INMUNOLOGIA', 'is_panel' => false, 'components' => [['code' => 'vdrl_comp', 'name' => 'VDRL Serología', 'type' => 'coded', 'option_set' => 'react_no_react', 'unit' => 'No aplica', 'method' => 'Floculación en lámina', 'methods' => ['Floculación en lámina'], 'ref_type' => 'qualitative_expected', 'text' => 'No reactivo']]],
            'widal_weil' => ['name' => 'Widal - Weil Felix', 'category' => 'SERO INMUNOLOGIA', 'is_panel' => true, 'components' => [['code' => 'typhi_o', 'name' => 'Salmonella typhi O', 'type' => 'titer', 'unit' => 'dilución', 'method' => 'Aglutinación en tubo', 'methods' => ['Aglutinación en tubo'], 'ref_type' => 'titer', 'text' => '< 1:80'], ['code' => 'typhi_h', 'name' => 'Salmonella typhi H', 'type' => 'titer', 'unit' => 'dilución', 'method' => 'Aglutinación en tubo', 'methods' => ['Aglutinación en tubo'], 'ref_type' => 'titer', 'text' => '< 1:80'], ['code' => 'paratyphi_a', 'name' => 'Salmonella paratyphi A', 'type' => 'titer', 'unit' => 'dilución', 'method' => 'Aglutinación en tubo', 'methods' => ['Aglutinación en tubo'], 'ref_type' => 'titer', 'text' => '< 1:80'], ['code' => 'paratyphi_b', 'name' => 'Salmonella paratyphi B', 'type' => 'titer', 'unit' => 'dilución', 'method' => 'Aglutinación en tubo', 'methods' => ['Aglutinación en tubo'], 'ref_type' => 'titer', 'text' => '< 1:80'], ['code' => 'proteus_ox19', 'name' => 'Proteus OX19', 'type' => 'titer', 'unit' => 'dilución', 'method' => 'Aglutinación en tubo', 'methods' => ['Aglutinación en tubo'], 'ref_type' => 'titer', 'text' => '< 1:80']]],
            'brusella' => ['name' => 'Brucella abortus', 'category' => 'SERO INMUNOLOGIA', 'is_panel' => false, 'components' => [['code' => 'brusella_comp', 'name' => 'Brucella abortus (Rosa de Bengala)', 'type' => 'coded', 'option_set' => 'react_no_react', 'unit' => 'No aplica', 'method' => 'Rosa de Bengala', 'methods' => ['Rosa de Bengala'], 'ref_type' => 'qualitative_expected', 'text' => 'No reactivo']]],
            'toxoplasma' => ['name' => 'Toxoplasma IgG / IgM', 'category' => 'SERO INMUNOLOGIA', 'is_panel' => true, 'components' => [['code' => 'toxoplasma_igg', 'name' => 'Toxoplasma IgG', 'type' => 'numeric', 'unit' => 'UI/mL', 'method' => 'Quimioluminiscencia (CLIA)', 'methods' => ['Quimioluminiscencia (CLIA)'], 'ref_type' => 'upper_limit', 'upper' => 10, 'text' => '< 10 UI/mL'], ['code' => 'toxoplasma_igm', 'name' => 'Toxoplasma IgM', 'type' => 'coded', 'option_set' => 'react_no_react', 'unit' => 'No aplica', 'method' => 'Quimioluminiscencia (CLIA)', 'methods' => ['Quimioluminiscencia (CLIA)'], 'ref_type' => 'qualitative_expected', 'text' => 'No reactivo']]],
            'rubeola' => ['name' => 'Rubeola IgG / IgM', 'category' => 'SERO INMUNOLOGIA', 'is_panel' => true, 'components' => [['code' => 'rubeola_igg', 'name' => 'Rubeola IgG', 'type' => 'numeric', 'unit' => 'UI/mL', 'method' => 'Quimioluminiscencia (CLIA)', 'methods' => ['Quimioluminiscencia (CLIA)'], 'ref_type' => 'upper_limit', 'upper' => 10, 'text' => '< 10 UI/mL'], ['code' => 'rubeola_igm', 'name' => 'Rubeola IgM', 'type' => 'coded', 'option_set' => 'react_no_react', 'unit' => 'No aplica', 'method' => 'Quimioluminiscencia (CLIA)', 'methods' => ['Quimioluminiscencia (CLIA)'], 'ref_type' => 'qualitative_expected', 'text' => 'No reactivo']]],
            'citomegalovirus' => ['name' => 'Citomegalovirus IgG / IgM', 'category' => 'SERO INMUNOLOGIA', 'is_panel' => true, 'components' => [['code' => 'cmv_igg', 'name' => 'Citomegalovirus IgG', 'type' => 'numeric', 'unit' => 'U/mL', 'method' => 'Quimioluminiscencia (CLIA)', 'methods' => ['Quimioluminiscencia (CLIA)'], 'ref_type' => 'upper_limit', 'upper' => 6, 'text' => '< 6 U/mL'], ['code' => 'cmv_igm', 'name' => 'Citomegalovirus IgM', 'type' => 'coded', 'option_set' => 'react_no_react', 'unit' => 'No aplica', 'method' => 'Quimioluminiscencia (CLIA)', 'methods' => ['Quimioluminiscencia (CLIA)'], 'ref_type' => 'qualitative_expected', 'text' => 'No reactivo']]],
            'herpes' => ['name' => 'Herpes I / II IgG / IgM', 'category' => 'SERO INMUNOLOGIA', 'is_panel' => true, 'components' => [['code' => 'hsv1_igg', 'name' => 'HSV-1 IgG', 'type' => 'coded', 'option_set' => 'react_no_react', 'unit' => 'No aplica', 'method' => 'ELISA', 'methods' => ['ELISA'], 'ref_type' => 'qualitative_expected', 'text' => 'No reactivo'], ['code' => 'hsv1_igm', 'name' => 'HSV-1 IgM', 'type' => 'coded', 'option_set' => 'react_no_react', 'unit' => 'No aplica', 'method' => 'ELISA', 'methods' => ['ELISA'], 'ref_type' => 'qualitative_expected', 'text' => 'No reactivo'], ['code' => 'hsv2_igg', 'name' => 'HSV-2 IgG', 'type' => 'coded', 'option_set' => 'react_no_react', 'unit' => 'No aplica', 'method' => 'ELISA', 'methods' => ['ELISA'], 'ref_type' => 'qualitative_expected', 'text' => 'No reactivo'], ['code' => 'hsv2_igm', 'name' => 'HSV-2 IgM', 'type' => 'coded', 'option_set' => 'react_no_react', 'unit' => 'No aplica', 'method' => 'ELISA', 'methods' => ['ELISA'], 'ref_type' => 'qualitative_expected', 'text' => 'No reactivo']]],
            'hepatitis' => ['name' => 'Hepatitis A / B / C', 'category' => 'SERO INMUNOLOGIA', 'is_panel' => true, 'components' => [['code' => 'hav_igm', 'name' => 'HAV IgM', 'type' => 'coded', 'option_set' => 'react_no_react', 'unit' => 'No aplica', 'method' => 'MEIA / Quimioluminiscencia', 'methods' => ['MEIA / Quimioluminiscencia'], 'ref_type' => 'qualitative_expected', 'text' => 'No reactivo'], ['code' => 'hbsag', 'name' => 'HBsAg (Antígeno de Superficie Hb)', 'type' => 'coded', 'option_set' => 'react_no_react', 'unit' => 'No aplica', 'method' => 'Quimioluminiscencia (CLIA)', 'methods' => ['Quimioluminiscencia (CLIA)'], 'ref_type' => 'qualitative_expected', 'text' => 'No reactivo'], ['code' => 'anti_hbc', 'name' => 'Anti-HBc Total', 'type' => 'coded', 'option_set' => 'react_no_react', 'unit' => 'No aplica', 'method' => 'Quimioluminiscencia (CLIA)', 'methods' => ['Quimioluminiscencia (CLIA)'], 'ref_type' => 'qualitative_expected', 'text' => 'No reactivo'], ['code' => 'anti_hbs', 'name' => 'Anti-HBs', 'type' => 'numeric', 'unit' => 'mUI/mL', 'method' => 'Quimioluminiscencia (CLIA)', 'methods' => ['Quimioluminiscencia (CLIA)'], 'ref_type' => 'lower_limit', 'lower' => 10, 'text' => '≥ 10 mUI/mL'], ['code' => 'anti_hcv', 'name' => 'Anti-HCV', 'type' => 'coded', 'option_set' => 'react_no_react', 'unit' => 'No aplica', 'method' => 'Quimioluminiscencia (CLIA)', 'methods' => ['Quimioluminiscencia (CLIA)'], 'ref_type' => 'qualitative_expected', 'text' => 'No reactivo']]],
            'helicobacter' => ['name' => 'Helicobacter pylori', 'category' => 'SERO INMUNOLOGIA', 'is_panel' => false, 'components' => [['code' => 'hpylori_comp', 'name' => 'Helicobacter pylori (Antígeno en heces)', 'type' => 'coded', 'option_set' => 'pos_neg', 'unit' => 'No aplica', 'method' => 'Inmunocromatografía', 'methods' => ['Inmunocromatografía'], 'ref_type' => 'qualitative_expected', 'text' => 'Negativo']]],
            'dengue' => ['name' => 'Dengue IgG / IgM', 'category' => 'SERO INMUNOLOGIA', 'is_panel' => true, 'components' => [['code' => 'dengue_ns1', 'name' => 'Dengue Antígeno NS1', 'type' => 'coded', 'option_set' => 'pos_neg', 'unit' => 'No aplica', 'method' => 'Inmunocromatografía rápida', 'methods' => ['Inmunocromatografía rápida'], 'ref_type' => 'qualitative_expected', 'text' => 'Negativo'], ['code' => 'dengue_igg', 'name' => 'Dengue IgG', 'type' => 'coded', 'option_set' => 'pos_neg', 'unit' => 'No aplica', 'method' => 'Inmunocromatografía rápida', 'methods' => ['Inmunocromatografía rápida'], 'ref_type' => 'qualitative_expected', 'text' => 'Negativo'], ['code' => 'dengue_igm', 'name' => 'Dengue IgM', 'type' => 'coded', 'option_set' => 'pos_neg', 'unit' => 'No aplica', 'method' => 'Inmunocromatografía rápida', 'methods' => ['Inmunocromatografía rápida'], 'ref_type' => 'qualitative_expected', 'text' => 'Negativo']]],
            'psa_total_libre' => ['name' => 'P.S.A. Total / Libre', 'category' => 'MARCADORES TUMORALES', 'is_panel' => true, 'components' => [['code' => 'psa_total', 'name' => 'PSA Total', 'type' => 'numeric', 'unit' => 'ng/mL', 'method' => 'Quimioluminiscencia (ECLIA)', 'methods' => ['Quimioluminiscencia (ECLIA)'], 'ref_type' => 'upper_limit', 'upper' => 4.0, 'text' => '< 4.0 ng/mL'], ['code' => 'psa_libre', 'name' => 'PSA Libre', 'type' => 'numeric', 'unit' => 'ng/mL', 'method' => 'Quimioluminiscencia (ECLIA)', 'methods' => ['Quimioluminiscencia (ECLIA)'], 'ref_type' => 'upper_limit', 'upper' => 0.93, 'text' => '< 0.93 ng/mL'], ['code' => 'psa_ratio', 'name' => 'Relación PSA Libre/Total', 'type' => 'calculated', 'unit' => '%', 'method' => 'Cálculo automatizado (Libre/Total * 100)', 'methods' => ['Cálculo automatizado (Libre/Total * 100)'], 'ref_type' => 'lower_limit', 'lower' => 20, 'text' => '> 20 %', 'formula' => '(libre / total) * 100']]],
            'cea_afp' => ['name' => 'C.E.A. / A.F.P.', 'category' => 'MARCADORES TUMORALES', 'is_panel' => true, 'components' => [['code' => 'cea', 'name' => 'C.E.A. (Antígeno Carcinoembrionario)', 'type' => 'numeric', 'unit' => 'ng/mL', 'method' => 'Quimioluminiscencia (ECLIA)', 'methods' => ['Quimioluminiscencia (ECLIA)'], 'ref_type' => 'upper_limit', 'upper' => 3.8, 'text' => '< 3.8 ng/mL'], ['code' => 'afp', 'name' => 'A.F.P. (Alfa Fetoproteína)', 'type' => 'numeric', 'unit' => 'UI/mL', 'method' => 'Quimioluminiscencia (ECLIA)', 'methods' => ['Quimioluminiscencia (ECLIA)'], 'ref_type' => 'upper_limit', 'upper' => 5.8, 'text' => '< 5.8 UI/mL']]],
            'ca_125_15_3_19_9' => ['name' => 'CA-125 / CA-15-3 / CA-19-9', 'category' => 'MARCADORES TUMORALES', 'is_panel' => true, 'components' => [['code' => 'ca125', 'name' => 'CA-125', 'type' => 'numeric', 'unit' => 'U/mL', 'method' => 'Quimioluminiscencia (ECLIA)', 'methods' => ['Quimioluminiscencia (ECLIA)'], 'ref_type' => 'upper_limit', 'upper' => 35, 'text' => '< 35 U/mL'], ['code' => 'ca15_3', 'name' => 'CA 15-3', 'type' => 'numeric', 'unit' => 'U/mL', 'method' => 'Quimioluminiscencia (ECLIA)', 'methods' => ['Quimioluminiscencia (ECLIA)'], 'ref_type' => 'upper_limit', 'upper' => 25, 'text' => '< 25 U/mL'], ['code' => 'ca19_9', 'name' => 'CA 19-9', 'type' => 'numeric', 'unit' => 'U/mL', 'method' => 'Quimioluminiscencia (ECLIA)', 'methods' => ['Quimioluminiscencia (ECLIA)'], 'ref_type' => 'upper_limit', 'upper' => 37, 'text' => '< 37 U/mL']]],
            'fisico_quimico' => [
                'name' => 'Físico químico y sedimento',
                'category' => 'ORINA',
                'is_panel' => true,
                'components' => [
                    ['code' => 'uri_color', 'name' => 'Color', 'type' => 'coded', 'option_set' => 'orina_color', 'unit' => 'No aplica', 'method' => 'Inspección visual directa', 'methods' => ['Inspección visual directa'], 'ref_type' => 'qualitative_expected', 'text' => 'Amarillo'],
                    ['code' => 'uri_aspecto', 'name' => 'Aspecto', 'type' => 'coded', 'option_set' => 'orina_aspecto', 'unit' => 'No aplica', 'method' => 'Inspección visual directa', 'methods' => ['Inspección visual directa'], 'ref_type' => 'qualitative_expected', 'text' => 'Límpido'],
                    ['code' => 'uri_densidad', 'name' => 'Densidad', 'type' => 'numeric', 'unit' => 'g/mL', 'method' => 'Refractometría / Tira reactiva', 'methods' => ['Refractometría / Tira reactiva'], 'ref_type' => 'interval', 'lower' => 1.005, 'upper' => 1.030, 'text' => '1.005 – 1.030 g/mL'],
                    ['code' => 'uri_ph', 'name' => 'pH', 'type' => 'numeric', 'unit' => 'No aplica', 'method' => 'Potenciometría / Tira reactiva', 'methods' => ['Potenciometría / Tira reactiva'], 'ref_type' => 'interval', 'lower' => 5.0, 'upper' => 8.0, 'text' => '5.0 – 8.0'],
                    ['code' => 'uri_proteinas', 'name' => 'Proteínas en orina', 'type' => 'coded', 'option_set' => 'pos_neg', 'unit' => 'No aplica', 'method' => 'Colorimetría con tira reactiva', 'methods' => ['Colorimetría con tira reactiva'], 'ref_type' => 'qualitative_expected', 'text' => 'Negativo'],
                    ['code' => 'uri_glucosa', 'name' => 'Glucosa en orina', 'type' => 'coded', 'option_set' => 'pos_neg', 'unit' => 'No aplica', 'method' => 'Enzimático (Glucosa oxidasa)', 'methods' => ['Enzimático (Glucosa oxidasa)'], 'ref_type' => 'qualitative_expected', 'text' => 'Negativo'],
                    ['code' => 'uri_cetonas', 'name' => 'Cetonas', 'type' => 'coded', 'option_set' => 'pos_neg', 'unit' => 'No aplica', 'method' => 'Nitroprusiato sódico', 'methods' => ['Nitroprusiato sódico'], 'ref_type' => 'qualitative_expected', 'text' => 'Negativo'],
                    ['code' => 'uri_bilirrubina', 'name' => 'Bilirrubina', 'type' => 'coded', 'option_set' => 'pos_neg', 'unit' => 'No aplica', 'method' => 'Reacción de acoplamiento diazoico', 'methods' => ['Reacción de acoplamiento diazoico'], 'ref_type' => 'qualitative_expected', 'text' => 'Negativo'],
                    ['code' => 'uri_urobilinogeno', 'name' => 'Urobilinógeno', 'type' => 'numeric', 'unit' => 'mg/dL', 'method' => 'Reacción de Ehrlich', 'methods' => ['Reacción de Ehrlich'], 'ref_type' => 'interval', 'lower' => 0.2, 'upper' => 1.0, 'text' => '0.2 – 1.0 mg/dL'],
                    ['code' => 'uri_sangre', 'name' => 'Sangre / Hemoglobina', 'type' => 'coded', 'option_set' => 'pos_neg', 'unit' => 'No aplica', 'method' => 'Actividad pseudoperoxidásica', 'methods' => ['Actividad pseudoperoxidásica'], 'ref_type' => 'qualitative_expected', 'text' => 'Negativo'],
                    ['code' => 'uri_nitritos', 'name' => 'Nitritos', 'type' => 'coded', 'option_set' => 'pos_neg', 'unit' => 'No aplica', 'method' => 'Reacción de Griess', 'methods' => ['Reacción de Griess'], 'ref_type' => 'qualitative_expected', 'text' => 'Negativo'],
                    ['code' => 'uri_esterasa', 'name' => 'Esterasa leucocitaria', 'type' => 'coded', 'option_set' => 'pos_neg', 'unit' => 'No aplica', 'method' => 'Enzimático (Éster de indoxilo)', 'methods' => ['Enzimático (Éster de indoxilo)'], 'ref_type' => 'qualitative_expected', 'text' => 'Negativo'],
                    ['code' => 'uri_leucocitos_campo', 'name' => 'Leucocitos por campo', 'type' => 'numeric', 'unit' => '/campo', 'method' => 'Microscopía de sedimento (400x)', 'methods' => ['Microscopía de sedimento (400x)'], 'ref_type' => 'upper_limit', 'upper' => 5, 'text' => '< 5 /campo'],
                    ['code' => 'uri_eritrocitos_campo', 'name' => 'Eritrocitos por campo', 'type' => 'numeric', 'unit' => '/campo', 'method' => 'Microscopía de sedimento (400x)', 'methods' => ['Microscopía de sedimento (400x)'], 'ref_type' => 'upper_limit', 'upper' => 3, 'text' => '< 3 /campo'],
                    ['code' => 'uri_celulas_epiteliales', 'name' => 'Células epiteliales', 'type' => 'coded', 'option_set' => 'escasa_moderada_abundante', 'unit' => 'No aplica', 'method' => 'Microscopía de sedimento (400x)', 'methods' => ['Microscopía de sedimento (400x)'], 'ref_type' => 'qualitative_expected', 'text' => 'Escasas'],
                    ['code' => 'uri_bacterias', 'name' => 'Bacterias', 'type' => 'coded', 'option_set' => 'escasa_moderada_abundante', 'unit' => 'No aplica', 'method' => 'Microscopía de sedimento (400x)', 'methods' => ['Microscopía de sedimento (400x)'], 'ref_type' => 'qualitative_expected', 'text' => 'No se observa'],
                    ['code' => 'uri_cristales', 'name' => 'Cristales', 'type' => 'text', 'unit' => 'No aplica', 'method' => 'Microscopía de contraste de fase', 'methods' => ['Microscopía de contraste de fase'], 'ref_type' => 'qualitative_expected', 'text' => 'No se observan'],
                    ['code' => 'uri_cilindros', 'name' => 'Cilindros', 'type' => 'text', 'unit' => 'No aplica', 'method' => 'Microscopía de sedimento (100x/400x)', 'methods' => ['Microscopía de sedimento (100x/400x)'], 'ref_type' => 'qualitative_expected', 'text' => 'No se observan'],
                    ['code' => 'uri_levaduras', 'name' => 'Levaduras', 'type' => 'coded', 'option_set' => 'escasa_moderada_abundante', 'unit' => 'No aplica', 'method' => 'Microscopía de sedimento (400x)', 'methods' => ['Microscopía de sedimento (400x)'], 'ref_type' => 'qualitative_expected', 'text' => 'No se observa'],
                ]
            ],
            'gram_gota' => ['name' => 'Gram de gota fresca', 'category' => 'ORINA', 'is_panel' => false, 'components' => [['code' => 'gram_gota_comp', 'name' => 'Gram de gota fresca', 'type' => 'microscopy', 'unit' => 'No aplica', 'method' => 'Microscopía óptica con tinción Gram', 'methods' => ['Microscopía óptica con tinción Gram'], 'ref_type' => 'qualitative_expected', 'text' => 'No se observan bacterias']]],
            'cultivo_orina' => ['name' => 'Cultivo y antibiograma (Urocultivo)', 'category' => 'ORINA', 'is_panel' => false, 'components' => [['code' => 'urocultivo_comp', 'name' => 'Urocultivo y susceptibilidad', 'type' => 'culture', 'unit' => 'UFC/mL', 'method' => 'Siembra en agar CLED/MacConkey + Kirby-Bauer', 'methods' => ['Siembra en agar CLED/MacConkey + Kirby-Bauer'], 'ref_type' => 'qualitative_expected', 'text' => 'Sin crecimiento bacteriano']]],
            'microalbuminuria' => ['name' => 'Microalbuminuria', 'category' => 'ORINA', 'is_panel' => false, 'components' => [['code' => 'microalb_comp', 'name' => 'Microalbuminuria', 'type' => 'numeric', 'unit' => 'mg/L', 'method' => 'Inmunoturbidimetría', 'methods' => ['Inmunoturbidimetría'], 'ref_type' => 'upper_limit', 'upper' => 20, 'text' => '< 20 mg/L']]],
            'coproparasitario' => ['name' => 'Coproparasitario', 'category' => 'HECES', 'is_panel' => false, 'components' => [['code' => 'copro_comp', 'name' => 'Examen coproparasitario', 'type' => 'microscopy', 'unit' => 'No aplica', 'method' => 'Examen directo con solución salina y lugol', 'methods' => ['Examen directo con solución salina y lugol'], 'ref_type' => 'qualitative_expected', 'text' => 'No se observan parásitos ni quistes']]],
            'sangre_oculta' => ['name' => 'Sangre oculta en heces', 'category' => 'HECES', 'is_panel' => false, 'components' => [['code' => 'sangre_oculta_comp', 'name' => 'Sangre oculta', 'type' => 'coded', 'option_set' => 'pos_neg', 'unit' => 'No aplica', 'method' => 'Inmunocromatografía humana (FOB)', 'methods' => ['Inmunocromatografía humana (FOB)'], 'ref_type' => 'qualitative_expected', 'text' => 'Negativo']]],
            'coprocultivo' => ['name' => 'Coprocultivo', 'category' => 'HECES', 'is_panel' => false, 'components' => [['code' => 'coprocultivo_comp', 'name' => 'Coprocultivo y antibiograma', 'type' => 'culture', 'unit' => 'No aplica', 'method' => 'Siembra en agares entéricos (SS, MacConkey)', 'methods' => ['Siembra en agares entéricos (SS, MacConkey)'], 'ref_type' => 'qualitative_expected', 'text' => 'Sin desarrollo de enteropatógenos']]],
            'rotavirus' => ['name' => 'Rotavirus', 'category' => 'HECES', 'is_panel' => false, 'components' => [['code' => 'rotavirus_comp', 'name' => 'Rotavirus en heces', 'type' => 'coded', 'option_set' => 'pos_neg', 'unit' => 'No aplica', 'method' => 'Inmunocromatografía', 'methods' => ['Inmunocromatografía'], 'ref_type' => 'qualitative_expected', 'text' => 'Negativo']]],
            'cultivo_secrecion' => ['name' => 'Cultivo y antibiograma de secreción', 'category' => 'MICROBIOLOGIA', 'is_panel' => false, 'components' => [['code' => 'micro_cultivo_comp', 'name' => 'Cultivo de secreción y susceptibilidad', 'type' => 'culture', 'unit' => 'No aplica', 'method' => 'Siembra en agar sangre/chocolate + Kirby-Bauer', 'methods' => ['Siembra en agar sangre/chocolate + Kirby-Bauer'], 'ref_type' => 'qualitative_expected', 'text' => 'Sin desarrollo patógeno']]],
            'tincion_gram_baar' => ['name' => 'Tinción Gram / BAAR', 'category' => 'MICROBIOLOGIA', 'is_panel' => true, 'components' => [['code' => 'tincion_gram', 'name' => 'Tinción de Gram', 'type' => 'microscopy', 'unit' => 'No aplica', 'method' => 'Microscopía óptica con tinción Gram', 'methods' => ['Microscopía óptica con tinción Gram'], 'ref_type' => 'qualitative_expected', 'text' => 'Microbiota habitual'], ['code' => 'tincion_baar', 'name' => 'Tinción BAAR (Ziehl-Neelsen)', 'type' => 'coded', 'option_set' => 'baar', 'unit' => 'No aplica', 'method' => 'Ziehl-Neelsen', 'methods' => ['Ziehl-Neelsen'], 'ref_type' => 'qualitative_expected', 'text' => 'Negativo para BAAR']]],
            'sodio_potasio_cloro' => ['name' => 'Sodio / Potasio / Cloro', 'category' => 'ELECTROLITOS', 'is_panel' => true, 'components' => [['code' => 'sodio', 'name' => 'Sodio (Na)', 'type' => 'numeric', 'unit' => 'mmol/L', 'method' => 'Electrodo selectivo de iones (ISE)', 'methods' => ['Electrodo selectivo de iones (ISE)'], 'ref_type' => 'interval', 'lower' => 135, 'upper' => 145, 'text' => '135 – 145 mmol/L'], ['code' => 'potasio', 'name' => 'Potasio (K)', 'type' => 'numeric', 'unit' => 'mmol/L', 'method' => 'Electrodo selectivo de iones (ISE)', 'methods' => ['Electrodo selectivo de iones (ISE)'], 'ref_type' => 'interval', 'lower' => 3.5, 'upper' => 5.1, 'text' => '3.5 – 5.1 mmol/L'], ['code' => 'cloro', 'name' => 'Cloro (Cl)', 'type' => 'numeric', 'unit' => 'mmol/L', 'method' => 'Electrodo selectivo de iones (ISE)', 'methods' => ['Electrodo selectivo de iones (ISE)'], 'ref_type' => 'interval', 'lower' => 98, 'upper' => 107, 'text' => '98 – 107 mmol/L']]],
            'calcio_ionico' => ['name' => 'Calcio / Calcio iónico', 'category' => 'ELECTROLITOS', 'is_panel' => true, 'components' => [['code' => 'calcio_total', 'name' => 'Calcio Total', 'type' => 'numeric', 'unit' => 'mg/dL', 'method' => 'Arsenazo III', 'methods' => ['Arsenazo III'], 'ref_type' => 'interval', 'lower' => 8.5, 'upper' => 10.5, 'text' => '8.5 – 10.5 mg/dL'], ['code' => 'calcio_ionico_comp', 'name' => 'Calcio Iónico', 'type' => 'numeric', 'unit' => 'mmol/L', 'method' => 'Electrodo selectivo de iones (ISE)', 'methods' => ['Electrodo selectivo de iones (ISE)'], 'ref_type' => 'interval', 'lower' => 1.15, 'upper' => 1.33, 'text' => '1.15 – 1.33 mmol/L']]],
            'hierro_fosforo_litio' => ['name' => 'Hierro / Fósforo / Litio', 'category' => 'ELECTROLITOS', 'is_panel' => true, 'components' => [['code' => 'hierro', 'name' => 'Hierro Sérico', 'type' => 'numeric', 'unit' => 'µg/dL', 'method' => 'Ferene colorimétrico', 'methods' => ['Ferene colorimétrico'], 'ref_type' => 'interval', 'lower' => 60, 'upper' => 170, 'text' => '60 – 170 µg/dL'], ['code' => 'fosforo', 'name' => 'Fósforo Sérico', 'type' => 'numeric', 'unit' => 'mg/dL', 'method' => 'Fosfomolibdato UV', 'methods' => ['Fosfomolibdato UV'], 'ref_type' => 'interval', 'lower' => 2.5, 'upper' => 4.5, 'text' => '2.5 – 4.5 mg/dL'], ['code' => 'litio', 'name' => 'Litio Sérico', 'type' => 'numeric', 'unit' => 'mmol/L', 'method' => 'Electrodo selectivo de iones / Espectrofotometría', 'methods' => ['Electrodo selectivo de iones / Espectrofotometría'], 'ref_type' => 'therapeutic', 'lower' => 0.6, 'upper' => 1.2, 'text' => '0.6 – 1.2 mmol/L (Rango terapéutico)']]],
            'magnesio' => ['name' => 'Magnesio', 'category' => 'ELECTROLITOS', 'is_panel' => false, 'components' => [['code' => 'magnesio_comp', 'name' => 'Magnesio Sérico', 'type' => 'numeric', 'unit' => 'mg/dL', 'method' => 'Azul de xilidilo', 'methods' => ['Azul de xilidilo'], 'ref_type' => 'interval', 'lower' => 1.7, 'upper' => 2.2, 'text' => '1.7 – 2.2 mg/dL']]],
            'gasometria_arterial' => ['name' => 'Gasometría arterial', 'category' => 'CUADRO CRITICO', 'is_panel' => true, 'components' => [['code' => 'gas_ph', 'name' => 'pH arterial', 'type' => 'numeric', 'unit' => 'No aplica', 'method' => 'Potenciometría directa', 'methods' => ['Potenciometría directa'], 'ref_type' => 'interval', 'lower' => 7.35, 'upper' => 7.45, 'text' => '7.35 – 7.45'], ['code' => 'gas_pco2', 'name' => 'pCO2', 'type' => 'numeric', 'unit' => 'mmHg', 'method' => 'Electrodo Severinghaus', 'methods' => ['Electrodo Severinghaus'], 'ref_type' => 'interval', 'lower' => 35, 'upper' => 45, 'text' => '35 – 45 mmHg'], ['code' => 'gas_po2', 'name' => 'pO2', 'type' => 'numeric', 'unit' => 'mmHg', 'method' => 'Electrodo Clark', 'methods' => ['Electrodo Clark'], 'ref_type' => 'interval', 'lower' => 80, 'upper' => 100, 'text' => '80 – 100 mmHg'], ['code' => 'gas_hco3', 'name' => 'HCO3 (Bicarbonato)', 'type' => 'numeric', 'unit' => 'mmol/L', 'method' => 'Cálculo por ecuación Henderson-Hasselbalch', 'methods' => ['Cálculo por ecuación Henderson-Hasselbalch'], 'ref_type' => 'interval', 'lower' => 22, 'upper' => 26, 'text' => '22 – 26 mmol/L'], ['code' => 'gas_eb', 'name' => 'Exceso de base (EB)', 'type' => 'numeric', 'unit' => 'mmol/L', 'method' => 'Cálculo automatizado', 'methods' => ['Cálculo automatizado'], 'ref_type' => 'interval', 'lower' => -2, 'upper' => 2, 'text' => '-2 – +2 mmol/L'], ['code' => 'gas_sato2', 'name' => 'Saturación de O2', 'type' => 'numeric', 'unit' => '%', 'method' => 'Co-oximetría', 'methods' => ['Co-oximetría'], 'ref_type' => 'lower_limit', 'lower' => 95, 'text' => '≥ 95 %']]],
            'mioglobina_stat' => ['name' => 'Mioglobina STAT', 'category' => 'CUADRO CRITICO', 'is_panel' => false, 'components' => [['code' => 'mioglobina_comp', 'name' => 'Mioglobina STAT', 'type' => 'numeric', 'unit' => 'ng/mL', 'method' => 'Quimioluminiscencia rápida', 'methods' => ['Quimioluminiscencia rápida'], 'ref_type' => 'upper_limit', 'upper' => 85, 'text' => '< 85 ng/mL']]],
            'troponina_stat' => ['name' => 'Troponina I STAT', 'category' => 'CUADRO CRITICO', 'is_panel' => false, 'components' => [['code' => 'troponina_comp', 'name' => 'Troponina I STAT', 'type' => 'numeric', 'unit' => 'ng/mL', 'method' => 'Quimioluminiscencia de alta sensibilidad', 'methods' => ['Quimioluminiscencia de alta sensibilidad'], 'ref_type' => 'upper_limit', 'upper' => 0.04, 'text' => '< 0.04 ng/mL']]],
            'procalcitonina' => ['name' => 'Procalcitonina', 'category' => 'CUADRO CRITICO', 'is_panel' => false, 'components' => [['code' => 'procalcitonina_comp', 'name' => 'Procalcitonina (PCT)', 'type' => 'numeric', 'unit' => 'ng/mL', 'method' => 'Inmunofluorescencia cuantitativa (TRACE)', 'methods' => ['Inmunofluorescencia cuantitativa (TRACE)'], 'ref_type' => 'upper_limit', 'upper' => 0.5, 'text' => '< 0.5 ng/mL']]],
            'liquido_cefalorraquideo' => ['name' => 'Líquido cefalorraquídeo (LCR)', 'category' => 'VARIOS', 'is_panel' => true, 'components' => [['code' => 'lcr_color', 'name' => 'LCR Color', 'type' => 'text', 'unit' => 'No aplica', 'method' => 'Inspección macroscópica', 'methods' => ['Inspección macroscópica'], 'ref_type' => 'qualitative_expected', 'text' => 'Incoloro (Roca de manantial)'], ['code' => 'lcr_aspecto', 'name' => 'LCR Aspecto', 'type' => 'text', 'unit' => 'No aplica', 'method' => 'Inspección macroscópica', 'methods' => ['Inspección macroscópica'], 'ref_type' => 'qualitative_expected', 'text' => 'Límpido / Transparente'], ['code' => 'lcr_celulas', 'name' => 'Recuento celular LCR', 'type' => 'numeric', 'unit' => '/mm³', 'method' => 'Cámara de Fuchs-Rosenthal / Neubauer', 'methods' => ['Cámara de Fuchs-Rosenthal / Neubauer'], 'ref_type' => 'upper_limit', 'upper' => 5, 'text' => '< 5 /mm³'], ['code' => 'lcr_diferencial', 'name' => 'Diferencial LCR', 'type' => 'text', 'unit' => 'No aplica', 'method' => 'Citocentrífuga y tinción Wright', 'methods' => ['Citocentrífuga y tinción Wright'], 'ref_type' => 'interpretive_text', 'text' => '100% Mononucleares (predominio)'], ['code' => 'lcr_glucosa', 'name' => 'Glucosa LCR', 'type' => 'numeric', 'unit' => 'mg/dL', 'method' => 'Hexoquinasa', 'methods' => ['Hexoquinasa'], 'ref_type' => 'interval', 'lower' => 40, 'upper' => 70, 'text' => '40 – 70 mg/dL (60% de glucosa plasmática)'], ['code' => 'lcr_proteinas', 'name' => 'Proteínas LCR', 'type' => 'numeric', 'unit' => 'mg/dL', 'method' => 'Rojo de pirogallol', 'methods' => ['Rojo de pirogallol'], 'ref_type' => 'interval', 'lower' => 15, 'upper' => 45, 'text' => '15 – 45 mg/dL'], ['code' => 'lcr_gram', 'name' => 'Gram LCR', 'type' => 'microscopy', 'unit' => 'No aplica', 'method' => 'Microscopía óptica con tinción Gram', 'methods' => ['Microscopía óptica con tinción Gram'], 'ref_type' => 'qualitative_expected', 'text' => 'No se observan bacterias'], ['code' => 'lcr_cultivo', 'name' => 'Cultivo LCR', 'type' => 'culture', 'unit' => 'No aplica', 'method' => 'Siembra en agar sangre/chocolate y tioglicolato', 'methods' => ['Siembra en agar sangre/chocolate y tioglicolato'], 'ref_type' => 'qualitative_expected', 'text' => 'Estéril a las 48 horas']]],
            'liquido_pleural' => ['name' => 'Líquido pleural', 'category' => 'VARIOS', 'is_panel' => true, 'components' => [['code' => 'pleural_color', 'name' => 'Color', 'type' => 'text', 'unit' => 'No aplica', 'method' => 'Inspección macroscópica', 'methods' => ['Inspección macroscópica'], 'ref_type' => 'qualitative_expected', 'text' => 'Amarillo cetrino'], ['code' => 'pleural_aspecto', 'name' => 'Aspecto', 'type' => 'text', 'unit' => 'No aplica', 'method' => 'Inspección macroscópica', 'methods' => ['Inspección macroscópica'], 'ref_type' => 'qualitative_expected', 'text' => 'Transparente / Límpido'], ['code' => 'pleural_celulas', 'name' => 'Recuento celular', 'type' => 'numeric', 'unit' => '/µL', 'method' => 'Cámara de recuento celular', 'methods' => ['Cámara de recuento celular'], 'ref_type' => 'upper_limit', 'upper' => 1000, 'text' => '< 1000 /µL'], ['code' => 'pleural_proteinas', 'name' => 'Proteínas', 'type' => 'numeric', 'unit' => 'g/dL', 'method' => 'Biuret', 'methods' => ['Biuret'], 'ref_type' => 'interpretive_text', 'text' => 'Criterios de Light (Relación pleural/sérica < 0.5)'], ['code' => 'pleural_ldh', 'name' => 'LDH', 'type' => 'numeric', 'unit' => 'U/L', 'method' => 'IFCC cinético', 'methods' => ['IFCC cinético'], 'ref_type' => 'interpretive_text', 'text' => 'Criterios de Light (Relación pleural/sérica < 0.6)'], ['code' => 'pleural_glucosa', 'name' => 'Glucosa', 'type' => 'numeric', 'unit' => 'mg/dL', 'method' => 'Hexoquinasa', 'methods' => ['Hexoquinasa'], 'ref_type' => 'interpretive_text', 'text' => 'Similar a glucemia plasmática (≥ 60 mg/dL)'], ['code' => 'pleural_ph', 'name' => 'pH', 'type' => 'numeric', 'unit' => 'No aplica', 'method' => 'Potenciometría directa en gasómetro', 'methods' => ['Potenciometría directa en gasómetro'], 'ref_type' => 'lower_limit', 'lower' => 7.60, 'text' => '7.60 – 7.64 (Fisiológico)'], ['code' => 'pleural_gram', 'name' => 'Gram', 'type' => 'microscopy', 'unit' => 'No aplica', 'method' => 'Microscopía óptica con tinción Gram', 'methods' => ['Microscopía óptica con tinción Gram'], 'ref_type' => 'qualitative_expected', 'text' => 'No se observan bacterias'], ['code' => 'pleural_cultivo', 'name' => 'Cultivo', 'type' => 'culture', 'unit' => 'No aplica', 'method' => 'Siembra en agar sangre/chocolate', 'methods' => ['Siembra en agar sangre/chocolate'], 'ref_type' => 'qualitative_expected', 'text' => 'Estéril']]],
            'liquido_sinovial' => ['name' => 'Líquido sinovial', 'category' => 'VARIOS', 'is_panel' => true, 'components' => [['code' => 'sinovial_color', 'name' => 'Color', 'type' => 'text', 'unit' => 'No aplica', 'method' => 'Inspección macroscópica', 'methods' => ['Inspección macroscópica'], 'ref_type' => 'qualitative_expected', 'text' => 'Incoloro o pajizo claro'], ['code' => 'sinovial_aspecto', 'name' => 'Aspecto', 'type' => 'text', 'unit' => 'No aplica', 'method' => 'Inspección macroscópica', 'methods' => ['Inspección macroscópica'], 'ref_type' => 'qualitative_expected', 'text' => 'Transparente / Claro'], ['code' => 'sinovial_viscosidad', 'name' => 'Viscosidad', 'type' => 'text', 'unit' => 'No aplica', 'method' => 'Prueba del filamento (Drop test)', 'methods' => ['Prueba del filamento (Drop test)'], 'ref_type' => 'qualitative_expected', 'text' => 'Alta (Filamento de 3 a 5 cm)'], ['code' => 'sinovial_leucocitos', 'name' => 'Leucocitos', 'type' => 'numeric', 'unit' => '/µL', 'method' => 'Cámara de recuento en solución salina', 'methods' => ['Cámara de recuento en solución salina'], 'ref_type' => 'upper_limit', 'upper' => 200, 'text' => '< 200 /µL'], ['code' => 'sinovial_cristales', 'name' => 'Cristales', 'type' => 'microscopy', 'unit' => 'No aplica', 'method' => 'Microscopía de luz polarizada', 'methods' => ['Microscopía de luz polarizada'], 'ref_type' => 'qualitative_expected', 'text' => 'Ausencia de cristales urato/pirofosfato'], ['code' => 'sinovial_gram', 'name' => 'Gram', 'type' => 'microscopy', 'unit' => 'No aplica', 'method' => 'Microscopía óptica con tinción Gram', 'methods' => ['Microscopía óptica con tinción Gram'], 'ref_type' => 'qualitative_expected', 'text' => 'No se observan bacterias'], ['code' => 'sinovial_cultivo', 'name' => 'Cultivo', 'type' => 'culture', 'unit' => 'No aplica', 'method' => 'Siembra en agar sangre/chocolate y tioglicolato', 'methods' => ['Siembra en agar sangre/chocolate y tioglicolato'], 'ref_type' => 'qualitative_expected', 'text' => 'Estéril']]],
        ];

        foreach ($examsData as $code => $data) {
            $exam = LaboratoryExam::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $data['name'],
                    'category' => $data['category'],
                    'is_panel' => $data['is_panel'],
                    'active' => true,
                    'version' => 1,
                ]
            );
            $processed++;

            foreach ($data['components'] as $idx => $compData) {
                $optionSetId = isset($compData['option_set']) ? ($optSets[$compData['option_set']] ?? null) : null;
                $methodsList = $compData['methods'] ?? [$compData['method'] ?? 'Jaffé cinético'];

                $comp = LaboratoryComponent::updateOrCreate(
                    ['code' => $compData['code']],
                    [
                        'name' => $compData['name'],
                        'result_type' => $compData['type'],
                        'default_unit' => $compData['unit'] ?? 'No aplica',
                        'decimal_places' => 2,
                        'option_set_id' => $optionSetId,
                        'default_method' => $methodsList[0],
                        'authorized_methods' => $methodsList,
                        'formula' => $compData['formula'] ?? null,
                        'validation_status' => 'provisional',
                        'active' => true,
                    ]
                );

                LaboratoryExamComponent::updateOrCreate(
                    ['exam_id' => $exam->id, 'component_id' => $comp->id],
                    ['display_order' => $idx + 1, 'required' => true]
                );

                LaboratoryReferenceRange::updateOrCreate(
                    ['component_id' => $comp->id, 'sex' => 'both'],
                    [
                        'reference_type' => $compData['ref_type'] ?? 'interval',
                        'minimum_age_days' => 0,
                        'maximum_age_days' => 36500,
                        'method' => $methodsList[0],
                        'lower_limit' => $compData['lower'] ?? null,
                        'upper_limit' => $compData['upper'] ?? null,
                        'validation_status' => 'provisional',
                        'source_note' => 'Configuración predeterminada provisional - Requiere confirmación por responsable de laboratorio',
                        'reference_text' => $compData['text'] ?? ($compData['ref_type'] === 'not_applicable' ? 'No aplica' : null),
                    ]
                );
            }
        }

        return $processed;
    }
}
