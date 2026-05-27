<?php

use App\Http\Controllers\AgentResponseController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\CampaignUserAssignmentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EvaluationController;
use App\Http\Controllers\EvaluationWorkQueueController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TranscriptController;
use Illuminate\Support\Facades\Route;

// Healthcheck route for load balancers and uptime checks.
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
    Route::get('/work-queue', [EvaluationWorkQueueController::class, 'index'])->name('work-queue.index');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Notificaciones
    Route::get('/notifications', [\App\Http\Controllers\NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [\App\Http\Controllers\NotificationController::class, 'markAsRead'])->name('notifications.mark-read');
    Route::post('/notifications/read-all', [\App\Http\Controllers\NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');

    // Campañas
    Route::middleware('permission:view_campaigns')->group(function () {
        Route::get('campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
    });
    Route::middleware('permission:create_campaigns')->group(function () {
        Route::get('campaigns/create', [CampaignController::class, 'create'])->name('campaigns.create');
        Route::post('campaigns', [CampaignController::class, 'store'])->name('campaigns.store');
    });
    Route::get('campaigns/{campaign}', [CampaignController::class, 'show'])
        ->middleware('permission:view_campaigns')
        ->name('campaigns.show');
    Route::middleware('permission:edit_campaigns')->group(function () {
        Route::get('campaigns/{campaign}/edit', [CampaignController::class, 'edit'])->name('campaigns.edit');
        Route::put('campaigns/{campaign}', [CampaignController::class, 'update'])->name('campaigns.update');
        Route::patch('campaigns/{campaign}', [CampaignController::class, 'update']);
    });
    Route::delete('campaigns/{campaign}', [CampaignController::class, 'destroy'])
        ->middleware('permission:delete_campaigns')
        ->name('campaigns.destroy');

    // Asignaciones
    Route::middleware('permission:assign_agents')->group(function () {
        Route::get('campaigns/{campaign}/assignments', [CampaignUserAssignmentController::class, 'index'])
            ->name('campaigns.assignments.index');
        Route::get('campaigns/{campaign}/assignments/create', [CampaignUserAssignmentController::class, 'create'])
            ->name('campaigns.assignments.create');
        Route::post('campaigns/{campaign}/assignments', [CampaignUserAssignmentController::class, 'store'])
            ->name('campaigns.assignments.store');
        Route::get('assignments/{assignment}/edit', [CampaignUserAssignmentController::class, 'edit'])
            ->name('assignments.edit');
        Route::put('assignments/{assignment}', [CampaignUserAssignmentController::class, 'update'])
            ->name('assignments.update');
        Route::delete('assignments/{assignment}', [CampaignUserAssignmentController::class, 'destroy'])
            ->name('assignments.destroy');
    });

    // Transcripciones
    Route::middleware('permission:view_transcripts')->group(function () {
        Route::get('transcripts', [TranscriptController::class, 'index'])->name('transcripts.index');
    });
    Route::middleware('permission:create_transcripts')->group(function () {
        Route::get('transcripts/create', [TranscriptController::class, 'create'])->name('transcripts.create');
        Route::post('transcripts', [TranscriptController::class, 'store'])->name('transcripts.store');
    });
    Route::middleware('permission:view_transcripts')->group(function () {
        Route::get('transcripts/{interaction}', [TranscriptController::class, 'show'])->name('transcripts.show');
        Route::get('transcripts/{interaction}/download', [TranscriptController::class, 'download'])->name('transcripts.download');
        Route::get('transcripts/{interaction}/audio', [TranscriptController::class, 'audio'])->name('transcripts.audio');
    });
    Route::middleware('permission:edit_transcripts')->group(function () {
        Route::get('transcripts/{interaction}/edit', [TranscriptController::class, 'edit'])->name('transcripts.edit');
        Route::put('transcripts/{interaction}', [TranscriptController::class, 'update'])->name('transcripts.update');
        Route::patch('transcripts/{interaction}', [TranscriptController::class, 'update']);
    });
    Route::delete('transcripts/{interaction}', [TranscriptController::class, 'destroy'])
        ->middleware('permission:delete_transcripts')
        ->name('transcripts.destroy');

    // AI Evaluation (rate limited - expensive operation)
    Route::middleware(['throttle:10,1'])->group(function () {
        Route::post('transcripts/{interaction}/evaluate', [TranscriptController::class, 'evaluate'])
            ->middleware('permission:create_evaluations')
            ->name('transcripts.evaluate');
    });

    // Evaluaciones
    Route::post('evaluations/{evaluation}/toggle-gold', [EvaluationController::class, 'toggleGold'])
        ->name('evaluations.toggle-gold');
    Route::post('evaluations/{evaluation}/publish', [EvaluationController::class, 'publish'])
        ->name('evaluations.publish');
    Route::post('evaluations/{evaluation}/reanalyze', [EvaluationController::class, 'reanalyze'])
        ->name('evaluations.reanalyze');
    Route::post('evaluations/{evaluation}/close', [EvaluationController::class, 'close'])
        ->name('evaluations.close');
    Route::post('evaluations/{evaluation}/reopen', [EvaluationController::class, 'reopen'])
        ->name('evaluations.reopen');
    Route::get('evaluations/manual/{interaction}/create', [\App\Http\Controllers\ManualEvaluationController::class, 'create'])
        ->name('evaluations.create_manual');
    Route::post('evaluations/manual/{interaction}', [\App\Http\Controllers\ManualEvaluationController::class, 'store'])
        ->name('evaluations.store_manual');
    Route::resource('evaluations', EvaluationController::class)->only(['index', 'show']);
    Route::get('exports/evaluations.csv', [ExportController::class, 'evaluations'])
        ->name('exports.evaluations');
    Route::get('exports/calibration.csv', [ExportController::class, 'calibration'])
        ->name('exports.calibration');
    Route::get('evaluations/{evaluation}/audit.csv', [ExportController::class, 'audit'])
        ->name('exports.evaluation-audit');

    // Respuestas de asesores
    Route::post('evaluations/{evaluation}/respond', [AgentResponseController::class, 'store'])
        ->name('evaluations.respond');

    // Resolución de disputas
    Route::post('disputes/{dispute}/supervisor-review', [AgentResponseController::class, 'supervisorReview'])
        ->name('disputes.supervisor-review');
    Route::post('disputes/{dispute}/qa-review', [AgentResponseController::class, 'qaReview'])
        ->name('disputes.qa-review');
    Route::post('disputes/{dispute}/coordinator-review', [AgentResponseController::class, 'coordinatorReview'])
        ->name('disputes.coordinator-review');
    Route::post('disputes/{dispute}/resolve', [AgentResponseController::class, 'resolve'])
        ->name('disputes.resolve');

    // Fichas de Calidad
    Route::middleware('permission:view_quality_forms')->group(function () {
        Route::get('quality-forms', [\App\Http\Controllers\QualityFormController::class, 'index'])->name('quality-forms.index');
    });
    Route::middleware('permission:create_quality_forms')->group(function () {
        Route::get('quality-forms/create', [\App\Http\Controllers\QualityFormController::class, 'create'])->name('quality-forms.create');
        Route::post('quality-forms', [\App\Http\Controllers\QualityFormController::class, 'store'])->name('quality-forms.store');
    });
    Route::get('quality-forms/{qualityForm}', [\App\Http\Controllers\QualityFormController::class, 'show'])
        ->middleware('permission:view_quality_forms')
        ->name('quality-forms.show');
    Route::get('quality-forms/{qualityForm}/context', [\App\Http\Controllers\QualityFormController::class, 'viewContext'])
        ->middleware('permission:view_quality_forms')
        ->name('quality-forms.context.show');
    Route::get('quality-forms/{qualityForm}/context/download', [\App\Http\Controllers\QualityFormController::class, 'downloadContext'])
        ->middleware('permission:view_quality_forms')
        ->name('quality-forms.context.download');
    Route::middleware('permission:edit_quality_forms')->group(function () {
        Route::get('quality-forms/{qualityForm}/edit', [\App\Http\Controllers\QualityFormController::class, 'edit'])->name('quality-forms.edit');
        Route::put('quality-forms/{qualityForm}', [\App\Http\Controllers\QualityFormController::class, 'update'])->name('quality-forms.update');
        Route::patch('quality-forms/{qualityForm}', [\App\Http\Controllers\QualityFormController::class, 'update']);
        Route::put('quality-forms/{qualityForm}/attributes', [\App\Http\Controllers\QualityFormController::class, 'updateAttributes'])
            ->name('quality-forms.update-attributes');
        Route::put('quality-forms/{qualityForm}/context', [\App\Http\Controllers\QualityFormController::class, 'updateContext'])
            ->name('quality-forms.update-context');
        Route::post('quality-forms/{qualityForm}/import', [\App\Http\Controllers\QualityFormController::class, 'importAttributes'])
            ->name('quality-forms.import');
    });
    Route::post('quality-forms/{qualityForm}/publish', [\App\Http\Controllers\QualityFormController::class, 'publish'])
        ->middleware('permission:publish_quality_forms')
        ->name('quality-forms.publish');
    Route::delete('quality-forms/{qualityForm}', [\App\Http\Controllers\QualityFormController::class, 'destroy'])
        ->middleware('permission:delete_quality_forms')
        ->name('quality-forms.destroy');

    // Insights Module (rate limited - expensive AI operations)
    Route::group(['middleware' => ['permission:view_insights']], function () {
        Route::get('insights', [\App\Http\Controllers\InsightsController::class, 'index'])->name('insights.index');
        Route::get('insights/{insight}', [\App\Http\Controllers\InsightsController::class, 'show'])->name('insights.show');
        Route::delete('insights/{insight}', [\App\Http\Controllers\InsightsController::class, 'destroy'])->name('insights.destroy');

        Route::middleware(['permission:generate_insights', 'throttle:5,1'])->group(function () {
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

    Route::get('settings/ai/performance', [\App\Http\Controllers\SettingsController::class, 'aiPerformance'])
        ->name('settings.ai.performance');
});

require __DIR__.'/auth.php';
