<?php

namespace App\Services;

class LabTestCatalogService
{
    public function all(): array
    {
        return [
            'HEMATOLOGIA' => [
                'biometria_hematica' => 'Biometría Hemática completa',
                'plaquetas' => 'Plaquetas',
                'eritrosedimentacion' => 'Eritrosedimentación',
                'inv_hematozoario' => 'Inv. de hematozoario',
                'grupo_sanguineo' => 'Grupo sanguíneo',
                'reticulocitos' => 'Reticulocitos',
            ],
            'QUIMICA CINETICA' => [
                'glucosa' => 'Glucosa',
                'glucosa_2pp' => 'Glucosa 2PP',
                'urea' => 'Urea',
                'creatinina' => 'Creatinina',
                'acido_urico' => 'Ácido úrico',
                'colesterol_total' => 'Colesterol total',
                'colesterol_hdl' => 'Colesterol HDL',
                'colesterol_ldl' => 'Colesterol LDL',
                'trigliceridos' => 'Triglicéridos',
                'bilirrubinas' => 'Bilirrubinas total, dir. e indir.',
            ],
            'ENZIMAS CINETICA' => [
                'tgo_tgp' => 'T.G.O. / T.G.P.',
                'fosfatasa_alcalina' => 'Fosfatasa alcalina',
                'amilasa' => 'Amilasa',
                'lipasa' => 'Lipasa',
                'cpk' => 'C.P.K.',
                'ck_mb' => 'C.K. Mb',
            ],
            'HORMONAS' => [
                't3_ft3_t4_ft4_tsh' => 'T3, FT3, T4, FT4, TSH',
                'anti_tpo' => 'Anti-TPO',
                'lh_fsh' => 'LH / FSH',
                'prolactina' => 'Prolactina',
                'insulina' => 'Insulina',
                'estradiol' => 'Estradiol',
                'progesterona' => 'Progesterona',
                'testosterona' => 'Testosterona',
                'hcg_beta' => 'H.C.G. Beta (Embarazo)',
            ],
            'SERO INMUNOLOGIA' => [
                'asto_pcr_fr' => 'A.S.T.O. / P.C.R. / F.R.',
                'vdrl' => 'V.D.R.L.',
                'widal_weil' => 'Widal - Weil Felix',
                'brusella' => 'Brusella Abortus',
                'toxoplasma' => 'Toxoplasma IgG / IgM',
                'rubeola' => 'Rubeola IgG / IgM',
                'citomegalovirus' => 'Citomegalovirus IgG / IgM',
                'herpes' => 'Herpes I / II IgG / IgM',
                'hepatitis' => 'Hepatitis A / B / C',
                'helicobacter' => 'Helicobacter pylori',
                'dengue' => 'Dengue IgG / IgM',
            ],
            'MARCADORES TUMORALES' => [
                'psa_total_libre' => 'P.S.A. Total / Libre',
                'cea_afp' => 'C.E.A. / A.F.P.',
                'ca_125_15_3_19_9' => 'CA-125 / CA-15-3 / CA-19-9',
            ],
            'ORINA' => [
                'fisico_quimico' => 'Físico químico y sedimento',
                'gram_gota' => 'Gram de gota fresca',
                'cultivo_orina' => 'Cultivo y antibiograma',
                'microalbuminuria' => 'Microalbuminuria',
            ],
            'HECES' => [
                'coproparasitario' => 'Coproparasitario',
                'sangre_oculta' => 'Sangre oculta',
                'coprocultivo' => 'Coprocultivo',
                'rotavirus' => 'Rotavirus',
            ],
            'MICROBIOLOGIA' => [
                'cultivo_secrecion' => 'Cultivo y antibiograma',
                'tincion_gram_baar' => 'Tinción Gram / BAAR',
            ],
            'ELECTROLITOS' => [
                'sodio_potasio_cloro' => 'Sodio / Potasio / Cloro',
                'calcio_ionico' => 'Calcio / Calcio iónico',
                'hierro_fosforo_litio' => 'Hierro / Fósforo / Litio',
                'magnesio' => 'Magnesio',
            ],
            'CUADRO CRITICO' => [
                'gasometria_arterial' => 'Gasometría arterial',
                'mioglobina_stat' => 'Mioglobina STAT',
                'troponina_stat' => 'Troponina I STAT',
                'procalcitonina' => 'Procalcitonina',
            ],
            'VARIOS' => [
                'liquido_cefalorraquideo' => 'Líquido cefalorraquídeo',
                'liquido_pleural' => 'Líquido pleural',
                'liquido_sinovial' => 'Líquido sinovial',
            ],
        ];
    }

    public function label(string $key): string
    {
        foreach ($this->all() as $items) {
            if (array_key_exists($key, $items)) {
                return $items[$key];
            }
        }

        return str_replace('_', ' ', $key);
    }

    public function flat(): array
    {
        $flat = [];
        foreach ($this->all() as $category => $items) {
            foreach ($items as $key => $label) {
                $flat[$key] = [
                    'key' => $key,
                    'label' => $label,
                    'category' => $category,
                ];
            }
        }

        return $flat;
    }
}
