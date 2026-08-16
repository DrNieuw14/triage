<?php

use App\Http\Controllers\TrainingController;
use App\Http\Controllers\TriageController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('triage.form');
});

Route::get('/triage', [TriageController::class, 'create'])->name('triage.form');
Route::post('/triage/predict', [TriageController::class, 'predict'])->name('triage.predict');

Route::prefix('train')->name('train.')->group(function () {
    Route::get('/login', [TrainingController::class, 'showLogin'])->name('login');
    Route::post('/login', [TrainingController::class, 'login'])->name('login.submit');
    Route::post('/logout', [TrainingController::class, 'logout'])->name('logout');

    Route::middleware('training.auth')->group(function () {
        Route::get('/', [TrainingController::class, 'index'])->name('index');
        Route::post('/upload', [TrainingController::class, 'upload'])->name('upload');
    });
});
