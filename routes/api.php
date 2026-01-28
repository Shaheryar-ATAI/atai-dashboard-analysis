<?php

use App\Http\Controllers\SalesmanPerformanceController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProjectApiController;

Route::get('/inquiries', [ProjectApiController::class, 'inquiries']);    // ?status=...&area=...
Route::get('/inquiries/{id}', [ProjectApiController::class, 'show']);
Route::get('/orders', [ProjectApiController::class, 'orders']);          // status=inhand (PO received)
Route::get('/kpis', [ProjectApiController::class, 'kpis']);


Route::get('/totals',      [ProjectApiController::class, 'totals']);
Route::get('/inquiries/{id}', [ProjectApiController::class, 'show']);
Route::post('/performance/salesman/ai', [SalesmanPerformanceController::class, 'aiToken']);
Route::get('/performance/salesman/ai-token/{token}', [SalesmanPerformanceController::class, 'aiTokenGet']);
