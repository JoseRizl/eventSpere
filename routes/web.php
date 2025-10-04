<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\BracketController;
use App\Http\Controllers\ActivitiesController;
use App\Http\Controllers\AnnouncementsController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::inertia('/', 'Home')->name('home');
Route::get('/events/{id}', [EventController::class, 'show'])->name('event.details');

// Public API route for fetching brackets for a specific event
Route::get('/api/events/{event}/brackets', [BracketController::class, 'indexForEvent'])->name('api.events.brackets');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [EventController::class, 'dashboard'])->name('dashboard');
    // Route::get('/event-list', [EventController::class, 'index'])->name('event.list');
    Route::get('/category-list', [CategoryController::class, 'index'])->name('category.list');
    Route::get('/archive', [EventController::class, 'getArchivedEvents'])->name('archive');

    Route::get('/events', [EventController::class, 'index'])->name('events.index');
    Route::put('/events/{id}/restore', [EventController::class, 'restore'])->name('events.restore');
    Route::delete('/events/{id}/permanent', [EventController::class, 'permanentDelete'])->name('events.permanent-delete');
    Route::post('/events/update-default-image', [EventController::class, 'updateDefaultImage'])->name('events.updateDefaultImage');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::inertia('/bracket', 'Sports/Bracket')->name('bracket');

    Route::post('/events/{id}/update', [EventController::class, 'update'])->name('event.update');
    Route::put('/events/{id}/activities', [ActivitiesController::class, 'updateForEvent'])->name('events.activities.updateForEvent');
    Route::post('/events/{id}/announcements', [AnnouncementsController::class, 'storeForEvent'])->name('events.announcements.storeForEvent');
    Route::delete('/events/{id}/announcements/{announcementId}', [AnnouncementsController::class, 'destroyForEvent'])->name('events.announcements.destroyForEvent');

    // Category and Tag routes
    Route::post('/categories', [CategoryController::class, 'store'])->name('category.store');
    Route::put('/categories/{id}', [CategoryController::class, 'update'])->name('category.update');
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy'])->name('category.destroy');

    // Additional event routes for Vue actions
    Route::post('/events', [EventController::class, 'store'])->name('events.store');
    Route::put('/events/{id}/update-from-list', [EventController::class, 'updateFromList'])->name('events.updateFromList');
    Route::put('/events/{id}/archive', [EventController::class, 'archive'])->name('events.archive');

    // Task routes - make sure these are properly accessible
    // This route is now handled by the API route below
    Route::put('/events/{id}/tasks', [TaskController::class, 'updateForEvent'])->name('tasks.updateForEvent');
    Route::get('/events/{eventId}/tasks/{taskId}', [TaskController::class, 'show'])->name('tasks.show');

    // API routes
    Route::prefix('api')->name('api.')->group(function () {
        Route::get('/events', [EventController::class, 'index'])->name('events.index'); // For fetching all events as JSON
        Route::apiResource('brackets', BracketController::class)->except(['show', 'create', 'edit']);
        Route::get('/events/{eventId}/tasks', [TaskController::class, 'indexForEvent'])->name('events.tasks.indexForEvent');
        Route::get('/events/{eventId}/activities', [ActivitiesController::class, 'indexForEvent'])->name('events.activities.indexForEvent');
        Route::get('/events/{eventId}/announcements', [AnnouncementsController::class, 'indexForEvent'])->name('events.announcements.indexForEvent');
        Route::apiResource('tasks', TaskController::class)->except(['show', 'create', 'edit']);
    });
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
