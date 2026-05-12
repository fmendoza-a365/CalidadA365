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
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class AlfinDemoSeeder extends Seeder
{
    public function run(): void
    {
        // ========================================
        // 1. USUARIOS (ALFIN)
        // ========================================

        // Manager Alfin
        $managerAlfin = User::firstOrCreate(
            ['email' => 'gerente@alfinbanco.com'],
            [
                'name' => 'Gerente Alfin',
                'username' => 'gerente_alfin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $managerAlfin->assignRole('manager');

        // Supervisor Alfin
        $supervisorAlfin = User::firstOrCreate(
            ['email' => 'supervisor@alfinbanco.com'],
            [
                'name' => 'Supervisor Alfin',
                'username' => 'supervisor_alfin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $supervisorAlfin->assignRole('supervisor');

        // Agentes Alfin
        $agentsAlfin = [];
        $agentData = [
            ['name' => 'Jorge Mendoza', 'email' => 'jmendoza@alfinbanco.com'],
            ['name' => 'Sandra Quispe', 'email' => 'squispe@alfinbanco.com'],
            ['name' => 'Miguel Rojas', 'email' => 'mrojas@alfinbanco.com'],
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
            $agentsAlfin[] = $agent;
        }

        // ========================================
        // 2. CAMPAÑA ALFIN
        // ========================================

        $campaignAlfin = Campaign::firstOrCreate(
            ['name' => 'Alfin - Cobranzas Tarjetas'],
            [
                'description' => 'Campaña de gestión de cobranza temprana y tardía para tarjetas de crédito Alfin',
                'is_active' => true,
            ]
        );

        // ========================================
        // 3. ASIGNACIONES
        // ========================================

        // Asignar Manager
        DB::table('campaign_managers')->updateOrInsert(
            ['campaign_id' => $campaignAlfin->id, 'user_id' => $managerAlfin->id],
            ['created_at' => now(), 'updated_at' => now()]
        );

        // Asignar Agentes
        foreach ($agentsAlfin as $agent) {
            CampaignUserAssignment::firstOrCreate([
                'campaign_id' => $campaignAlfin->id,
                'agent_id' => $agent->id,
            ], [
                'supervisor_id' => $supervisorAlfin->id,
                'start_date' => now()->subMonths(1),
                'is_active' => true,
            ]);
        }

        // ========================================
        // 4. FICHA DE CALIDAD ALFIN
        // ========================================

        $formAlfin = QualityForm::firstOrCreate(
            ['name' => 'Ficha de Cobranza Alfin', 'campaign_id' => $campaignAlfin->id],
            [
                'description' => 'Evaluación estándar para ejecutivos de cobranza de Banco Alfin',
                'created_by' => $managerAlfin->id,
            ]
        );

        $formAlfin->update([
            'operational_context_markdown' => <<<'MARKDOWN'
## Productos y condiciones
- Tarjeta de crédito Alfin: deuda vencida puede generar cargos por morosidad e impacto en centrales de riesgo.
- Préstamo personal Alfin: si el cliente no puede pagar el total, se debe ofrecer refinanciamiento o derivación a soluciones de pago.
- Compromiso de pago: debe quedar claro monto, fecha y canal de pago.

## Speech obligatorio de cobranza
El asesor debe mencionar de forma exacta o muy semejante:
"Me comunico de Alfin Banco, mi nombre es [nombre del asesor]".

También debe explicar el motivo de llamada indicando deuda, atraso o monto vencido. Si corresponde, debe validar que habla con el titular.

## Reglas de evaluación operativa
- Si el asesor menciona Infocorp, centrales de riesgo, cargos por mora o intereses, debe hacerlo sin tono amenazante.
- Si el cliente indica dificultad económica, el asesor debe mostrar empatía antes de negociar.
- Si se acuerda un pago parcial, debe informar que puede quedar saldo pendiente.
- No debe inventar beneficios, descuentos o condonaciones no autorizadas.
MARKDOWN,
        ]);

        $versionAlfin = QualityFormVersion::firstOrCreate(
            ['quality_form_id' => $formAlfin->id, 'version_number' => 1],
            [
                'status' => 'published',
                'published_at' => now(),
                'published_by' => $managerAlfin->id,
            ]
        );

        $this->createAlfinAttributes($versionAlfin);
        $campaignAlfin->update(['active_form_version_id' => $versionAlfin->id]);

        // ========================================
        // 5. INTERACCIONES (TRANSCRIPCIONES ALFIN)
        // ========================================

        $transcriptSamples = [
            "Agente: Buenos días, me comunico de Alfin Banco, mi nombre es %AGENT%. ¿Hablo con el señor Juan Pérez?\nCliente: Sí, él habla. ¿Qué desea?\nAgente: El motivo de mi llamada es porque verificamos en el sistema que presenta un atraso de 15 días en su tarjeta de crédito Alfin. El monto vencido es de 250 soles. ¿Tuvimos algún inconveniente para realizar el pago?\nCliente: Sí, la verdad me he retrasado por unos gastos médicos imprevistos.\nAgente: Entiendo su situación, señor Pérez. Sin embargo, es importante regularizar la cuenta para evitar el reporte negativo en Infocorp y cargos por morosidad. ¿Podría realizar un pago a cuenta el día de hoy o mañana?\nCliente: Podría depositar 100 soles mañana.\nAgente: De acuerdo, registraré un compromiso de pago por 100 soles para mañana. Puede acercarse a cualquier agente o por la app de Alfin. Le recuerdo que quedará un saldo pendiente que seguirá generando intereses. ¿Queda clara la información?\nCliente: Sí, claro.\nAgente: Perfecto. Muchas gracias por atender mi llamada. Que tenga un buen día de parte de Alfin Banco.",
            
            "Agente: Hola, muy buenas tardes. Le saluda %AGENT% de Alfin Banco. ¿Hablo con la titular María González?\nCliente: Sí, soy yo. Dime.\nAgente: Señora María, la llamamos por su deuda pendiente de 800 soles en su préstamo personal. Ya tiene 30 días de retraso. ¿Cuándo tiene pensado cancelar?\nCliente: Ay joven, me he quedado sin trabajo, no sé cómo voy a hacer.\nAgente: Comprendo su difícil situación. Mire, Alfin Banco tiene opciones de refinanciamiento. Podemos fraccionar la deuda en cuotas más pequeñas para que no afecte tanto su liquidez. ¿Le interesaría evaluar esta opción?\nCliente: Sí, por favor, porque ahora mismo no tengo los 800.\nAgente: Perfecto, voy a derivarla al área de soluciones de pago para que le armen un cronograma nuevo según sus posibilidades. Por favor, no corte la línea que la transferiré.\nCliente: Está bien, espero.\nAgente: Muchas gracias por su tiempo y confianza en Alfin Banco."
        ];

        foreach ($agentsAlfin as $index => $agent) {
            $transcript = str_replace('%AGENT%', $agent->name, $transcriptSamples[$index % 2]);

            Interaction::create([
                'campaign_id' => $campaignAlfin->id,
                'agent_id' => $agent->id,
                'supervisor_id' => $supervisorAlfin->id,
                'file_name' => "alfin_llamada_{$agent->id}.txt",
                'file_path' => "transcripts/alfin_llamada_{$agent->id}.txt",
                'transcript_text' => $transcript,
                'occurred_at' => now()->subDays(rand(1, 5))->subHours(rand(1, 8)),
                'uploaded_by' => $supervisorAlfin->id,
                'status' => 'uploaded',
            ]);
        }

        $this->command->info('✅ Datos de demostración de ALFIN creados exitosamente!');
        $this->command->info('📧 Credenciales ALFIN:');
        $this->command->info('   Gerente: gerente@alfinbanco.com / password');
        $this->command->info('   Supervisor: supervisor@alfinbanco.com / password');
        $this->command->info('   Agente: jmendoza@alfinbanco.com / password');
    }

    private function createAlfinAttributes(QualityFormVersion $version): void
    {
        if ($version->attributes()->count() > 0) return;

        // Atributo 1: Protocolo de Cobranza
        $attr1 = QualityAttribute::create([
            'form_version_id' => $version->id,
            'name' => 'Protocolo Inicial',
            'weight' => 20,
            'concept' => 'Identificación y motivo de llamada',
            'sort_order' => 1,
        ]);

        QualitySubAttribute::create([
            'attribute_id' => $attr1->id,
            'name' => 'Saludo corporativo Alfin',
            'weight_percent' => 50,
            'concept' => 'Menciona que llama de Alfin Banco y su nombre',
            'is_critical' => true,
            'sort_order' => 1,
        ]);

        QualitySubAttribute::create([
            'attribute_id' => $attr1->id,
            'name' => 'Verificación de titularidad',
            'weight_percent' => 50,
            'concept' => 'Se asegura de hablar con el titular de la cuenta',
            'is_critical' => true,
            'sort_order' => 2,
        ]);

        // Atributo 2: Gestión de Cobranza
        $attr2 = QualityAttribute::create([
            'form_version_id' => $version->id,
            'name' => 'Gestión de la Deuda',
            'weight' => 50,
            'concept' => 'Negociación y manejo de la morosidad',
            'sort_order' => 2,
        ]);

        QualitySubAttribute::create([
            'attribute_id' => $attr2->id,
            'name' => 'Indagación del motivo de no pago',
            'weight_percent' => 30,
            'concept' => 'Pregunta la razón del retraso de forma respetuosa',
            'is_critical' => false,
            'sort_order' => 1,
        ]);

        QualitySubAttribute::create([
            'attribute_id' => $attr2->id,
            'name' => 'Mención de consecuencias',
            'weight_percent' => 40,
            'concept' => 'Informa sobre reporte a centrales de riesgo (Infocorp) o cargos',
            'is_critical' => true,
            'sort_order' => 2,
        ]);

        QualitySubAttribute::create([
            'attribute_id' => $attr2->id,
            'name' => 'Negociación o Compromiso',
            'weight_percent' => 30,
            'concept' => 'Logra un compromiso de pago o deriva a soluciones',
            'is_critical' => true,
            'sort_order' => 3,
        ]);

        // Atributo 3: Despedida
        $attr3 = QualityAttribute::create([
            'form_version_id' => $version->id,
            'name' => 'Despedida',
            'weight' => 30,
            'concept' => 'Cierre de la llamada',
            'sort_order' => 3,
        ]);

        QualitySubAttribute::create([
            'attribute_id' => $attr3->id,
            'name' => 'Cierre corporativo',
            'weight_percent' => 100,
            'concept' => 'Se despide amablemente agradeciendo en nombre de Alfin Banco',
            'is_critical' => false,
            'sort_order' => 1,
        ]);
    }
}
