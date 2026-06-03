<?php

use App\Http\Controllers\Api\MobileAuthController;
use App\Http\Controllers\Api\MobileDashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('mobile')->group(function () {
    Route::post('login', [MobileAuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('mobile.auth')->group(function () {
        Route::get('me', [MobileAuthController::class, 'me']);
        Route::post('logout', [MobileAuthController::class, 'logout']);
        Route::get('dashboard', [MobileDashboardController::class, 'dashboard']);
        Route::get('summary', [MobileDashboardController::class, 'summary']);
        Route::get('alerts', [MobileDashboardController::class, 'alerts']);
        Route::get('evaluations', [MobileDashboardController::class, 'evaluations']);
        Route::post('evaluations/{evaluation}/viewed', [MobileDashboardController::class, 'markEvaluationViewed']);
        Route::post('evaluations/{evaluation}/respond', [MobileDashboardController::class, 'respondEvaluation']);
        Route::get('transcripts/{interaction}/audio', [MobileDashboardController::class, 'transcriptAudio']);
    });
});
