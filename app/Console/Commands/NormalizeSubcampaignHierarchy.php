<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\QualityForm;
use App\Models\QualityFormVersion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizeSubcampaignHierarchy extends Command
{
    protected $signature = 'qa:normalize-subcampaign-hierarchy
        {--apply : Persist changes instead of dry-running}
        {--campaign= : Limit to one parent campaign by id or exact name}';

    protected $description = 'Move operational records from parent campaigns to their single subcampaign when the mapping is unambiguous';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $rows = [];

        $parents = Campaign::query()
            ->parents()
            ->with(['children' => fn ($query) => $query->orderBy('name')])
            ->when($this->option('campaign'), function ($query, string $campaign) {
                if (ctype_digit($campaign)) {
                    $query->whereKey((int) $campaign);
                } else {
                    $query->where('name', $campaign);
                }
            })
            ->orderBy('name')
            ->get();

        foreach ($parents as $parent) {
            if ($parent->children->count() !== 1) {
                $rows[] = [
                    $parent->name,
                    $parent->children->count(),
                    '-',
                    'skip',
                    'requires exactly one subcampaign',
                ];
                continue;
            }

            $child = $parent->children->first();
            $summary = $this->countsForParent($parent);

            $rows[] = [
                $parent->name,
                1,
                $child->name,
                $apply ? 'apply' : 'dry-run',
                collect($summary)->map(fn ($count, $table) => "{$table}:{$count}")->implode(', '),
            ];

            if (! $apply) {
                continue;
            }

            DB::transaction(function () use ($parent, $child) {
                $this->moveAssignments($parent, $child);
                $this->copyManagers($parent, $child);
                $this->moveInteractions($parent, $child);
                $this->moveEvaluations($parent, $child);
                $this->moveWeeklyReports($parent, $child);
                $this->moveNullableCampaignRows($parent, $child);
            });
        }

        $this->table(['Campaña', 'Subcampañas', 'Destino', 'Modo', 'Registros'], $rows);
        $this->info($apply
            ? 'Jerarquía de subcampañas normalizada.'
            : 'Dry run complete. Re-run with --apply to persist changes.');

        return Command::SUCCESS;
    }

    private function countsForParent(Campaign $parent): array
    {
        return [
            'assignments' => DB::table('campaign_user_assignments')->where('campaign_id', $parent->id)->count(),
            'interactions' => DB::table('interactions')->where('campaign_id', $parent->id)->count(),
            'evaluations' => DB::table('evaluations')->where('campaign_id', $parent->id)->count(),
            'staffing_batches' => $this->tableCount('staffing_batches', $parent->id),
            'staffing_members' => $this->tableCount('staffing_members', $parent->id),
            'sampling_plans' => $this->tableCount('sampling_plans', $parent->id),
            'sampling_orders' => $this->tableCount('sampling_orders', $parent->id),
            'insight_reports' => $this->tableCount('insight_reports', $parent->id),
            'weekly_reports' => $this->tableCount('weekly_reports', $parent->id),
        ];
    }

    private function tableCount(string $table, int $campaignId): int
    {
        if (! $this->tableExists($table)) {
            return 0;
        }

        return DB::table($table)->where('campaign_id', $campaignId)->count();
    }

    private function moveAssignments(Campaign $parent, Campaign $child): void
    {
        $assignments = DB::table('campaign_user_assignments')
            ->where('campaign_id', $parent->id)
            ->get();

        foreach ($assignments as $assignment) {
            $duplicate = DB::table('campaign_user_assignments')
                ->where('campaign_id', $child->id)
                ->where('agent_id', $assignment->agent_id)
                ->where('supervisor_id', $assignment->supervisor_id)
                ->exists();

            if ($duplicate) {
                DB::table('campaign_user_assignments')
                    ->where('id', $assignment->id)
                    ->update([
                        'is_active' => false,
                        'updated_at' => now(),
                    ]);

                continue;
            }

            DB::table('campaign_user_assignments')
                ->where('id', $assignment->id)
                ->update([
                    'campaign_id' => $child->id,
                    'updated_at' => now(),
                ]);
        }
    }

    private function copyManagers(Campaign $parent, Campaign $child): void
    {
        $managerIds = DB::table('campaign_managers')
            ->where('campaign_id', $parent->id)
            ->pluck('user_id');

        foreach ($managerIds as $managerId) {
            DB::table('campaign_managers')->updateOrInsert(
                [
                    'campaign_id' => $child->id,
                    'user_id' => $managerId,
                ],
                [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    private function moveInteractions(Campaign $parent, Campaign $child): void
    {
        $formMap = $this->formIdMap($parent, $child);

        DB::table('interactions')
            ->where('campaign_id', $parent->id)
            ->orderBy('id')
            ->chunkById(100, function ($interactions) use ($child, $formMap) {
                foreach ($interactions as $interaction) {
                    $update = [
                        'campaign_id' => $child->id,
                        'updated_at' => now(),
                    ];

                    if ($interaction->quality_form_id && isset($formMap[$interaction->quality_form_id])) {
                        $update['quality_form_id'] = $formMap[$interaction->quality_form_id];
                    }

                    DB::table('interactions')
                        ->where('id', $interaction->id)
                        ->update($update);
                }
            });
    }

    private function moveEvaluations(Campaign $parent, Campaign $child): void
    {
        $versionMap = $this->formVersionIdMap($parent, $child);

        DB::table('evaluations')
            ->where('campaign_id', $parent->id)
            ->orderBy('id')
            ->chunkById(100, function ($evaluations) use ($child, $versionMap) {
                foreach ($evaluations as $evaluation) {
                    $update = [
                        'campaign_id' => $child->id,
                        'updated_at' => now(),
                    ];

                    if ($evaluation->form_version_id && isset($versionMap[$evaluation->form_version_id])) {
                        $update['form_version_id'] = $versionMap[$evaluation->form_version_id];
                    }

                    DB::table('evaluations')
                        ->where('id', $evaluation->id)
                        ->update($update);
                }
            });
    }

    private function moveNullableCampaignRows(Campaign $parent, Campaign $child): void
    {
        foreach (['staffing_batches', 'staffing_members', 'sampling_plans', 'sampling_orders', 'insight_reports'] as $table) {
            if (! $this->tableExists($table)) {
                continue;
            }

            DB::table($table)
                ->where('campaign_id', $parent->id)
                ->update([
                    'campaign_id' => $child->id,
                    'updated_at' => now(),
                ]);
        }
    }

    private function moveWeeklyReports(Campaign $parent, Campaign $child): void
    {
        if (! $this->tableExists('weekly_reports')) {
            return;
        }

        $reports = DB::table('weekly_reports')
            ->where('campaign_id', $parent->id)
            ->orderBy('id')
            ->get();

        foreach ($reports as $report) {
            $duplicate = DB::table('weekly_reports')
                ->where('campaign_id', $child->id)
                ->where('week_start', $report->week_start)
                ->exists();

            if ($duplicate) {
                continue;
            }

            DB::table('weekly_reports')
                ->where('id', $report->id)
                ->update([
                    'campaign_id' => $child->id,
                    'updated_at' => now(),
                ]);
        }
    }

    private function formIdMap(Campaign $parent, Campaign $child): array
    {
        $childForms = QualityForm::query()
            ->where('campaign_id', $child->id)
            ->get()
            ->keyBy(fn (QualityForm $form) => mb_strtolower($form->name));

        return QualityForm::query()
            ->where('campaign_id', $parent->id)
            ->get()
            ->mapWithKeys(function (QualityForm $parentForm) use ($childForms) {
                $childForm = $childForms->get(mb_strtolower($parentForm->name));

                return $childForm ? [$parentForm->id => $childForm->id] : [];
            })
            ->all();
    }

    private function formVersionIdMap(Campaign $parent, Campaign $child): array
    {
        $formMap = $this->formIdMap($parent, $child);

        if ($formMap === []) {
            return [];
        }

        $childVersions = QualityFormVersion::query()
            ->whereIn('quality_form_id', array_values($formMap))
            ->get()
            ->groupBy(fn (QualityFormVersion $version) => $version->quality_form_id.'-'.$version->version_number);

        return QualityFormVersion::query()
            ->whereIn('quality_form_id', array_keys($formMap))
            ->get()
            ->mapWithKeys(function (QualityFormVersion $parentVersion) use ($formMap, $childVersions) {
                $childFormId = $formMap[$parentVersion->quality_form_id] ?? null;
                $childVersion = $childFormId
                    ? $childVersions->get($childFormId.'-'.$parentVersion->version_number)?->first()
                    : null;

                return $childVersion ? [$parentVersion->id => $childVersion->id] : [];
            })
            ->all();
    }

    private function tableExists(string $table): bool
    {
        return DB::getSchemaBuilder()->hasTable($table);
    }
}
