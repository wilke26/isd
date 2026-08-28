<?php

declare(strict_types=1);

use App\Http\Controllers\MetricsController;
use App\Http\Middleware\AuthenticateMetrics;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/metrics', MetricsController::class)
    ->middleware(AuthenticateMetrics::class);
