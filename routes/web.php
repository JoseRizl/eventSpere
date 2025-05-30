<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\CategoryController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::inertia('/home', 'Home')->name('home');

    Route::inertia('/event-list', 'List/EventList')->name('event.list');
    Route::get('/category-list', [CategoryController::class, 'index'])->name('category.list');
    Route::inertia('/archive', 'List/Archive')->name('archive');

    Route::get('/archived-events', [EventController::class, 'getArchivedEvents'])->name('events.archived');
    Route::put('/events/{id}/restore', [EventController::class, 'restore'])->name('events.restore');
    Route::delete('/events/{id}/permanent', [EventController::class, 'permanentDelete'])->name('events.permanent-delete');

    Route::inertia('/create-category', 'Create/CreateCategory')->name('category.create');
    Route::inertia('/create-event', 'Create/CreateEvent')->name('event.create');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::inertia('/task-form', 'Create/TaskForm')->name('task.form');
    Route::inertia('/sports', 'Sports/MatchView')->name('match');
    Route::inertia('/bracket', 'Sports/Bracket')->name('bracket');

    Route::get('/events/{id}', [EventController::class, 'show'])->name('event.details');
    Route::post('/events/{id}/update', [EventController::class, 'update'])->name('event.update');

    // Category and Tag routes
    Route::post('/categories', [CategoryController::class, 'store'])->name('category.store');
    Route::put('/categories/{id}', [CategoryController::class, 'update'])->name('category.update');
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy'])->name('category.destroy');
});

Route::middleware('guest')->group(function () {
    Route::inertia('/register', 'Auth/Register')->name('register');
    Route::post('/register', [AuthController::class, 'register']);

    Route::inertia('/', 'Auth/Login')->name('login');
    Route::post('/', [AuthController::class, 'login']);
});

// Redirect any unmatched routes to home
Route::get('/{any}', function () {
    return redirect()->route('home');
})->where('any', '.*')->middleware('auth');
