<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LabController;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/labs/{slug}', [LabController::class, 'show'])->name('lab.show');
