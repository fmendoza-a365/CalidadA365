<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\QualityForm;
use App\Models\QualityFormVersion;
use App\Models\QualityAttribute;
use App\Models\QualitySubAttribute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QualityFormController extends Controller
{
    public function index()
    {
        $forms = QualityForm::with(['campaign', 'latestVersion'])->latest()->paginate(15);
        return view('quality-forms.index', compact('forms'));
    }

    public function create()
    {
        $campaigns = Campaign::active()->get();
        return view('quality-forms.create', compact('campaigns'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'campaign_id' => 'required|exists:campaigns,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $validated['created_by'] = auth()->id();
        $form = QualityForm::create($validated);

        // Crear versión inicial en draft
        QualityFormVersion::create([
            'quality_form_id' => $form->id,
            'version_number' => 1,
            'status' => 'draft',
        ]);

        return redirect()->route('quality-forms.edit', $form)
            ->with('success', 'Ficha creada. Ahora agrega los atributos.');
    }

    public function show(QualityForm $qualityForm)
    {
        $qualityForm->load(['campaign', 'versions.formAttributes.subAttributes']);
        return view('quality-forms.show', compact('qualityForm'));
    }

    public function edit(QualityForm $qualityForm)
    {
        $qualityForm->load(['latestVersion.formAttributes.subAttributes']);
        $campaigns = Campaign::active()->get();
        return view('quality-forms.edit', compact('qualityForm', 'campaigns'));
    }

    public function update(Request $request, QualityForm $qualityForm)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $qualityForm->update($validated);

        return redirect()->route('quality-forms.show', $qualityForm)
            ->with('success', 'Información de la ficha actualizada.');
    }

    public function updateAttributes(Request $request, QualityForm $qualityForm)
    {
        $validated = $request->validate([
            'attributes' => 'required|array|min:1',
            'attributes.*.name' => 'required|string',
            'attributes.*.weight' => 'required|numeric|min:0|max:100',
            'attributes.*.concept' => 'nullable|string',
            'attributes.*.subattributes' => 'required|array|min:1',
            'attributes.*.subattributes.*.name' => 'required|string',
            'attributes.*.subattributes.*.weight_percent' => 'required|numeric|min:0|max:100',
            'attributes.*.subattributes.*.concept' => 'nullable|string',
            'attributes.*.subattributes.*.guidelines' => 'nullable|string',
            'attributes.*.subattributes.*.is_critical' => 'nullable',
        ]);

        // ... (validation logic remains same)

        DB::transaction(function () use ($qualityForm, $validated) {
            // 1. Check if there is ALREADY a draft version
            $draftVersion = $qualityForm->versions()->where('status', 'draft')->first();

            if ($draftVersion) {
                // Use existing draft
                $version = $draftVersion;
                // Clear existing attributes to replace them
                $version->attributes()->delete();

                // Ensure it's active if desired, or leave logic as is
            } else {
                // 2. No draft exists. Create a new one based on the latest version number
                $lastVersionNumber = $qualityForm->versions()->max('version_number') ?? 0;

                $version = $qualityForm->versions()->create([
                    'version_number' => $lastVersionNumber + 1,
                    'status' => 'draft',
                    'is_active' => true
                ]);

                // Deactivate other versions to ensure only one is active (optional, depending on business logic)
                // Assuming we want the new draft to be the one being edited/previewed
                $qualityForm->versions()->where('id', '!=', $version->id)->update(['is_active' => false]);
            }

            // Eliminar y crear atributos
            foreach ($validated['attributes'] as $sortOrder => $attrData) {
                $attribute = QualityAttribute::create([
                    'form_version_id' => $version->id,
                    'name' => $attrData['name'],
                    'weight' => $attrData['weight'],
                    'concept' => $attrData['concept'] ?? null,
                    'sort_order' => $sortOrder + 1,
                ]);

                // Crear subatributos
                foreach ($attrData['subattributes'] as $subSortOrder => $subData) {
                    QualitySubAttribute::create([
                        'attribute_id' => $attribute->id,
                        'name' => $subData['name'],
                        'weight_percent' => $subData['weight_percent'],
                        'concept' => $subData['concept'] ?? null,
                        'guidelines' => $subData['guidelines'] ?? null,
                        'is_critical' => isset($subData['is_critical']),
                        'sort_order' => $subSortOrder + 1,
                    ]);
                }
            }
        });

        return redirect()->route('quality-forms.show', $qualityForm)
            ->with('success', 'Criterios de evaluación actualizados.');
    }

    public function destroy(QualityForm $qualityForm)
    {
        // 1. Verificar si alguna versión de ESTA ficha ha sido usada en evaluaciones
        $hasEvaluations = \App\Models\Evaluation::whereIn(
            'form_version_id',
            $qualityForm->versions()->pluck('id')
        )->exists();

        if ($hasEvaluations) {
            return back()->with('error', 'No se puede eliminar esta ficha porque tiene evaluaciones asociadas. Debes archivarla o eliminar las evaluaciones primero.');
        }

        try {
            DB::transaction(function () use ($qualityForm) {
                // 2. Si es la ficha activa de la campaña, desvincularla
                if (
                    $qualityForm->campaign->active_form_version_id &&
                    $qualityForm->versions()->where('id', $qualityForm->campaign->active_form_version_id)->exists()
                ) {
                    $qualityForm->campaign->update(['active_form_version_id' => null]);
                }

                // 3. Eliminar recursivamente versiones, atributos y subatributos
                $qualityForm->versions()->each(function ($version) {
                    // Eliminar subatributos
                    $version->formAttributes()->each(function ($attr) {
                        $attr->subAttributes()->delete();
                    });
                    $version->formAttributes()->each(function ($attr) { // Borra atributos
                        $attr->delete();
                    });
                    $version->delete(); // Borra la versión
                });

                // 4. Eliminar la ficha
                $qualityForm->delete();
            });

            return redirect()->route('quality-forms.index')
                ->with('success', 'Ficha de calidad eliminada permanentemente.');

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error eliminando ficha: ' . $e->getMessage());
            return back()->with('error', 'Error al eliminar la ficha: ' . $e->getMessage());
        }
    }

    public function publish(QualityForm $qualityForm)
    {
        $version = $qualityForm->latestVersion;

        if (!$version || $version->status !== 'draft') {
            return back()->withErrors(['publish' => 'No hay una versión en borrador para publicar.']);
        }

        // Validar pesos
        $errors = $version->validateWeights();
        if (!empty($errors)) {
            return back()->withErrors(['weights' => implode(' ', $errors)]);
        }

        DB::transaction(function () use ($version, $qualityForm) {
            // Publicar versión
            $version->update([
                'status' => 'published',
                'published_at' => now(),
                'published_by' => auth()->id(),
            ]);

            // Asignar como versión activa de la campaña
            $qualityForm->campaign->update([
                'active_form_version_id' => $version->id,
            ]);
        });

        return redirect()->route('quality-forms.show', $qualityForm)
            ->with('success', 'Ficha publicada y asignada a la campaña exitosamente.');
    }
    public function importAttributes(Request $request, QualityForm $qualityForm)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        $file = $request->file('csv_file');
        $path = $file->getRealPath();

        // Configuración para leer CSV
        $handle = fopen($path, "r");
        $header = fgetcsv($handle, 0, ","); // Leer cabecera

        // Mapeo básico de columnas por nombre (simple, asumiendo orden o nombres fijos del template)
        // Categoria,Peso_Categoria,Orden_Categoria,Concepto_Categoria,Item,Peso_Item,Critico,Concepto_Item,Ayuda_Referencia,Orden_Item

        $data = [];
        $rowIdx = 1;

        try {
            while (($row = fgetcsv($handle, 0, ",")) !== FALSE) {
                $rowIdx++;
                // Limpieza básica de caracteres BOM o espacios
                $categoriaName = $this->cleanCsvValue($row[0] ?? '');

                if (empty($categoriaName))
                    continue; // Fila vacía

                $catKey = strtolower(trim($categoriaName));

                if (!isset($data[$catKey])) {
                    $data[$catKey] = [
                        'name' => $categoriaName,
                        'weight' => (float) ($row[1] ?? 0),
                        'sort_order' => (int) ($row[2] ?? 0),
                        'concept' => $this->cleanCsvValue($row[3] ?? ''),
                        'items' => []
                    ];
                }

                // Validar consistencia de peso de categoría
                if (abs($data[$catKey]['weight'] - (float) ($row[1] ?? 0)) > 0.01) {
                    throw new \Exception("Error en fila $rowIdx: El peso de la categoría '$categoriaName' no es consistente.");
                }

                $itemName = $this->cleanCsvValue($row[4] ?? '');
                if (!empty($itemName)) {
                    $data[$catKey]['items'][] = [
                        'name' => $itemName,
                        'weight_percent' => (float) ($row[5] ?? 0),
                        'is_critical' => strtoupper(trim($row[6] ?? 'NO')) === 'SI',
                        'concept' => $this->cleanCsvValue($row[7] ?? ''),
                        'guidelines' => $this->cleanCsvValue($row[8] ?? ''),
                        'sort_order' => (int) ($row[9] ?? 0),
                    ];
                }
            }
            fclose($handle);

            // 1. Validar suma total de categorías (excluir categorías que son 100% críticas/MP)
            $nonMPCategories = array_filter($data, function ($cat) {
                $allCritical = !empty($cat['items']) && count(array_filter($cat['items'], fn($i) => $i['is_critical'])) === count($cat['items']);
                return !$allCritical;
            });
            $totalCatWeight = array_sum(array_column($nonMPCategories, 'weight'));
            if (abs($totalCatWeight - 100) > 0.1) {
                throw new \Exception("Los pesos de las categorías (sin MP) suman $totalCatWeight%, deben sumar 100%.");
            }

            // 2. Validar suma de items por categoría (excluir items críticos de la suma)
            foreach ($data as $cat) {
                $nonCriticalItems = array_filter($cat['items'], fn($i) => !$i['is_critical']);
                $criticalItems = array_filter($cat['items'], fn($i) => $i['is_critical']);

                // Si todos son críticos (categoría MP pura), no validar suma de pesos
                if (empty($nonCriticalItems) && !empty($criticalItems)) {
                    continue;
                }

                $totalItemWeight = array_sum(array_column($nonCriticalItems, 'weight_percent'));
                if (abs($totalItemWeight - 100) > 0.1) {
                    throw new \Exception("Los ítems no-críticos de '{$cat['name']}' suman $totalItemWeight%, deben sumar 100%.");
                }
            }

            // 3. Guardar en Base de Datos
            DB::transaction(function () use ($qualityForm, $data) {
                $version = $qualityForm->latestVersion;

                // Asegurar versión borrador
                if (!$version || $version->status !== 'draft') {
                    $version = QualityFormVersion::create([
                        'quality_form_id' => $qualityForm->id,
                        'version_number' => ($qualityForm->versions()->max('version_number') ?? 0) + 1,
                        'status' => 'draft',
                    ]);
                }

                // Limpiar todo lo anterior de esta versión
                foreach ($version->formAttributes as $attr) {
                    $attr->subAttributes()->delete();
                    $attr->delete();
                }

                // Insertar nuevas
                // Ordenar categorías por sort_order
                usort($data, fn($a, $b) => $a['sort_order'] <=> $b['sort_order']);

                foreach ($data as $catData) {
                    $attribute = QualityAttribute::create([
                        'form_version_id' => $version->id,
                        'name' => $catData['name'],
                        'weight' => $catData['weight'],
                        'concept' => $catData['concept'],
                        'sort_order' => $catData['sort_order'],
                    ]);

                    // Ordenar items
                    usort($catData['items'], fn($a, $b) => $a['sort_order'] <=> $b['sort_order']);

                    foreach ($catData['items'] as $itemData) {
                        QualitySubAttribute::create([
                            'attribute_id' => $attribute->id,
                            'name' => $itemData['name'],
                            'weight_percent' => $itemData['weight_percent'],
                            'is_critical' => $itemData['is_critical'],
                            'concept' => $itemData['concept'],
                            'guidelines' => $itemData['guidelines'],
                            'sort_order' => $itemData['sort_order'],
                        ]);
                    }
                }
            });

            return back()->with('success', 'Ficha importada correctamente.');

        } catch (\Exception $e) {
            if (is_resource($handle))
                fclose($handle);
            return back()->with('error', 'Error en importación: ' . $e->getMessage());
        }
    }

    private function cleanCsvValue($value)
    {
        // Remove UTF-8 BOM if present and trim
        $bom = pack('H*', 'EFBBBF');
        $value = preg_replace("/^$bom/", '', $value);
        return trim($value, " \t\n\r\0\x0B\"'");
    }
}
