<?php

use App\Http\Controllers\GeneratorController;
use App\Http\Controllers\ValidatorController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('generator');
})->name('home');

Route::get('/generator', [GeneratorController::class, 'index'])
    ->name('generator');

Route::get('/validator', [ValidatorController::class, 'index'])
    ->name('validator');
