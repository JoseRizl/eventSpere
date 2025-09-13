<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\CategoryController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::inertia('/', 'Home')->name('home');
Route::get('/events/{id}', [EventController::class, 'show'])->name('event.details');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [EventController::class, 'dashboard'])->name('dashboard');
    Route::inertia('/event-list', 'List/EventList')->name('event.list');
    Route::get('/category-list', [CategoryController::class, 'index'])->name('category.list');
    Route::get('/archive', [EventController::class, 'getArchivedEvents'])->name('archive');

    Route::get('/events', [EventController::class, 'index'])->name('events.index');
    Route::put('/events/{id}/restore', [EventController::class, 'restore'])->name('events.restore');
    Route::delete('/events/{id}/permanent', [EventController::class, 'permanentDelete'])->name('events.permanent-delete');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::inertia('/bracket', 'Sports/Bracket')->name('bracket');

    Route::post('/events/{id}/update', [EventController::class, 'update'])->name('event.update');

    // Category and Tag routes
    Route::post('/categories', [CategoryController::class, 'store'])->name('category.store');
    Route::put('/categories/{id}', [CategoryController::class, 'update'])->name('category.update');
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy'])->name('category.destroy');

    // Additional event routes for Vue actions
    Route::post('/events', [EventController::class, 'store'])->name('events.store');
    Route::put('/events/{id}/update-from-list', [EventController::class, 'updateFromList'])->name('events.updateFromList');
    Route::put('/events/{id}/archive', [EventController::class, 'archive'])->name('events.archive');
});


    Route::inertia('/register', 'Auth/Register')->name('register');
    Route::post('/register', [AuthController::class, 'register']);

    Route::inertia('/login', 'Auth/Login')->name('login');
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('guest')->group(function () {

    });

// Redirect any unmatched routes to home
Route::get('/{any}', function () {
    return redirect()->route('home');
})->where('any', '.*')->middleware('auth');
