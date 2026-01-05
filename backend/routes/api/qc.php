<?php

use App\Http\Controllers\AcceptanceRuleController;
use App\Http\Controllers\ReceivingInspectionController;
use App\Http\Controllers\NonConformanceReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Quality Control Module Routes
|--------------------------------------------------------------------------
| Acceptance Rules, Receiving Inspections, NCRs
| Requires: MODULE_QC_ENABLED=true
*/

// Acceptance Rules
Route::prefix('acceptance-rules')->group(function () {
    Route::middleware('permission:qc.view')->group(function () {
        Route::get('/', [AcceptanceRuleController::class, 'index']);
        Route::get('/list', [AcceptanceRuleController::class, 'list']);
        Route::get('/inspection-types', [AcceptanceRuleController::class, 'inspectionTypes']);
        Route::get('/sampling-methods', [AcceptanceRuleController::class, 'samplingMethods']);
        Route::post('/find-applicable', [AcceptanceRuleController::class, 'findApplicable']);
        Route::get('/{acceptanceRule}', [AcceptanceRuleController::class, 'show']);
    });

    Route::post('/', [AcceptanceRuleController::class, 'store'])->middleware('permission:qc.create');
    Route::put('/{acceptanceRule}', [AcceptanceRuleController::class, 'update'])->middleware('permission:qc.edit');
    Route::delete('/{acceptanceRule}', [AcceptanceRuleController::class, 'destroy'])->middleware('permission:qc.delete');
});

// Receiving Inspections
Route::prefix('receiving-inspections')->group(function () {
    Route::middleware('permission:qc.view')->group(function () {
        Route::get('/', [ReceivingInspectionController::class, 'index']);
        Route::get('/statistics', [ReceivingInspectionController::class, 'statistics']);
        Route::get('/results', [ReceivingInspectionController::class, 'results']);
        Route::get('/dispositions', [ReceivingInspectionController::class, 'dispositions']);
        Route::get('/for-grn/{goodsReceivedNote}', [ReceivingInspectionController::class, 'forGrn']);
        Route::get('/{receivingInspection}', [ReceivingInspectionController::class, 'show']);
    });

    Route::middleware('permission:qc.inspect')->group(function () {
        Route::post('/create-for-grn/{goodsReceivedNote}', [ReceivingInspectionController::class, 'createForGrn']);
        Route::post('/{receivingInspection}/record-result', [ReceivingInspectionController::class, 'recordResult']);
    });

    Route::post('/{receivingInspection}/approve', [ReceivingInspectionController::class, 'approve'])
        ->middleware('permission:qc.approve');

    Route::middleware('permission:qc.edit')->group(function () {
        Route::put('/{receivingInspection}/disposition', [ReceivingInspectionController::class, 'updateDisposition']);
        Route::post('/{receivingInspection}/transfer-to-qc', [ReceivingInspectionController::class, 'transferToQcZone']);
    });
});

// Non-Conformance Reports (NCR)
Route::prefix('ncrs')->group(function () {
    Route::middleware('permission:qc.view')->group(function () {
        Route::get('/', [NonConformanceReportController::class, 'index']);
        Route::get('/statistics', [NonConformanceReportController::class, 'statistics']);
        Route::get('/statuses', [NonConformanceReportController::class, 'statuses']);
        Route::get('/severities', [NonConformanceReportController::class, 'severities']);
        Route::get('/defect-types', [NonConformanceReportController::class, 'defectTypes']);
        Route::get('/dispositions', [NonConformanceReportController::class, 'dispositions']);
        Route::get('/supplier/{supplierId}/summary', [NonConformanceReportController::class, 'supplierSummary']);
        Route::get('/{nonConformanceReport}', [NonConformanceReportController::class, 'show']);
    });

    Route::middleware('permission:qc.create')->group(function () {
        Route::post('/', [NonConformanceReportController::class, 'store']);
        Route::post('/from-inspection/{receivingInspection}', [NonConformanceReportController::class, 'createFromInspection']);
    });

    Route::middleware('permission:qc.edit')->group(function () {
        Route::put('/{nonConformanceReport}', [NonConformanceReportController::class, 'update']);
        Route::post('/{nonConformanceReport}/submit-review', [NonConformanceReportController::class, 'submitForReview']);
        Route::post('/{nonConformanceReport}/start-progress', [NonConformanceReportController::class, 'startProgress']);
        Route::post('/{nonConformanceReport}/cancel', [NonConformanceReportController::class, 'cancel']);
    });

    Route::middleware('permission:qc.review')->post('/{nonConformanceReport}/complete-review', [NonConformanceReportController::class, 'completeReview']);

    Route::middleware('permission:qc.approve')->group(function () {
        Route::post('/{nonConformanceReport}/set-disposition', [NonConformanceReportController::class, 'setDisposition']);
        Route::post('/{nonConformanceReport}/close', [NonConformanceReportController::class, 'close']);
    });

    Route::delete('/{nonConformanceReport}', [NonConformanceReportController::class, 'destroy'])->middleware('permission:qc.delete');
});
