<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\QualityAttribute;
use App\Models\QualityForm;
use App\Models\QualityFormVersion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SeedSubcampaignsFromQualityForms extends Command
{
    protected $signature = 'qa:seed-subcampaigns-from-forms
        {--apply : Persist changes instead of dry-running}
        {--campaign= : Limit to one general campaign by id or exact name}
        {--no-clone : Create subcampaigns without copying quality forms}';

    protected $description = 'Create operational subcampaigns using current quality form names as references';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $cloneForms = ! (bool) $this->option('no-clone');
        $rows = [];

        $campaigns = Campaign::query()
            ->parents()
            ->with(['children', 'forms.versions.formAttributes.subAttributes', 'managers'])
            ->when($this->option('campaign'), function ($query, string $campaign) {
                if (ctype_digit($campaign)) {
                    $query->whereKey((int) $campaign);
                } else {
                    $query->where('name', $campaign);
                }
            })
            ->orderBy('name')
            ->get();

        if ($campaigns->isEmpty()) {
            $this->warn('No general campaigns found for the requested scope.');

            return Command::SUCCESS;
        }

        foreach ($campaigns as $campaign) {
            foreach ($campaign->forms as $form) {
                $subcampaignName = $this->subcampaignNameFromForm($campaign, $form);

                if ($subcampaignName === null) {
                    $rows[] = [$campaign->name, $form->name, '-', 'skipped: no subcampaign name'];
                    continue;
                }

                $existingChild = $campaign->children()
                    ->get()
                    ->first(fn (Campaign $child) => Str::lower($child->name) === Str::lower($subcampaignName));

                $existingChildForm = $existingChild
                    ? QualityForm::query()
                        ->where('campaign_id', $existingChild->id)
                        ->where('name', $form->name)
                        ->exists()
                    : false;

                $action = $existingChild
                    ? ($existingChildForm || ! $cloneForms ? 'exists' : 'clone form')
                    : ($cloneForms ? 'create + clone form' : 'create');

                $rows[] = [$campaign->name, $form->name, $subcampaignName, $action];

                if (! $apply) {
                    continue;
                }

                DB::transaction(function () use ($campaign, $form, $subcampaignName, $existingChild, $existingChildForm, $cloneForms) {
                    $child = $existingChild ?: $this->createChildCampaign($campaign, $subcampaignName);

                    $managerIds = $campaign->managers->pluck('id')->all();
                    if ($managerIds !== []) {
                        $child->managers()->sync($managerIds);
                    }

                    if ($cloneForms && ! $existingChildForm) {
                        $this->cloneQualityForm($form, $child);
                    }
                });
            }
        }

        $this->table(['Campaña general', 'Ficha actual', 'Subcampaña', 'Acción'], $rows);
        $this->info($apply
            ? 'Subcampañas creadas desde fichas actuales.'
            : 'Dry run complete. Re-run with --apply to persist changes.');

        return Command::SUCCESS;
    }

    private function subcampaignNameFromForm(Campaign $campaign, QualityForm $form): ?string
    {
        $name = trim((string) $form->name);

        $name = preg_replace('/^\s*(ficha|formulario|pauta|evaluacion|evaluación)(\s+de)?(\s+calidad)?\s+/iu', '', $name) ?: $name;
        $name = preg_replace('/\s+/', ' ', trim($name)) ?: $name;

        if ($name === '' || Str::lower($name) === Str::lower($campaign->name)) {
            return null;
        }

        return Str::limit($name, 255, '');
    }

    private function createChildCampaign(Campaign $campaign, string $subcampaignName): Campaign
    {
        return $campaign->children()->create([
            'name' => $subcampaignName,
            'description' => $campaign->description,
            'is_active' => $campaign->is_active,
            'color' => $campaign->color,
            'target_quality' => $campaign->target_quality,
            'target_aht' => $campaign->target_aht,
            'type' => $campaign->type,
            'start_date' => $campaign->start_date,
            'end_date' => $campaign->end_date,
            'script_url' => $campaign->script_url,
        ]);
    }

    private function cloneQualityForm(QualityForm $sourceForm, Campaign $targetCampaign): QualityForm
    {
        $targetForm = QualityForm::create([
            'campaign_id' => $targetCampaign->id,
            'name' => $sourceForm->name,
            'description' => $sourceForm->description,
            'operational_context_markdown' => $sourceForm->operational_context_markdown,
            'context_file_path' => $sourceForm->context_file_path,
            'context_file_original_name' => $sourceForm->context_file_original_name,
            'context_file_mime' => $sourceForm->context_file_mime,
            'context_file_text' => $sourceForm->context_file_text,
            'context_file_uploaded_at' => $sourceForm->context_file_uploaded_at,
            'context_file_uploaded_by' => $sourceForm->context_file_uploaded_by,
            'created_by' => $sourceForm->created_by,
        ]);

        $activeVersionId = null;

        foreach ($sourceForm->versions as $sourceVersion) {
            $targetVersion = QualityFormVersion::create([
                'quality_form_id' => $targetForm->id,
                'version_number' => $sourceVersion->version_number,
                'status' => $sourceVersion->status,
                'is_active' => $sourceVersion->is_active,
                'published_at' => $sourceVersion->published_at,
                'published_by' => $sourceVersion->published_by,
            ]);

            foreach ($sourceVersion->formAttributes as $sourceAttribute) {
                $targetAttribute = QualityAttribute::create([
                    'form_version_id' => $targetVersion->id,
                    'name' => $sourceAttribute->name,
                    'weight' => $sourceAttribute->weight,
                    'concept' => $sourceAttribute->concept,
                    'guidelines' => $sourceAttribute->guidelines,
                    'sort_order' => $sourceAttribute->sort_order,
                ]);

                foreach ($sourceAttribute->subAttributes as $sourceSubAttribute) {
                    $targetAttribute->subAttributes()->create([
                        'name' => $sourceSubAttribute->name,
                        'weight_percent' => $sourceSubAttribute->weight_percent,
                        'concept' => $sourceSubAttribute->concept,
                        'guidelines' => $sourceSubAttribute->guidelines,
                        'is_critical' => $sourceSubAttribute->is_critical,
                        'sort_order' => $sourceSubAttribute->sort_order,
                    ]);
                }
            }

            if ($sourceForm->campaign?->active_form_version_id === $sourceVersion->id || $sourceVersion->is_active) {
                $activeVersionId = $targetVersion->id;
            }
        }

        if ($activeVersionId) {
            $targetCampaign->update(['active_form_version_id' => $activeVersionId]);
        }

        return $targetForm;
    }
}
