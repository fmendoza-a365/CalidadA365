<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\CampaignUserAssignmentController;
use App\Http\Controllers\TranscriptController;
use App\Http\Controllers\EvaluationController;
use App\Http\Controllers\AgentResponseController;
use Illuminate\Support\Facades\Route;

// Healthcheck route for Railway
Route::get('/up', function () {
    return response()->json(['status' => 'ok'], 200);
});

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Dashboard Calidad
    Route::get('/dashboard/quality', [App\Http\Controllers\DashboardQualityController::class, 'index'])->name('dashboard.quality');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Notificaciones
    Route::get('/notifications', [\App\Http\Controllers\NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [\App\Http\Controllers\NotificationController::class, 'markAsRead'])->name('notifications.mark-read');
    Route::post('/notifications/read-all', [\App\Http\Controllers\NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');

    // Campañas
    Route::resource('campaigns', CampaignController::class);

    // Asignaciones
    Route::resource('campaigns.assignments', CampaignUserAssignmentController::class)
        ->shallow()
        ->except(['show']);

    // Transcripciones
    Route::resource('transcripts', TranscriptController::class)
        ->parameters(['transcripts' => 'interaction']);
    Route::get('transcripts/{interaction}/download', [TranscriptController::class, 'download'])
        ->name('transcripts.download');
    Route::get('/transcripts/{interaction}/audio', [TranscriptController::class, 'audio'])->name('transcripts.audio');

    // AI Evaluation (rate limited - expensive operation)
    Route::middleware(['throttle:10,1'])->group(function () {
        Route::post('transcripts/{interaction}/evaluate', [TranscriptController::class, 'evaluate'])
            ->name('transcripts.evaluate');
    });

    // Evaluaciones
    Route::post('evaluations/{evaluation}/toggle-gold', [EvaluationController::class, 'toggleGold'])
        ->name('evaluations.toggle-gold');
    Route::get('evaluations/manual/{interaction}/create', [\App\Http\Controllers\ManualEvaluationController::class, 'create'])
        ->name('evaluations.create_manual');
    Route::post('evaluations/manual/{interaction}', [\App\Http\Controllers\ManualEvaluationController::class, 'store'])
        ->name('evaluations.store_manual');
    Route::resource('evaluations', EvaluationController::class)->only(['index', 'show']);

    // Respuestas de asesores
    Route::post('evaluations/{evaluation}/respond', [AgentResponseController::class, 'store'])
        ->name('evaluations.respond');

    // Resolución de disputas
    Route::post('disputes/{dispute}/resolve', [AgentResponseController::class, 'resolve'])
        ->name('disputes.resolve');

    // Fichas de Calidad
    Route::group(['middleware' => ['role:admin|qa_manager']], function () {
        Route::resource('quality-forms', \App\Http\Controllers\QualityFormController::class);
        Route::post('quality-forms/{qualityForm}/publish', [\App\Http\Controllers\QualityFormController::class, 'publish'])
            ->name('quality-forms.publish');
        Route::put('quality-forms/{qualityForm}/attributes', [\App\Http\Controllers\QualityFormController::class, 'updateAttributes'])
            ->name('quality-forms.update-attributes');
        Route::post('quality-forms/{qualityForm}/import', [\App\Http\Controllers\QualityFormController::class, 'importAttributes'])
            ->name('quality-forms.import');

        // Insights Module (rate limited - expensive AI operations)
        Route::get('insights', [\App\Http\Controllers\InsightsController::class, 'index'])->name('insights.index');
        Route::get('insights/{insight}', [\App\Http\Controllers\InsightsController::class, 'show'])->name('insights.show');
        Route::delete('insights/{insight}', [\App\Http\Controllers\InsightsController::class, 'destroy'])->name('insights.destroy');

        Route::middleware(['throttle:5,1'])->group(function () {
            Route::post('insights/generate', [\App\Http\Controllers\InsightsController::class, 'generate'])->name('insights.generate');
        });
    });

    // Configuración (solo admin)
    Route::group(['middleware' => ['role:admin']], function () {
        Route::resource('users', \App\Http\Controllers\UserController::class);
        Route::resource('roles', \App\Http\Controllers\RolePermissionController::class);

        Route::get('settings/ai', [\App\Http\Controllers\SettingsController::class, 'aiSettings'])
            ->name('settings.ai');
        Route::post('settings/ai', [\App\Http\Controllers\SettingsController::class, 'updateAiSettings'])
            ->name('settings.ai.update');
        Route::post('settings/ai/test', [\App\Http\Controllers\SettingsController::class, 'testAiConnection'])
            ->name('settings.ai.test');
    });

    // Dashboard Widgets (Custom Dashboard API) - Available to all authenticated users
    Route::get('dashboard/widgets', [\App\Http\Controllers\DashboardWidgetController::class, 'index'])
        ->name('dashboard.widgets.index');
    Route::post('dashboard/widgets', [\App\Http\Controllers\DashboardWidgetController::class, 'store'])
        ->name('dashboard.widgets.store');
    Route::put('dashboard/widgets/{widget}', [\App\Http\Controllers\DashboardWidgetController::class, 'update'])
        ->name('dashboard.widgets.update');
    Route::delete('dashboard/widgets/{widget}', [\App\Http\Controllers\DashboardWidgetController::class, 'destroy'])
        ->name('dashboard.widgets.destroy');
    Route::post('dashboard/widgets/bulk-update', [\App\Http\Controllers\DashboardWidgetController::class, 'bulkUpdate'])
        ->name('dashboard.widgets.bulk-update');
    Route::post('dashboard/widgets/data', [\App\Http\Controllers\WidgetDataController::class, 'getData'])
        ->name('dashboard.widgets.data');
});

require __DIR__ . '/auth.php';
