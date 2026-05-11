<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Campaign;
use App\Models\CampaignUserAssignment;
use App\Models\QualityForm;
use App\Models\QualityFormVersion;
use App\Models\QualityAttribute;
use App\Models\QualitySubAttribute;
use App\Models\Interaction;
use App\Models\Evaluation;
use App\Models\EvaluationItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // ========================================
        // 1. USUARIOS
        // ========================================

        // Admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@calidad.com'],
            [
                'name' => 'Administrador Sistema',
                'username' => 'admin_sys', // Changed to avoid conflict
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $admin->assignRole('admin');

        // Supervisores
        $supervisor1 = User::firstOrCreate(
            ['email' => 'supervisor1@calidad.com'],
            [
                'name' => 'María García',
                'username' => 'supervisor1_demo',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $supervisor1->assignRole('supervisor');

        $supervisor2 = User::firstOrCreate(
            ['email' => 'supervisor2@calidad.com'],
            [
                'name' => 'Carlos Rodríguez',
                'username' => 'supervisor2_demo',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $supervisor2->assignRole('supervisor');

        // 6. QA Coordinator y Monitors
        $qaCoordinator = User::firstOrCreate(
            ['email' => 'coordinator@calidad.com'],
            [
                'name' => 'Roberto QA',
                'username' => 'coordinator_demo',
                'password' => Hash::make('password')
            ]
        );
        $qaCoordinator->assignRole('qa_coordinator');

        $qaMonitor = User::firstOrCreate(
            ['email' => 'monitor@calidad.com'],
            [
                'name' => 'Lucia Monitor',
                'username' => 'monitor_demo',
                'password' => Hash::make('password'),
                'supervisor_id' => $qaCoordinator->id
            ]
        );
        $qaMonitor->assignRole('qa_monitor');

        // 7. Manager
        $manager = User::firstOrCreate(
            ['email' => 'manager@calidad.com'],
            [
                'name' => 'Director Operaciones',
                'username' => 'manager_demo',
                'password' => Hash::make('password')
            ]
        );
        $manager->assignRole('manager');

        $agents = [];
        $agentData = [
            ['name' => 'Ana López', 'email' => 'ana.lopez@calidad.com'],
            ['name' => 'Luis Martínez', 'email' => 'luis.martinez@calidad.com'],
            ['name' => 'Carmen Sánchez', 'email' => 'carmen.sanchez@calidad.com'],
            ['name' => 'Pedro Ramírez', 'email' => 'pedro.ramirez@calidad.com'],
            ['name' => 'Laura Torres', 'email' => 'laura.torres@calidad.com'],
            ['name' => 'Diego Vargas', 'email' => 'diego.vargas@calidad.com'],
        ];

        foreach ($agentData as $data) {
            $agent = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'username' => explode('@', $data['email'])[0],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );
            $agent->assignRole('agent');
            $agents[] = $agent;
        }

        // ========================================
        // 2. CAMPAÑAS
        // ========================================

        $campaign1 = Campaign::firstOrCreate(
            ['name' => 'Atención al Cliente'],
            [
                'description' => 'Campaña principal de atención y soporte al cliente',
                'is_active' => true,
            ]
        );

        $campaign2 = Campaign::firstOrCreate(
            ['name' => 'Ventas Outbound'],
            [
                'description' => 'Campaña de ventas telefónicas salientes',
                'is_active' => true,
            ]
        );

        $campaign3 = Campaign::firstOrCreate(
            ['name' => 'Retención de Clientes'],
            [
                'description' => 'Campaña para retención y fidelización de clientes',
                'is_active' => true,
            ]
        );

        // ========================================
        // 3. ASIGNACIONES
        // ========================================

        // Asignar Manager a Campaña 1
        \Illuminate\Support\Facades\DB::table('campaign_managers')->updateOrInsert(
            ['campaign_id' => $campaign1->id, 'user_id' => $manager->id],
            ['created_at' => now(), 'updated_at' => now()]
        );

        // Campaña 1: 4 agentes con supervisor 1
        foreach (array_slice($agents, 0, 4) as $agent) {
            CampaignUserAssignment::firstOrCreate([
                'campaign_id' => $campaign1->id,
                'agent_id' => $agent->id,
            ], [
                'supervisor_id' => $supervisor1->id,
                'start_date' => now()->subMonths(3),
                'is_active' => true,
            ]);
        }

        // Campaña 2: 2 agentes con supervisor 2
        foreach (array_slice($agents, 4, 2) as $agent) {
            CampaignUserAssignment::firstOrCreate([
                'campaign_id' => $campaign2->id,
                'agent_id' => $agent->id,
            ], [
                'supervisor_id' => $supervisor2->id,
                'start_date' => now()->subMonths(2),
                'is_active' => true,
            ]);
        }

        // ========================================
        // 4. FICHAS DE CALIDAD
        // ========================================

        // Ficha para Atención al Cliente
        $form1 = QualityForm::firstOrCreate(
            ['name' => 'Ficha Atención al Cliente', 'campaign_id' => $campaign1->id],
            [
                'description' => 'Evaluación integral de llamadas de atención al cliente',
                'created_by' => $admin->id,
            ]
        );

        $version1 = QualityFormVersion::firstOrCreate(
            ['quality_form_id' => $form1->id, 'version_number' => 1],
            [
                'status' => 'published',
                'published_at' => now(),
                'published_by' => $admin->id,
            ]
        );

        // Crear atributos para la ficha
        $this->createAttributesForVersion($version1);

        // Asignar como versión activa
        $campaign1->update(['active_form_version_id' => $version1->id]);

        // Ficha para Ventas
        $form2 = QualityForm::firstOrCreate(
            ['name' => 'Ficha Ventas Telefónicas', 'campaign_id' => $campaign2->id],
            [
                'description' => 'Evaluación de efectividad en ventas',
                'created_by' => $admin->id,
            ]
        );

        $version2 = QualityFormVersion::firstOrCreate(
            ['quality_form_id' => $form2->id, 'version_number' => 1],
            [
                'status' => 'published',
                'published_at' => now(),
                'published_by' => $admin->id,
            ]
        );

        $this->createSalesAttributesForVersion($version2);
        $campaign2->update(['active_form_version_id' => $version2->id]);

        // ========================================
        // 5. INTERACCIONES (TRANSCRIPCIONES)
        // ========================================

        $transcriptSamples = [
            "Agente: Buenos días, gracias por llamar a Servicio al Cliente, mi nombre es %AGENT%, ¿en qué puedo ayudarle?\nCliente: Hola, buenos días. Tengo un problema con mi factura del mes pasado.\nAgente: Entiendo su preocupación. Permítame verificar su cuenta. ¿Me puede proporcionar su número de cliente?\nCliente: Sí, es el 12345678.\nAgente: Perfecto, ya tengo su información. Veo que hay un cargo adicional de $50 que no reconoce, ¿es correcto?\nCliente: Exacto, no sé de dónde salió ese cargo.\nAgente: Déjeme revisar... Efectivamente, parece ser un error de facturación. Procederé a realizar la corrección y el ajuste aparecerá en su próxima factura.\nCliente: Muchas gracias, eso era todo lo que necesitaba.\nAgente: Es un placer ayudarle. ¿Hay algo más en lo que pueda asistirle?\nCliente: No, eso es todo.\nAgente: Gracias por comunicarse con nosotros. Que tenga un excelente día.",

            "Agente: Buenas tardes, bienvenido a Atención al Cliente, soy %AGENT%. ¿Cómo puedo asistirle hoy?\nCliente: Buenas tardes. Quiero hacer un reclamo porque mi pedido llegó incompleto.\nAgente: Lamento mucho escuchar eso. Permítame registrar su reclamo. ¿Cuál es su número de pedido?\nCliente: Es el PED-2024-5678.\nAgente: Gracias. Veo su pedido aquí. ¿Qué artículos faltaron?\nCliente: Faltó una camiseta azul talla M.\nAgente: Entiendo. Voy a procesar el envío del artículo faltante sin costo adicional. Debería recibirlo en 2-3 días hábiles.\nCliente: Perfecto, gracias por la solución rápida.\nAgente: No se preocupe, es nuestro deber. Le enviaré un correo con el número de seguimiento. ¿Desea algo más?\nCliente: No, gracias.\nAgente: Gracias por su paciencia. Que tenga un buen día.",

            "Agente: Hola, gracias por llamar. Mi nombre es %AGENT%, ¿en qué le puedo ayudar?\nCliente: Necesito información sobre los planes de suscripción.\nAgente: Con gusto le explico. Tenemos tres planes: Básico a $29, Premium a $49 y Empresarial a $99 mensuales.\nCliente: ¿Qué incluye el Premium?\nAgente: El plan Premium incluye acceso ilimitado, soporte prioritario 24/7, y 5 usuarios adicionales.\nCliente: Me interesa. ¿Cómo puedo contratarlo?\nAgente: Puedo procesarlo ahora mismo. Solo necesito algunos datos. ¿Desea proceder?\nCliente: Sí, adelante.\nAgente: Excelente. Primero, ¿su nombre completo?\nCliente: Juan Pérez González.\nAgente: Perfecto. Su suscripción ha sido activada. Recibirá un correo con los detalles de acceso.\nCliente: Muchas gracias por la ayuda.\nAgente: Gracias a usted por elegirnos. Que tenga un excelente día.",
        ];

        // Crear interacciones para cada agente en campaña 1
        foreach (array_slice($agents, 0, 4) as $agentIndex => $agent) {
            for ($i = 0; $i < 3; $i++) {
                $transcript = str_replace('%AGENT%', $agent->name, $transcriptSamples[$i % 3]);

                $interaction = Interaction::create([
                    'campaign_id' => $campaign1->id,
                    'agent_id' => $agent->id,
                    'supervisor_id' => $supervisor1->id,
                    'file_name' => "llamada_{$agent->id}_{$i}.txt",
                    'file_path' => "transcripts/llamada_{$agent->id}_{$i}.txt",
                    'transcript_text' => $transcript,
                    'occurred_at' => now()->subDays(rand(1, 30))->subHours(rand(1, 8)),
                    'uploaded_by' => $supervisor1->id,
                    'status' => 'uploaded',
                ]);

                // Crear evaluación para algunas interacciones
                if ($i < 2) {
                    $score = rand(70, 98);
                    $evaluation = Evaluation::create([
                        'interaction_id' => $interaction->id,
                        'campaign_id' => $campaign1->id,
                        'agent_id' => $agent->id,
                        'form_version_id' => $version1->id,
                        'percentage_score' => $score,
                        'status' => $i == 0 ? 'agent_responded' : 'visible_to_agent',
                        'ai_processed_at' => now()->subDays(rand(1, 5)),
                    ]);

                    // Crear items de evaluación
                    foreach ($version1->attributes as $attribute) {
                        foreach ($attribute->subAttributes as $subAttribute) {
                            $isCompliant = rand(0, 100) > 20; // 80% de cumplimiento
                            $score = $isCompliant ? 1 : 0;
                            $effectiveWeight = ($attribute->weight * $subAttribute->weight_percent) / 100;

                            EvaluationItem::create([
                                'evaluation_id' => $evaluation->id,
                                'subattribute_id' => $subAttribute->id,
                                'status' => $isCompliant ? 'compliant' : 'non_compliant',
                                'score' => $score,
                                'max_score' => 1,
                                'weighted_score' => $score * $effectiveWeight,
                                'confidence' => rand(85, 99) / 100,
                                'evidence_quote' => $isCompliant
                                    ? 'El agente cumplió correctamente con este criterio.'
                                    : 'No se encontró evidencia de cumplimiento.',
                            ]);
                        }
                    }
                }
            }
        }

        $this->command->info('✅ Datos de demostración creados exitosamente!');
        $this->command->info('');
        $this->command->info('📧 Credenciales de acceso:');
        $this->command->info('   Admin: admin@calidad.com / password');
        $this->command->info('   Supervisor: supervisor1@calidad.com / password');
        $this->command->info('   Asesor: ana.lopez@calidad.com / password');
    }

    private function createAttributesForVersion(QualityFormVersion $version): void
    {
        if ($version->attributes()->count() > 0)
            return;

        // Atributo 1: Saludo y Protocolo
        $attr1 = QualityAttribute::create([
            'form_version_id' => $version->id,
            'name' => 'Saludo y Protocolo',
            'weight' => 25,
            'concept' => 'Evaluación del saludo inicial y cumplimiento del protocolo de atención',
            'sort_order' => 1,
        ]);

        QualitySubAttribute::create([
            'attribute_id' => $attr1->id,
            'name' => 'Saludo institucional completo',
            'weight_percent' => 40,
            'concept' => 'Incluye nombre del agente, empresa y ofrecimiento de ayuda',
            'is_critical' => true,
            'sort_order' => 1,
        ]);

        QualitySubAttribute::create([
            'attribute_id' => $attr1->id,
            'name' => 'Tono de voz amable',
            'weight_percent' => 30,
            'concept' => 'Voz clara, amable y profesional',
            'is_critical' => false,
            'sort_order' => 2,
        ]);

        QualitySubAttribute::create([
            'attribute_id' => $attr1->id,
            'name' => 'Despedida cordial',
            'weight_percent' => 30,
            'concept' => 'Cierre de llamada con agradecimiento',
            'is_critical' => false,
            'sort_order' => 3,
        ]);

        // Atributo 2: Manejo de la Consulta
        $attr2 = QualityAttribute::create([
            'form_version_id' => $version->id,
            'name' => 'Manejo de la Consulta',
            'weight' => 35,
            'concept' => 'Capacidad para resolver efectivamente la consulta del cliente',
            'sort_order' => 2,
        ]);

        QualitySubAttribute::create([
            'attribute_id' => $attr2->id,
            'name' => 'Identificación correcta del problema',
            'weight_percent' => 35,
            'concept' => 'El agente comprende y valida el problema del cliente',
            'is_critical' => true,
            'sort_order' => 1,
        ]);

        QualitySubAttribute::create([
            'attribute_id' => $attr2->id,
            'name' => 'Solución brindada',
            'weight_percent' => 40,
            'concept' => 'Se ofrece una solución efectiva o escalamiento apropiado',
            'is_critical' => true,
            'sort_order' => 2,
        ]);

        QualitySubAttribute::create([
            'attribute_id' => $attr2->id,
            'name' => 'Confirmación con el cliente',
            'weight_percent' => 25,
            'concept' => 'Se confirma que el cliente está satisfecho con la solución',
            'is_critical' => false,
            'sort_order' => 3,
        ]);

        // Atributo 3: Comunicación
        $attr3 = QualityAttribute::create([
            'form_version_id' => $version->id,
            'name' => 'Comunicación',
            'weight' => 25,
            'concept' => 'Calidad de la comunicación durante la interacción',
            'sort_order' => 3,
        ]);

        QualitySubAttribute::create([
            'attribute_id' => $attr3->id,
            'name' => 'Escucha activa',
            'weight_percent' => 50,
            'concept' => 'El agente demuestra atención y comprensión',
            'is_critical' => false,
            'sort_order' => 1,
        ]);

        QualitySubAttribute::create([
            'attribute_id' => $attr3->id,
            'name' => 'Lenguaje claro y profesional',
            'weight_percent' => 50,
            'concept' => 'Uso correcto del lenguaje, sin muletillas ni jerga',
            'is_critical' => false,
            'sort_order' => 2,
        ]);

        // Atributo 4: Procedimientos
        $attr4 = QualityAttribute::create([
            'form_version_id' => $version->id,
            'name' => 'Procedimientos',
            'weight' => 15,
            'concept' => 'Cumplimiento de procedimientos y normativas',
            'sort_order' => 4,
        ]);

        QualitySubAttribute::create([
            'attribute_id' => $attr4->id,
            'name' => 'Verificación de identidad',
            'weight_percent' => 50,
            'concept' => 'Se solicitan datos de verificación cuando corresponde',
            'is_critical' => true,
            'sort_order' => 1,
        ]);

        QualitySubAttribute::create([
            'attribute_id' => $attr4->id,
            'name' => 'Registro correcto en sistema',
            'weight_percent' => 50,
            'concept' => 'Se documenta la interacción correctamente',
            'is_critical' => false,
            'sort_order' => 2,
        ]);
    }

    private function createSalesAttributesForVersion(QualityFormVersion $version): void
    {
        if ($version->attributes()->count() > 0)
            return;

        // Atributo 1: Apertura de Venta
        $attr1 = QualityAttribute::create([
            'form_version_id' => $version->id,
            'name' => 'Apertura de Venta',
            'weight' => 20,
            'concept' => 'Técnica de apertura y generación de rapport',
            'sort_order' => 1,
        ]);

        QualitySubAttribute::create([
            'attribute_id' => $attr1->id,
            'name' => 'Presentación efectiva',
            'weight_percent' => 50,
            'concept' => 'Presentación clara del motivo de la llamada',
            'is_critical' => true,
            'sort_order' => 1,
        ]);

        QualitySubAttribute::create([
            'attribute_id' => $attr1->id,
            'name' => 'Generación de interés',
            'weight_percent' => 50,
            'concept' => 'Logra captar la atención del cliente',
            'is_critical' => false,
            'sort_order' => 2,
        ]);

        // Atributo 2: Técnica de Ventas
        $attr2 = QualityAttribute::create([
            'form_version_id' => $version->id,
            'name' => 'Técnica de Ventas',
            'weight' => 40,
            'concept' => 'Aplicación de técnicas de venta consultiva',
            'sort_order' => 2,
        ]);

        QualitySubAttribute::create([
            'attribute_id' => $attr2->id,
            'name' => 'Identificación de necesidades',
            'weight_percent' => 30,
            'concept' => 'Realiza preguntas para identificar necesidades',
            'is_critical' => true,
            'sort_order' => 1,
        ]);

        QualitySubAttribute::create([
            'attribute_id' => $attr2->id,
            'name' => 'Presentación de beneficios',
            'weight_percent' => 40,
            'concept' => 'Presenta beneficios relevantes para el cliente',
            'is_critical' => true,
            'sort_order' => 2,
        ]);

        QualitySubAttribute::create([
            'attribute_id' => $attr2->id,
            'name' => 'Manejo de objeciones',
            'weight_percent' => 30,
            'concept' => 'Responde adecuadamente a las objeciones',
            'is_critical' => false,
            'sort_order' => 3,
        ]);

        // Atributo 3: Cierre
        $attr3 = QualityAttribute::create([
            'form_version_id' => $version->id,
            'name' => 'Cierre de Venta',
            'weight' => 25,
            'concept' => 'Técnica de cierre y concreción de la venta',
            'sort_order' => 3,
        ]);

        QualitySubAttribute::create([
            'attribute_id' => $attr3->id,
            'name' => 'Intento de cierre',
            'weight_percent' => 60,
            'concept' => 'Realiza al menos un intento de cierre',
            'is_critical' => true,
            'sort_order' => 1,
        ]);

        QualitySubAttribute::create([
            'attribute_id' => $attr3->id,
            'name' => 'Confirmación de datos',
            'weight_percent' => 40,
            'concept' => 'Confirma datos del cliente para la venta',
            'is_critical' => false,
            'sort_order' => 2,
        ]);

        // Atributo 4: Cumplimiento Normativo
        $attr4 = QualityAttribute::create([
            'form_version_id' => $version->id,
            'name' => 'Cumplimiento Normativo',
            'weight' => 15,
            'concept' => 'Cumplimiento de normativas de protección al consumidor',
            'sort_order' => 4,
        ]);

        QualitySubAttribute::create([
            'attribute_id' => $attr4->id,
            'name' => 'Información clara de precios',
            'weight_percent' => 50,
            'concept' => 'Informa claramente precios y condiciones',
            'is_critical' => true,
            'sort_order' => 1,
        ]);

        QualitySubAttribute::create([
            'attribute_id' => $attr4->id,
            'name' => 'Respeto por el cliente',
            'weight_percent' => 50,
            'concept' => 'No presiona excesivamente al cliente',
            'is_critical' => false,
            'sort_order' => 2,
        ]);
    }
}
