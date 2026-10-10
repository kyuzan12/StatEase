<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ColumnMappingController;
use App\Http\Controllers\LikertScaleController;
use App\Http\Controllers\ReliabilityController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ResultsController;
use App\Http\Controllers\SlovinController;
use App\Http\Controllers\StatisticalTestController;
use App\Http\Controllers\SurveyController;
use App\Http\Middleware\EnsureSurveyIsMapped;
use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------------------------------------
// Accounts: only for visitors who are not logged in ("guest"). Login and register are limited
// to 6 attempts per minute ("throttle") to slow down password guessing.
// ---------------------------------------------------------------------------------------------
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:6,1');
    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:6,1');
});

// ---------------------------------------------------------------------------------------------
// Everything else requires a logged-in user ("auth"). "can:view,survey" asks SurveyPolicy whether
// this user owns the survey; if not, Laravel answers 403 Forbidden.
// ---------------------------------------------------------------------------------------------
Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::redirect('/', '/surveys');

    // My surveys: list, upload form, save upload
    Route::get('surveys', [SurveyController::class, 'index'])->name('surveys.index');
    Route::get('surveys/create', [SurveyController::class, 'create'])->name('surveys.create');
    Route::post('surveys', [SurveyController::class, 'store'])->name('surveys.store');

    // One survey: only its owner
    Route::get('surveys/{survey}', [SurveyController::class, 'show'])->name('surveys.show')->middleware('can:view,survey');
    Route::delete('surveys/{survey}', [SurveyController::class, 'destroy'])->name('surveys.destroy')->middleware('can:delete,survey');

    // Choose the type of each column (Likert item, profile question, ignore)
    Route::get('surveys/{survey}/columns', [ColumnMappingController::class, 'edit'])->name('surveys.columns.edit')->middleware('can:update,survey');
    Route::put('surveys/{survey}/columns', [ColumnMappingController::class, 'update'])->name('surveys.columns.update')->middleware('can:update,survey');

    // Analysis pages: only the owner, and only after the column types are confirmed
    Route::middleware(['can:view,survey', EnsureSurveyIsMapped::class])->prefix('surveys/{survey}')->name('surveys.')->group(function () {
        Route::get('results', [ResultsController::class, 'show'])->name('results');
        Route::get('report.doc', [ReportController::class, 'word'])->name('report');
        Route::get('reliability', [ReliabilityController::class, 'show'])->name('reliability');
        Route::get('tests', [StatisticalTestController::class, 'index'])->name('tests.index');
        Route::get('tests/run', [StatisticalTestController::class, 'run'])->name('tests.run');
    });

    // Likert scales: everyone sees the built-in ones plus their own; only their own can be changed
    Route::get('scales', [LikertScaleController::class, 'index'])->name('scales.index');
    Route::get('scales/create', [LikertScaleController::class, 'create'])->name('scales.create');
    Route::post('scales', [LikertScaleController::class, 'store'])->name('scales.store');
    Route::get('scales/{scale}/edit', [LikertScaleController::class, 'edit'])->name('scales.edit')->middleware('can:update,scale');
    Route::put('scales/{scale}', [LikertScaleController::class, 'update'])->name('scales.update')->middleware('can:update,scale');
    Route::delete('scales/{scale}', [LikertScaleController::class, 'destroy'])->name('scales.destroy')->middleware('can:delete,scale');

    // Slovin's formula sample size calculator
    Route::get('slovin', [SlovinController::class, 'index'])->name('slovin.index');
    Route::post('slovin', [SlovinController::class, 'calculate'])->name('slovin.calculate');
});
