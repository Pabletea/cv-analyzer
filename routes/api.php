<?php

use App\Http\Controllers\CvAnalysisController;
use Illuminate\Support\Facades\Route;

Route::post('/cv/analyze',          [CvAnalysisController::class, 'analyze']);
Route::get('/cv/{id}/status',       [CvAnalysisController::class, 'status']);
Route::get('/cv/{id}/report',       [CvAnalysisController::class, 'report']);

Route::post('/batch/analyze',       [CvAnalysisController::class, 'analyzeBatch']);
Route::get('/batch/{id}/status',    [CvAnalysisController::class, 'batchStatus']);
Route::get('/batch/{id}/ranking',   [CvAnalysisController::class, 'batchRanking']);


Route::post('/configs',             [CvAnalysisController::class, 'createConfig']);
Route::get('/configs',              [CvAnalysisController::class, 'listConfigs']);