<?php

namespace Database\Seeders;

use App\Models\AgentResponse;
use App\Models\Campaign;
use App\Models\CampaignUserAssignment;
use App\Models\DisputeResolution;
use App\Models\Evaluation;
use App\Models\EvaluationAuditEvent;
use App\Models\EvaluationItem;
use App\Models\Interaction;
use App\Models\QualityAttribute;
use App\Models\QualityForm;
use App\Models\QualityFormVersion;
use App\Models\QualitySubAttribute;
use App\Models\User;
use App\Support\AiSettings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CurrentFunctionalityDemoSeeder extends Seeder
{
    private const CAMPAIGN_NAME = 'Demo QA365 - Funcionalidad Actual';

    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
        ]);

        $users = $this->demoUsers();
        $campaign = $this->campaign($users);
        $this->resetDemoData($campaign);
        $version = $this->qualityFormVersion($campaign, $users['admin']);
        $subAttributes = $version->attributes()->with('subAttributes')->get()->flatMap->subAttributes->values();

        $this->createInteractionsAndEvaluations($campaign, $version, $subAttributes, $users);

        $this->command?->info('Datos demo de funcionalidad actual creados.');
        $this->command?->line('');
        $this->command?->line('Credenciales demo:');
        $this->command?->line('  Admin: demo.admin@qa365.local / password');
        $this->command?->line('  QA Manager: demo.qamanager@qa365.local / password');
        $this->command?->line('  Monitor: demo.monitor@qa365.local / password');
        $this->command?->line('  Supervisor: demo.supervisor@qa365.local / password');
        $this->command?->line('  Agente: demo.agent1@qa365.local / password');
    }

    private function demoUsers(): array
    {
        $admin = $this->user('demo.admin@qa365.local', 'Demo Admin QA365', 'demo_admin', 'admin');
        $qaManager = $this->user('demo.qamanager@qa365.local', 'Demo QA Manager', 'demo_qa_manager', 'qa_manager');
        $coordinator = $this->user('demo.coordinator@qa365.local', 'Demo Coordinadora QA', 'demo_coordinator', 'qa_coordinator');
        $monitor = $this->user('demo.monitor@qa365.local', 'Demo Monitor QA', 'demo_monitor', 'qa_monitor');
        $manager = $this->user('demo.manager@qa365.local', 'Demo Gerente Operaciones', 'demo_manager', 'manager');
        $supervisor = $this->user('demo.supervisor@qa365.local', 'Demo Supervisor', 'demo_supervisor', 'supervisor');
        $agent1 = $this->user('demo.agent1@qa365.local', 'Demo Agente Alto Riesgo', 'demo_agent1', 'agent');
        $agent2 = $this->user('demo.agent2@qa365.local', 'Demo Agente Consistente', 'demo_agent2', 'agent');
        $agent3 = $this->user('demo.agent3@qa365.local', 'Demo Agente En Calibracion', 'demo_agent3', 'agent');

        $monitor->forceFill(['supervisor_id' => $coordinator->id])->save();

        return compact('admin', 'qaManager', 'coordinator', 'monitor', 'manager', 'supervisor', 'agent1', 'agent2', 'agent3');
    }

    private function user(string $email, string $name, string $username, string $role): User
    {
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'username' => $username,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $user->syncRoles([$role]);

        return $user;
    }

    private function campaign(array $users): Campaign
    {
        $campaign = Campaign::updateOrCreate(
            ['name' => self::CAMPAIGN_NAME],
            [
                'description' => 'Campaña de ejemplo para revisar bandeja, evaluaciones, calibración IA y disputas.',
                'is_active' => true,
                'target_quality' => 85,
                'target_aht' => 420,
                'type' => 'demo',
                'color' => '#4f46e5',
            ]
        );

        foreach (['manager', 'qaManager', 'coordinator', 'monitor'] as $key) {
            DB::table('campaign_managers')->updateOrInsert(
                ['campaign_id' => $campaign->id, 'user_id' => $users[$key]->id],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }

        foreach (['agent1', 'agent2', 'agent3'] as $agentKey) {
            CampaignUserAssignment::updateOrCreate(
                [
                    'campaign_id' => $campaign->id,
                    'agent_id' => $users[$agentKey]->id,
                ],
                [
                    'supervisor_id' => $users['supervisor']->id,
                    'start_date' => now()->subMonths(2)->toDateString(),
                    'is_active' => true,
                ]
            );
        }

        return $campaign;
    }

    private function resetDemoData(Campaign $campaign): void
    {
        $interactionIds = Interaction::where('campaign_id', $campaign->id)->pluck('id');
        $evaluationIds = Evaluation::whereIn('interaction_id', $interactionIds)->pluck('id');

        if ($evaluationIds->isNotEmpty()) {
            DisputeResolution::whereIn('evaluation_id', $evaluationIds)->delete();
            AgentResponse::whereIn('evaluation_id', $evaluationIds)->delete();
            EvaluationAuditEvent::whereIn('evaluation_id', $evaluationIds)->delete();
            EvaluationItem::whereIn('evaluation_id', $evaluationIds)->delete();
            Evaluation::whereIn('id', $evaluationIds)->delete();
        }

        Interaction::whereIn('id', $interactionIds)->delete();
    }

    private function qualityFormVersion(Campaign $campaign, User $admin): QualityFormVersion
    {
        $form = QualityForm::updateOrCreate(
            [
                'campaign_id' => $campaign->id,
                'name' => 'Ficha Demo Omnicanal QA365',
            ],
            [
                'description' => 'Ficha de ejemplo para evaluar atención, solución, cumplimiento y cierre.',
                'created_by' => $admin->id,
                'operational_context_markdown' => "Usar esta ficha demo para validar el flujo actual de QA365.\n\n- Penalizar falta de verificación de identidad.\n- Marcar como crítico si el asesor entrega información incorrecta.\n- Considerar evidencia textual de la transcripción.",
            ]
        );

        $version = QualityFormVersion::updateOrCreate(
            [
                'quality_form_id' => $form->id,
                'version_number' => 1,
            ],
            [
                'status' => 'published',
                'is_active' => true,
                'published_at' => now()->subDays(15),
                'published_by' => $admin->id,
            ]
        );

        $this->resetFormCriteria($version);
        $campaign->update(['active_form_version_id' => $version->id]);

        return $version->fresh('attributes.subAttributes');
    }

    private function resetFormCriteria(QualityFormVersion $version): void
    {
        $attributeIds = $version->attributes()->pluck('id');
        QualitySubAttribute::whereIn('attribute_id', $attributeIds)->delete();
        QualityAttribute::whereIn('id', $attributeIds)->delete();

        $criteria = [
            [
                'name' => 'Apertura y protocolo',
                'weight' => 20,
                'concept' => 'Valida saludo, presentación y control inicial de la interacción.',
                'items' => [
                    ['Saludo institucional completo', 50, true],
                    ['Verificación de identidad cuando corresponde', 50, true],
                ],
            ],
            [
                'name' => 'Diagnóstico',
                'weight' => 25,
                'concept' => 'Evalúa escucha activa, preguntas y entendimiento del motivo de contacto.',
                'items' => [
                    ['Explora la necesidad del cliente', 50, false],
                    ['Confirma entendimiento antes de resolver', 50, false],
                ],
            ],
            [
                'name' => 'Solución',
                'weight' => 35,
                'concept' => 'Mide precisión de la respuesta y claridad de los próximos pasos.',
                'items' => [
                    ['Solución correcta y completa', 60, true],
                    ['Explica plazos y próximos pasos', 40, false],
                ],
            ],
            [
                'name' => 'Cierre y registro',
                'weight' => 20,
                'concept' => 'Controla confirmación final y documentación interna.',
                'items' => [
                    ['Confirma satisfacción del cliente', 50, false],
                    ['Registra correctamente la gestión', 50, false],
                ],
            ],
        ];

        foreach ($criteria as $index => $attributeData) {
            $attribute = QualityAttribute::create([
                'form_version_id' => $version->id,
                'name' => $attributeData['name'],
                'weight' => $attributeData['weight'],
                'concept' => $attributeData['concept'],
                'sort_order' => $index + 1,
            ]);

            foreach ($attributeData['items'] as $itemIndex => [$name, $weight, $critical]) {
                QualitySubAttribute::create([
                    'attribute_id' => $attribute->id,
                    'name' => $name,
                    'weight_percent' => $weight,
                    'concept' => "Criterio demo: {$name}.",
                    'guidelines' => 'Validar contra evidencia explícita en la transcripción.',
                    'is_critical' => $critical,
                    'sort_order' => $itemIndex + 1,
                ]);
            }
        }
    }

    private function createInteractionsAndEvaluations(Campaign $campaign, QualityFormVersion $version, $subAttributes, array $users): void
    {
        $scenarios = [
            ['title' => 'IA pendiente en cola', 'agent' => 'agent1', 'type' => 'ai', 'status' => Evaluation::STATUS_PENDING_AI, 'score' => null, 'days' => 0, 'provider' => null, 'model' => null],
            ['title' => 'IA procesando', 'agent' => 'agent2', 'type' => 'ai', 'status' => Evaluation::STATUS_AI_PROCESSING, 'score' => null, 'days' => 0, 'provider' => 'gemini', 'model' => 'gemini-2.5-flash'],
            ['title' => 'IA fallida por respuesta inválida', 'agent' => 'agent1', 'type' => 'ai', 'status' => Evaluation::STATUS_AI_FAILED, 'score' => null, 'days' => 1, 'provider' => 'openai', 'model' => 'gpt-4o-mini'],
            ['title' => 'Revisión monitor pendiente', 'agent' => 'agent2', 'type' => 'ai', 'status' => Evaluation::STATUS_PENDING_MONITOR_REVIEW, 'score' => 88, 'days' => 2, 'provider' => 'gemini', 'model' => 'gemini-2.5-flash'],
            ['title' => 'Reanálisis solicitado', 'agent' => 'agent1', 'type' => 'ai', 'status' => Evaluation::STATUS_AI_REANALYSIS_REQUESTED, 'score' => 64, 'days' => 3, 'provider' => 'claude', 'model' => 'claude-3-5-sonnet-20241022'],
            ['title' => 'Publicada al asesor', 'agent' => 'agent2', 'type' => 'manual', 'status' => Evaluation::STATUS_PUBLISHED_TO_AGENT, 'score' => 91, 'days' => 4, 'provider' => null, 'model' => null],
            ['title' => 'Aceptada por asesor', 'agent' => 'agent2', 'type' => 'manual', 'status' => Evaluation::STATUS_AGENT_ACCEPTED, 'score' => 86, 'days' => 5, 'provider' => null, 'model' => null],
            ['title' => 'Disputada por asesor', 'agent' => 'agent1', 'type' => 'manual', 'status' => Evaluation::STATUS_AGENT_DISPUTED, 'score' => 58, 'days' => 6, 'provider' => null, 'model' => null],
            ['title' => 'Disputa resuelta', 'agent' => 'agent1', 'type' => 'manual', 'status' => Evaluation::STATUS_DISPUTE_RESOLVED, 'score' => 75, 'days' => 7, 'provider' => null, 'model' => null],
            ['title' => 'Evaluación cerrada', 'agent' => 'agent2', 'type' => 'manual', 'status' => Evaluation::STATUS_CLOSED, 'score' => 93, 'days' => 8, 'provider' => null, 'model' => null],
        ];

        foreach ($scenarios as $scenario) {
            $interaction = $this->interaction($campaign, $users[$scenario['agent']], $users['supervisor'], $users['monitor'], $scenario['title'], $scenario['days']);
            $this->evaluation($interaction, $version, $subAttributes, $users, $scenario);
        }

        $audioInteraction = $this->audioInteraction(
            $campaign,
            $users['agent2'],
            $users['supervisor'],
            $users['monitor'],
            'Audio demo transcrito con sentimiento',
            1
        );
        $this->evaluation($audioInteraction, $version, $subAttributes, $users, [
            'title' => 'Audio demo transcrito con sentimiento',
            'agent' => 'agent2',
            'type' => 'ai',
            'status' => Evaluation::STATUS_PENDING_MONITOR_REVIEW,
            'score' => 89,
            'days' => 1,
            'provider' => 'gemini',
            'model' => 'gemini-2.5-flash',
        ]);

        $this->calibrationPair($campaign, $version, $subAttributes, $users);
    }

    private function interaction(Campaign $campaign, User $agent, User $supervisor, User $uploader, string $title, int $daysAgo): Interaction
    {
        $createdAt = now()->subDays($daysAgo)->subHours(2);
        $slug = Str::slug($title).'-'.$agent->id;

        $interaction = Interaction::create([
            'campaign_id' => $campaign->id,
            'agent_id' => $agent->id,
            'supervisor_id' => $supervisor->id,
            'occurred_at' => $createdAt->copy()->subHours(3),
            'uploaded_by' => $uploader->id,
            'file_path' => "demo/{$slug}.txt",
            'file_name' => "{$slug}.txt",
            'call_sn' => 'SN-DEMO-'.str_pad((string) ($daysAgo + 1), 4, '0', STR_PAD_LEFT),
            'external_id' => 'CRM-DEMO-'.str_pad((string) ($daysAgo + 100), 5, '0', STR_PAD_LEFT),
            'source_type' => 'text',
            'channel' => $daysAgo % 2 === 0 ? 'call' : 'whatsapp',
            'direction' => $daysAgo % 2 === 0 ? 'inbound' : 'outbound',
            'contact_reason' => $title,
            'outcome' => $daysAgo % 3 === 0 ? 'escalated' : 'resolved',
            'customer_reference' => '***'.str_pad((string) (1200 + $daysAgo), 4, '0', STR_PAD_LEFT),
            'queue_name' => 'Soporte Demo',
            'product_name' => 'Servicio QA365',
            'priority' => $daysAgo % 4 === 0 ? 'high' : 'normal',
            'transcription_status' => 'completed',
            'transcript_text' => $this->transcript($agent->name, $title),
            'status' => 'scored',
            'metadata' => [
                'demo' => true,
                'scenario' => $title,
                'upload' => [
                    'language' => 'es',
                    'tags' => ['demo', 'calidad', Str::slug($title)],
                    'origin' => 'manual_upload',
                    'diarization_mode' => 'auto',
                    'analysis_options' => [
                        'emotion' => true,
                        'critical_compliance' => true,
                    ],
                    'ai_context' => 'Interacción demo para validar metadatos operativos.',
                ],
            ],
            'quality_form_id' => $campaign->forms()->where('name', 'Ficha Demo Omnicanal QA365')->value('id'),
        ]);

        $interaction->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

        return $interaction;
    }

    private function audioInteraction(Campaign $campaign, User $agent, User $supervisor, User $uploader, string $title, int $daysAgo): Interaction
    {
        $createdAt = now()->subDays($daysAgo)->subHours(1);
        $slug = Str::slug($title).'-'.$agent->id;
        $path = "demo/{$slug}.wav";

        Storage::disk(config('filesystems.default', 'local'))->put($path, $this->demoWavBytes());

        $interaction = Interaction::create([
            'campaign_id' => $campaign->id,
            'agent_id' => $agent->id,
            'supervisor_id' => $supervisor->id,
            'occurred_at' => $createdAt->copy()->subHours(3),
            'uploaded_by' => $uploader->id,
            'file_path' => $path,
            'file_name' => "{$slug}.wav",
            'call_sn' => 'SN-AUDIO-2026-001',
            'external_id' => 'GENESYS-AUD-2026-001',
            'source_type' => 'audio',
            'channel' => 'call',
            'direction' => 'inbound',
            'audio_duration' => 94,
            'contact_reason' => 'Solicitud pendiente desde ayer',
            'outcome' => 'resolved',
            'customer_reference' => '***2026',
            'queue_name' => 'Soporte Prioritario',
            'product_name' => 'Activación de servicio',
            'priority' => 'complaint',
            'transcription_status' => 'completed',
            'transcript_text' => $this->audioTranscript($agent->name, $title),
            'status' => 'scored',
            'metadata' => [
                'demo' => true,
                'scenario' => $title,
                'upload' => [
                    'language' => 'es',
                    'tags' => ['audio', 'reclamo', 'sentimiento'],
                    'origin' => 'manual_upload',
                    'diarization_mode' => 'auto',
                    'analysis_options' => [
                        'emotion' => true,
                        'critical_compliance' => true,
                    ],
                    'ai_context' => 'Audio demo con cliente preocupado al inicio y satisfecho al cierre.',
                ],
                'sentiment' => [
                    'overall' => 'positivo',
                    'summary' => 'Cliente inicialmente preocupado, pero termina satisfecho por claridad y solución.',
                    'agent' => [
                        'score' => 0.7,
                        'tone' => 'Calmo, claro y resolutivo.',
                    ],
                    'client' => [
                        'score' => 0.4,
                        'tone' => 'Preocupado al inicio, conforme al cierre.',
                        'satisfaction' => 'satisfecho',
                    ],
                ],
                'sentiment_segments' => $this->audioSentimentSegments(),
            ],
            'quality_form_id' => $campaign->forms()->where('name', 'Ficha Demo Omnicanal QA365')->value('id'),
        ]);

        $interaction->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

        return $interaction;
    }

    private function transcript(string $agentName, string $title): string
    {
        return "Escenario demo: {$title}\n\nAgente: Buen día, le atiende {$agentName} del equipo de soporte. ¿Con quién tengo el gusto?\nCliente: Soy Laura Pérez. Tengo un inconveniente con mi servicio y necesito una solución.\nAgente: Gracias, Laura. Validaré sus datos y revisaré el caso para darle una respuesta clara.\nCliente: Perfecto, necesito saber qué pasó y cuándo se resolverá.\nAgente: Detecto una incidencia en la activación. Haré el ajuste ahora y quedará regularizado en un plazo máximo de 24 horas.\nCliente: ¿Me llegará una confirmación?\nAgente: Sí, recibirá un correo con el número de gestión y el detalle de los próximos pasos.\nCliente: Gracias por la ayuda.\nAgente: Gracias por contactarnos. ¿Hay algo adicional en lo que pueda ayudarle?";
    }

    private function audioTranscript(string $agentName, string $title): string
    {
        return "Contexto: {$title}\n[00:00] Agente: Buen día, le atiende {$agentName}. ¿Me confirma su nombre para validar la atención?\n[00:08] Cliente: Soy Laura Pérez. Estoy llamando porque mi solicitud aparece como pendiente desde ayer.\n[00:17] Agente: Gracias, Laura. Voy a revisar el historial y confirmar el estado antes de darle una respuesta.\n[00:29] Cliente: Me preocupa porque necesito que quede activo hoy.\n[00:36] Agente: Entiendo la urgencia. Ya identifiqué que faltaba una validación interna y la voy a registrar en este momento.\n[00:51] Cliente: ¿Eso significa que no tengo que volver a llamar?\n[00:56] Agente: Correcto. La gestión queda registrada con prioridad y recibirá confirmación por correo dentro de las próximas dos horas.\n[01:08] Cliente: Perfecto, muchas gracias por explicarlo.\n[01:13] Agente: Gracias a usted. Antes de finalizar, confirmo que el número de gestión es AUD-2026-001.";
    }

    private function audioSentimentSegments(): array
    {
        return [
            ['index' => 0, 'start' => 0, 'end' => 8, 'sentiment' => 'positivo', 'emotion' => 'calma', 'score' => 0.55, 'intensity' => 48],
            ['index' => 1, 'start' => 8, 'end' => 17, 'sentiment' => 'mixto', 'emotion' => 'preocupacion', 'score' => -0.15, 'intensity' => 68],
            ['index' => 2, 'start' => 17, 'end' => 29, 'sentiment' => 'positivo', 'emotion' => 'confianza', 'score' => 0.42, 'intensity' => 58],
            ['index' => 3, 'start' => 29, 'end' => 36, 'sentiment' => 'mixto', 'emotion' => 'urgencia', 'score' => -0.25, 'intensity' => 76],
            ['index' => 4, 'start' => 36, 'end' => 51, 'sentiment' => 'positivo', 'emotion' => 'resolucion', 'score' => 0.62, 'intensity' => 72],
            ['index' => 5, 'start' => 51, 'end' => 56, 'sentiment' => 'neutro', 'emotion' => 'validacion', 'score' => 0.05, 'intensity' => 46],
            ['index' => 6, 'start' => 56, 'end' => 68, 'sentiment' => 'positivo', 'emotion' => 'certeza', 'score' => 0.74, 'intensity' => 64],
            ['index' => 7, 'start' => 68, 'end' => 73, 'sentiment' => 'positivo', 'emotion' => 'satisfaccion', 'score' => 0.82, 'intensity' => 52],
            ['index' => 8, 'start' => 73, 'end' => 94, 'sentiment' => 'positivo', 'emotion' => 'cierre_claro', 'score' => 0.68, 'intensity' => 60],
        ];
    }

    private function demoWavBytes(): string
    {
        $sampleRate = 8000;
        $durationSeconds = 94;
        $sampleCount = $sampleRate * $durationSeconds;
        $bitsPerSample = 16;
        $channels = 1;
        $bytesPerSample = (int) ($bitsPerSample / 8);
        $byteRate = $sampleRate * $channels * $bytesPerSample;
        $blockAlign = $channels * $bytesPerSample;
        $data = str_repeat(pack('v', 0), $sampleCount);
        $dataSize = strlen($data);

        return 'RIFF'
            .pack('V', 36 + $dataSize)
            .'WAVE'
            .'fmt '
            .pack('VvvVVvv', 16, 1, $channels, $sampleRate, $byteRate, $blockAlign, $bitsPerSample)
            .'data'
            .pack('V', $dataSize)
            .$data;
    }

    private function evaluation(Interaction $interaction, QualityFormVersion $version, $subAttributes, array $users, array $scenario): Evaluation
    {
        $createdAt = $interaction->created_at->copy()->addHours(1);
        $isAi = $scenario['type'] === 'ai';
        $score = $scenario['score'];

        $evaluation = Evaluation::create([
            'interaction_id' => $interaction->id,
            'form_version_id' => $version->id,
            'campaign_id' => $interaction->campaign_id,
            'agent_id' => $interaction->agent_id,
            'type' => $scenario['type'],
            'evaluator_id' => $isAi ? null : $users['monitor']->id,
            'total_score' => $score,
            'max_possible_score' => 100,
            'percentage_score' => $score,
            'status' => $scenario['status'],
            'is_gold' => $scenario['status'] === Evaluation::STATUS_CLOSED,
            'ai_processed_at' => $isAi && $scenario['status'] !== Evaluation::STATUS_PENDING_AI ? $createdAt->copy()->addMinutes(8) : null,
            'ai_provider' => $scenario['provider'],
            'ai_model' => $scenario['model'],
            'ai_prompt_version' => $isAi && $scenario['provider'] ? AiSettings::PROMPT_VERSION : null,
            'ai_prompt_hash' => $isAi && $scenario['provider'] ? hash('sha256', "demo-{$scenario['title']}") : null,
            'ai_settings_snapshot' => $isAi && $scenario['provider'] ? ['provider' => $scenario['provider'], 'config' => ['model' => $scenario['model']]] : null,
            'ai_summary' => $isAi ? "Resumen IA demo para {$scenario['title']}." : null,
            'ai_raw_response' => $scenario['status'] === Evaluation::STATUS_AI_FAILED ? 'Demo: respuesta inválida del proveedor IA.' : null,
        ]);

        $evaluation->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

        if ($score !== null) {
            $this->evaluationItems($evaluation, $subAttributes, (float) $score);
        }

        $this->applyLifecycleMetadata($evaluation, $users);
        $this->auditTimeline($evaluation, $users, $scenario['title']);

        return $evaluation->fresh();
    }

    private function evaluationItems(Evaluation $evaluation, $subAttributes, float $score): void
    {
        $count = $subAttributes->count();
        $compliantCount = (int) round(($score / 100) * $count);

        foreach ($subAttributes as $index => $subAttribute) {
            $isCompliant = $index < $compliantCount;
            $effectiveWeight = ((float) $subAttribute->attribute->weight * (float) $subAttribute->weight_percent) / 100;

            EvaluationItem::create([
                'evaluation_id' => $evaluation->id,
                'subattribute_id' => $subAttribute->id,
                'status' => $isCompliant ? 'compliant' : 'non_compliant',
                'score' => $isCompliant ? 1 : 0,
                'max_score' => 1,
                'weighted_score' => $isCompliant ? $effectiveWeight : 0,
                'confidence' => $evaluation->type === 'ai' ? 0.88 : 0.98,
                'evidence_quote' => $isCompliant
                    ? 'La transcripción contiene evidencia suficiente de cumplimiento.'
                    : 'No se encontró evidencia clara de cumplimiento en la interacción.',
                'ai_notes' => $evaluation->type === 'ai' ? 'Criterio evaluado automáticamente en datos demo.' : null,
            ]);
        }
    }

    private function applyLifecycleMetadata(Evaluation $evaluation, array $users): void
    {
        $publishedStatuses = [
            Evaluation::STATUS_PUBLISHED_TO_AGENT,
            Evaluation::STATUS_AGENT_ACCEPTED,
            Evaluation::STATUS_AGENT_DISPUTED,
            Evaluation::STATUS_DISPUTE_RESOLVED,
            Evaluation::STATUS_CLOSED,
        ];

        if (in_array($evaluation->status, $publishedStatuses, true)) {
            $evaluation->forceFill([
                'reviewed_by' => $users['monitor']->id,
                'reviewed_at' => $evaluation->created_at->copy()->addHours(2),
                'review_notes' => 'Notas demo: evaluación validada para mostrar el flujo operativo.',
                'published_by' => $users['monitor']->id,
                'visible_to_agent_at' => $evaluation->created_at->copy()->addHours(3),
                'finalized_at' => $evaluation->created_at->copy()->addHours(3),
                'evaluator_id' => $evaluation->evaluator_id ?: $users['monitor']->id,
            ])->save();
        }

        if ($evaluation->status === Evaluation::STATUS_AGENT_ACCEPTED || $evaluation->status === Evaluation::STATUS_CLOSED) {
            $this->agentResponse($evaluation, 'accept');
        }

        if ($evaluation->status === Evaluation::STATUS_AGENT_DISPUTED) {
            $this->agentResponse($evaluation, 'dispute');
            $this->dispute($evaluation, DisputeResolution::STATUS_PENDING_SUPERVISOR_REVIEW, $users);
        }

        if ($evaluation->status === Evaluation::STATUS_DISPUTE_RESOLVED) {
            $this->agentResponse($evaluation, 'dispute');
            $this->dispute($evaluation, DisputeResolution::STATUS_RESOLVED, $users);
        }

        if ($evaluation->status === Evaluation::STATUS_CLOSED) {
            $evaluation->forceFill([
                'previous_status_before_close' => Evaluation::STATUS_AGENT_ACCEPTED,
                'closed_at' => $evaluation->created_at->copy()->addDays(2),
                'closed_by' => $users['qaManager']->id,
                'closure_reason' => 'Caso demo cerrado para validar ciclo de vida.',
            ])->save();
        }
    }

    private function agentResponse(Evaluation $evaluation, string $type): AgentResponse
    {
        $items = $evaluation->items()->nonCompliant()->limit(2)->pluck('id')->all();

        return AgentResponse::create([
            'evaluation_id' => $evaluation->id,
            'agent_id' => $evaluation->agent_id,
            'response_type' => $type,
            'commitment_comment' => $type === 'accept' ? 'Acepto la evaluación y reforzaré los puntos observados.' : null,
            'dispute_reason' => $type === 'dispute' ? 'La evidencia no considera que se explicó el plazo al cliente.' : null,
            'disputed_items' => $type === 'dispute' ? $items : null,
            'responded_at' => $evaluation->created_at->copy()->addHours(5),
        ]);
    }

    private function dispute(Evaluation $evaluation, string $status, array $users): DisputeResolution
    {
        $response = $evaluation->agentResponse()->firstOrFail();
        $resolved = $status === DisputeResolution::STATUS_RESOLVED;

        return DisputeResolution::create([
            'agent_response_id' => $response->id,
            'evaluation_id' => $evaluation->id,
            'status' => $status,
            'supervisor_reviewed_by' => $resolved ? $users['supervisor']->id : null,
            'supervisor_reviewed_at' => $resolved ? $evaluation->created_at->copy()->addHours(8) : null,
            'supervisor_notes' => $resolved ? 'Supervisor valida parcialmente el reclamo.' : null,
            'qa_reviewed_by' => $resolved ? $users['monitor']->id : null,
            'qa_reviewed_at' => $resolved ? $evaluation->created_at->copy()->addHours(10) : null,
            'qa_recommendation' => $resolved ? 'adjust_score' : null,
            'qa_notes' => $resolved ? 'Se recomienda ajuste por evidencia adicional.' : null,
            'coordinator_reviewed_by' => $resolved ? $users['coordinator']->id : null,
            'coordinator_reviewed_at' => $resolved ? $evaluation->created_at->copy()->addHours(12) : null,
            'coordinator_decision' => $resolved ? 'approve_adjustment' : null,
            'coordinator_notes' => $resolved ? 'Ajuste validado en revisión demo.' : null,
            'resolved_by' => $resolved ? $users['qaManager']->id : null,
            'resolution_notes' => $resolved ? 'Se ajusta score final y se cierra disputa demo.' : null,
            'resolution_decision' => $resolved ? 'adjusted' : null,
            'adjusted_score' => $resolved ? (float) $evaluation->percentage_score + 5 : null,
            'resolved_at' => $resolved ? $evaluation->created_at->copy()->addHours(14) : null,
        ]);
    }

    private function auditTimeline(Evaluation $evaluation, array $users, string $title): void
    {
        if ($evaluation->type === 'ai') {
            $evaluation->recordAuditEvent('ai_queued', $users['monitor'], ['demo' => true, 'scenario' => $title], null, Evaluation::STATUS_PENDING_AI);

            if ($evaluation->status !== Evaluation::STATUS_PENDING_AI) {
                $evaluation->recordAuditEvent('ai_processing_started', null, ['demo' => true], Evaluation::STATUS_PENDING_AI, Evaluation::STATUS_AI_PROCESSING);
            }

            if ($evaluation->status === Evaluation::STATUS_AI_FAILED) {
                $evaluation->recordAuditEvent('ai_failed', null, ['error' => 'Respuesta inválida demo'], Evaluation::STATUS_AI_PROCESSING, Evaluation::STATUS_AI_FAILED);
            } elseif ($evaluation->status !== Evaluation::STATUS_AI_PROCESSING) {
                $evaluation->recordAuditEvent('ai_evaluated', null, ['provider' => $evaluation->ai_provider], Evaluation::STATUS_AI_PROCESSING, Evaluation::STATUS_PENDING_MONITOR_REVIEW);
            }
        } else {
            $evaluation->recordAuditEvent('manual_created', $users['monitor'], ['demo' => true, 'scenario' => $title], null, $evaluation->status);
        }

        if ($evaluation->visible_to_agent_at) {
            $evaluation->recordAuditEvent('published', $users['monitor'], ['demo' => true], Evaluation::STATUS_PENDING_MONITOR_REVIEW, Evaluation::STATUS_PUBLISHED_TO_AGENT);
        }

        if ($evaluation->status === Evaluation::STATUS_AGENT_ACCEPTED || $evaluation->status === Evaluation::STATUS_CLOSED) {
            $evaluation->recordAuditEvent('agent_accepted', $evaluation->agent, ['demo' => true], Evaluation::STATUS_PUBLISHED_TO_AGENT, Evaluation::STATUS_AGENT_ACCEPTED);
        }

        if ($evaluation->status === Evaluation::STATUS_AGENT_DISPUTED || $evaluation->status === Evaluation::STATUS_DISPUTE_RESOLVED) {
            $evaluation->recordAuditEvent('agent_disputed', $evaluation->agent, ['demo' => true], Evaluation::STATUS_PUBLISHED_TO_AGENT, Evaluation::STATUS_AGENT_DISPUTED);
        }

        if ($evaluation->status === Evaluation::STATUS_DISPUTE_RESOLVED) {
            $evaluation->recordAuditEvent('dispute_resolved', $users['qaManager'], ['demo' => true], Evaluation::STATUS_AGENT_DISPUTED, Evaluation::STATUS_DISPUTE_RESOLVED);
        }

        if ($evaluation->status === Evaluation::STATUS_CLOSED) {
            $evaluation->recordAuditEvent('closed', $users['qaManager'], ['demo' => true], Evaluation::STATUS_AGENT_ACCEPTED, Evaluation::STATUS_CLOSED);
        }
    }

    private function calibrationPair(Campaign $campaign, QualityFormVersion $version, $subAttributes, array $users): void
    {
        $interaction = $this->interaction($campaign, $users['agent3'], $users['supervisor'], $users['monitor'], 'Calibracion IA vs monitor con delta alto', 2);

        $this->evaluation($interaction, $version, $subAttributes, $users, [
            'title' => 'IA permisiva frente a monitor',
            'agent' => 'agent3',
            'type' => 'ai',
            'status' => Evaluation::STATUS_PENDING_MONITOR_REVIEW,
            'score' => 96,
            'days' => 2,
            'provider' => 'gemini',
            'model' => 'gemini-2.5-flash',
        ]);

        $this->evaluation($interaction, $version, $subAttributes, $users, [
            'title' => 'Monitor detecta brecha crítica',
            'agent' => 'agent3',
            'type' => 'manual',
            'status' => Evaluation::STATUS_PENDING_MONITOR_REVIEW,
            'score' => 72,
            'days' => 2,
            'provider' => null,
            'model' => null,
        ]);
    }
}
