<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Campaign;
use App\Models\QualityForm;
use App\Models\QualityFormVersion;
use App\Models\QualityAttribute;
use App\Models\QualitySubAttribute;
use Illuminate\Support\Facades\DB;

class EntelQualityFormSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            // 1. Crear Campaña
            $campaign = Campaign::create([
                'name' => 'Entel Chile - Atención al Cliente',
                'description' => 'Campaña de atención telefónica para clientes de Entel Chile Móvil y Hogar.',
                'is_active' => true,
            ]);

            // 2. Crear Ficha de Calidad
            $form = QualityForm::create([
                'campaign_id' => $campaign->id,
                'name' => 'Ficha de Calidad Entel - V1.0',
                'description' => 'Estándar de calidad para atención de requerimientos y reclamos.',
                'created_by' => 1, // User ID 1 (Admin)
            ]);

            // 3. Crear Versión de la Ficha (Draft)
            $version = QualityFormVersion::create([
                'quality_form_id' => $form->id,
                'version_number' => 1,
                'status' => 'published', // Directamente publicada para uso inmediato
                'published_at' => now(),
                'published_by' => 1,
            ]);

            // Vincular versión a campaña
            $campaign->update(['active_form_version_id' => $version->id]);

            // 4. Definir Atributos y Subatributos
            $attributes = [
                [
                    'name' => 'Saludo y Protocolo',
                    'weight' => 10,
                    'concept' => 'Evalúa el inicio de la llamada y la identificación correcta.',
                    'sort_order' => 1,
                    'sub_attributes' => [
                        ['name' => 'Saludo inicial corporativo ("Gracias por llamar a Entel...")', 'weight_percent' => 50, 'is_critical' => false],
                        ['name' => 'Solicita nombre y RUT del cliente', 'weight_percent' => 50, 'is_critical' => false],
                    ]
                ],
                [
                    'name' => 'Habilidades Blandas / Empatía',
                    'weight' => 20,
                    'concept' => 'Evalúa el trato, tono de voz y disposición hacia el cliente.',
                    'sort_order' => 2,
                    'sub_attributes' => [
                        ['name' => 'Escucha activa (no interrumpe, valida información)', 'weight_percent' => 40, 'is_critical' => false],
                        ['name' => 'Tono de voz cálido y profesional', 'weight_percent' => 30, 'is_critical' => false],
                        ['name' => 'Uso de lenguaje positivo', 'weight_percent' => 30, 'is_critical' => false],
                    ]
                ],
                [
                    'name' => 'Manejo de la Solución',
                    'weight' => 40,
                    'concept' => 'Evalúa la capacidad de resolver el requerimiento técnico o comercial.',
                    'sort_order' => 3,
                    'sub_attributes' => [
                        ['name' => 'Diagnóstico correcto del problema', 'weight_percent' => 30, 'is_critical' => true], // Error crítico si no diagnostica bien
                        ['name' => 'Entrega de información veraz y completa', 'weight_percent' => 30, 'is_critical' => true],
                        ['name' => 'Uso correcto de herramientas/sistemas', 'weight_percent' => 20, 'is_critical' => false],
                        ['name' => 'Ofrece alternativas de solución', 'weight_percent' => 20, 'is_critical' => false],
                    ]
                ],
                [
                    'name' => 'Gestión y Procedimientos',
                    'weight' => 20,
                    'concept' => 'Evalúa el cumplimiento de procesos administrativos.',
                    'sort_order' => 4,
                    'sub_attributes' => [
                        ['name' => 'Correcta tipificación en CRM', 'weight_percent' => 50, 'is_critical' => false],
                        ['name' => 'Verificación de identidad (Seguridad)', 'weight_percent' => 50, 'is_critical' => true], // Crítico de seguridad
                    ]
                ],
                [
                    'name' => 'Cierre de Llamada',
                    'weight' => 10,
                    'concept' => 'Evalúa la despedida y confirmación de la solución.',
                    'sort_order' => 5,
                    'sub_attributes' => [
                        ['name' => 'Recapitula lo acordado en la llamada', 'weight_percent' => 50, 'is_critical' => false],
                        ['name' => 'Despedida corporativa y agradecimiento', 'weight_percent' => 50, 'is_critical' => false],
                    ]
                ],
            ];

            // 5. Insertar Atributos
            foreach ($attributes as $attrData) {
                $attribute = QualityAttribute::create([
                    'form_version_id' => $version->id,
                    'name' => $attrData['name'],
                    'weight' => $attrData['weight'],
                    'concept' => $attrData['concept'],
                    'sort_order' => $attrData['sort_order'],
                ]);

                foreach ($attrData['sub_attributes'] as $index => $subData) {
                    QualitySubAttribute::create([
                        'attribute_id' => $attribute->id,
                        'name' => $subData['name'],
                        'weight_percent' => $subData['weight_percent'],
                        'is_critical' => $subData['is_critical'],
                        'sort_order' => $index + 1,
                    ]);
                }
            }
        });

        $this->command->info('✅ Ficha de Calidad "Entel Chile" creada exitosamente.');
    }
}
