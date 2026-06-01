<?php

use App\Http\Controllers\AIRecommendationController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DrugController;
use App\Http\Controllers\ManagementController;
use App\Http\Controllers\TenderController;
use App\Http\Controllers\PredictionController;
use App\Http\Controllers\ImportBatchController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\PricingStatisticsController;
use App\Http\Controllers\StandardizationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'landing'])->name('landing');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [PageController::class, 'dashboard'])->name('dashboard');

    Route::get('/uploads', [UploadController::class, 'index'])->name('uploads.index');
    Route::post('/uploads', [UploadController::class, 'store'])->name('uploads.store');
    Route::post('/uploads/manual', [UploadController::class, 'manualStore'])->name('uploads.manual.store');
    Route::post('/uploads/upcoming-tenders', [UploadController::class, 'storeUpcomingTender'])
        ->name('uploads.upcoming-tenders.store');

    Route::get('/imports', [ImportBatchController::class, 'index'])->name('imports.index');
    Route::get('/imports/{import}', [ImportBatchController::class, 'show'])->name('imports.show');
    Route::get('/imports/{import}/mapping', [ImportBatchController::class, 'mapping'])->name('imports.mapping');
    Route::post('/imports/{import}/mapping', [ImportBatchController::class, 'confirmMapping'])->name('imports.mapping.confirm');
    Route::get('/imports/{import}/progress', [ImportBatchController::class, 'progress'])->name('imports.progress');
    Route::post('/imports/{import}/chunks/retry-failed', [ImportBatchController::class, 'retryFailedChunks'])
        ->name('imports.chunks.retry-failed');
    Route::get('/imports/{import}/preview', [ImportBatchController::class, 'preview'])->name('imports.preview');
    Route::delete('/imports/{import}', [ImportBatchController::class, 'destroy'])->name('imports.destroy');
    Route::post('/imports/{import}/materialize', [ImportBatchController::class, 'materialize'])->name('imports.materialize');
    Route::post('/imports/{import}/materialization/retry-failed', [ImportBatchController::class, 'retryFailedMaterializationChunks'])
        ->name('imports.materialization.retry-failed');
    Route::post('/imports/{import}/statistics/retry', [ImportBatchController::class, 'retryStatistics'])
        ->name('imports.statistics.retry');
    Route::get('/management', [ManagementController::class, 'index'])->name('management.index');
    Route::get('/management/bid-records/{bidRecord}', [ManagementController::class, 'show'])->name('management.bid-records.show');
    Route::get('/management/bid-records/{bidRecord}/edit', [ManagementController::class, 'edit'])->name('management.bid-records.edit');
    Route::put('/management/bid-records/{bidRecord}', [ManagementController::class, 'update'])->name('management.bid-records.update');
    Route::post('/management/bid-records/{bidRecord}/toggle-exclusion', [ManagementController::class, 'toggleExclusion'])
        ->name('management.bid-records.toggle-exclusion');

    Route::get('/companies', [CompanyController::class, 'index'])->name('companies.index');
    Route::get('/companies/{company}', [CompanyController::class, 'show'])->name('companies.show');

    Route::get('/tenders', [TenderController::class, 'index'])->name('tenders.index');
    Route::get('/tenders/{tender}', [TenderController::class, 'show'])->name('tenders.show');

    Route::get('/drugs', [DrugController::class, 'index'])->name('drugs.index');
    Route::get('/drugs/{drug}', [DrugController::class, 'show'])->name('drugs.show');

    Route::get('/statistics/pricing', [PricingStatisticsController::class, 'index'])
        ->name('statistics.pricing.index');

    Route::get('/standardization', [StandardizationController::class, 'index'])->name('standardization.index');
    Route::post('/standardization/batches/{batch}/run', [StandardizationController::class, 'runBatch'])
        ->name('standardization.run-batch');
    Route::post('/standardization/batches/{batch}/retry-failed', [StandardizationController::class, 'retryFailedChunks'])
        ->name('standardization.retry-failed');
    Route::post('/standardization/bulk-action', [StandardizationController::class, 'bulkAction'])
        ->name('standardization.bulk-action');
    Route::post('/standardization/batches/{batch}/approve-all-review', [StandardizationController::class, 'approveAllReviewForBatch'])
        ->name('standardization.approve-all-review');
    Route::post('/standardization/rows/{row}/approve', [StandardizationController::class, 'approveRow'])
        ->name('standardization.approve-row');
    Route::post('/standardization/rows/{row}/reject', [StandardizationController::class, 'rejectRow'])
        ->name('standardization.reject-row');
    Route::put('/standardization/rows/{row}/edit-match', [StandardizationController::class, 'editMatch'])
        ->name('standardization.edit-match');
    Route::get('/standardization/search/products', [StandardizationController::class, 'searchProducts'])
        ->name('standardization.search-products');
    Route::get('/standardization/search/companies', [StandardizationController::class, 'searchCompanies'])
        ->name('standardization.search-companies');

    Route::get('/ai/recommendations/create', [AIRecommendationController::class, 'create'])->name('ai.recommendations.create');
    Route::post('/ai/recommendations', [AIRecommendationController::class, 'store'])->name('ai.recommendations.store');
    Route::get('/ai/recommendations/{prediction}', [AIRecommendationController::class, 'show'])->name('ai.recommendations.show');
    Route::post('/ai/recommendations/{prediction}/regenerate-insights', [AIRecommendationController::class, 'regenerateInsights'])->name('ai.recommendations.regenerate-insights');

    Route::get('/predictions', [PredictionController::class, 'index'])->name('predictions.index');
    Route::get('/predictions/{prediction}', [PredictionController::class, 'show'])->name('predictions.show');

    Route::get('/reports', [PageController::class, 'reportsIndex'])->name('reports.index');
    Route::get('/reports/company', [PageController::class, 'reportsCompany'])->name('reports.company');
    Route::get('/reports/opportunity', [PageController::class, 'reportsOpportunity'])->name('reports.opportunity');
    Route::get('/reports/history', [PageController::class, 'reportsHistory'])->name('reports.history');

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings/general', [SettingsController::class, 'updateGeneral'])->name('settings.general.update');
    Route::put('/settings/prediction', [SettingsController::class, 'updatePrediction'])->name('settings.prediction.update');
    Route::put('/settings/standardization', [SettingsController::class, 'updateStandardization'])->name('settings.standardization.update');
    Route::put('/settings/ai', [SettingsController::class, 'updateAi'])->name('settings.ai.update');
    Route::delete('/settings/ai/api-key', [SettingsController::class, 'destroyAiKey'])->name('settings.ai.api-key.destroy');
    Route::post('/settings/ai/test', [SettingsController::class, 'testAiConnection'])->name('settings.ai.test');
    Route::post('/settings/ai/test-narrative', [SettingsController::class, 'testAiNarrative'])->name('settings.ai.test-narrative');
    Route::post('/settings/users', [SettingsController::class, 'storeUser'])->name('settings.users.store');
    Route::post('/settings/users/{user}/toggle-status', [SettingsController::class, 'toggleUserStatus'])->name('settings.users.toggle-status');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
