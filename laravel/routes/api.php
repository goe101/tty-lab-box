<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AttemptController;
use App\Http\Controllers\Api\TerminalController;

// Group without specific auth middleware since we're using a dummy user internally if not logged in.
Route::post('/attempts/start', [AttemptController::class, 'start']);
Route::post('/attempts/submit', [AttemptController::class, 'submit']);
Route::post('/attempts/stop', [AttemptController::class, 'stop']);
Route::post('/terminal/token', [TerminalController::class, 'token']);
