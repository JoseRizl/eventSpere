<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MemorandumController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\BracketController;
use App\Http\Controllers\ActivitiesController;
use App\Http\Controllers\AnnouncementsController;
use App\Http\Controllers\CommitteeController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::inertia('/', 'Home')->name('home');
Route::get('/events/{id}', [EventController::class, 'show'])->name('event.details');

Route::inertia('/test', 'Test')->name('test');

// Public API routes (accessible without authentication)
Route::prefix('api')->name('api.')->group(function () {
    Route::get('/events/{event}/brackets', [BracketController::class, 'indexForEvent'])->name('events.brackets.index');
    Route::get('/brackets', [BracketController::class, 'index'])->name('brackets.index');
    // Point event-specific and general announcement index routes to the same controller method.
    Route::get('/events/{event}/announcements', [AnnouncementsController::class, 'index'])->name('events.announcements.index');
    Route::get('/announcements', [AnnouncementsController::class, 'index'])->name('announcements.index');
    Route::get('/events', [EventController::class, 'index'])->name('events.index'); // For fetching all events as JSON for guests
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [EventController::class, 'dashboard'])->name('dashboard');
    // Route::get('/event-list', [EventController::class, 'index'])->name('event.list');
    Route::get('/category-list', [CategoryController::class, 'index'])->name('category.list');
    Route::get('/archive/{type?}', [EventController::class, 'getArchivedEvents'])->name('archive');

    Route::get('/events', [EventController::class, 'index'])->name('events.index');
    Route::put('/events/{id}/restore', [EventController::class, 'restore'])->name('events.restore');
    Route::delete('/events/{id}/permanent', [EventController::class, 'permanentDelete'])->name('events.permanent-delete');
    Route::post('/events/update-default-image', [EventController::class, 'updateDefaultImage'])->name('events.updateDefaultImage');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::inertia('/bracket', 'Sports/Bracket')->name('bracket');

    Route::put('/events/{id}/update', [EventController::class, 'update'])->name('event.update');
    Route::put('/events/{id}/activities', [ActivitiesController::class, 'updateForEvent'])->name('events.activities.updateForEvent');
    Route::post('/announcements', [AnnouncementsController::class, 'store'])->name('announcements.store');
    Route::put('/announcements/{announcement}', [AnnouncementsController::class, 'update'])->name('announcements.update');
    Route::delete('/announcements/{announcement}', [AnnouncementsController::class, 'destroy'])->name('announcements.destroy');
    Route::post('/events/{event}/announcements', [AnnouncementsController::class, 'store'])->name('events.announcements.store');
    // Use scoped bindings to ensure the announcement belongs to the event.
    Route::put('/events/{event}/announcements/{announcement:id}', [AnnouncementsController::class, 'update'])->name('events.announcements.update');
    Route::delete('/events/{event}/announcements/{announcement:id}', [AnnouncementsController::class, 'destroy'])->name('events.announcements.destroy');

    // Category and Tag routes
    Route::post('/categories', [CategoryController::class, 'store'])->name('category.store');
    Route::put('/categories/{id}', [CategoryController::class, 'update'])->name('category.update');
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy'])->name('category.destroy');
    Route::put('/categories/{id}/archive', [CategoryController::class, 'archiveCategory'])->name('categories.archive');
    Route::put('/categories/{id}/restore', [CategoryController::class, 'restoreCategory'])->name('categories.restore');
    Route::delete('/categories/{id}/permanent', [CategoryController::class, 'permanentDeleteCategory'])->name('categories.permanent-delete');

    Route::put('/tags/{id}/archive', [CategoryController::class, 'archiveTag'])->name('tags.archive');
    Route::put('/tags/{id}/restore', [CategoryController::class, 'restoreTag'])->name('tags.restore');
    Route::delete('/tags/{id}/permanent', [CategoryController::class, 'permanentDeleteTag'])->name('tags.permanent-delete');

    // Additional event routes for Vue actions
    Route::post('/events', [EventController::class, 'store'])->name('events.store');
    Route::put('/events/{id}/update-from-list', [EventController::class, 'updateFromList'])->name('events.updateFromList');
    Route::put('/events/{id}/archive', [EventController::class, 'archive'])->name('events.archive');

    // Task routes - make sure these are properly accessible
    // This route is now handled by the API route below
    Route::put('/events/{id}/tasks', [TaskController::class, 'updateForEvent'])->name('tasks.updateForEvent');
    Route::get('/events/{eventId}/tasks/{taskId}', [TaskController::class, 'show'])->name('tasks.show');

    // Committee routes
    Route::post('/committees', [CommitteeController::class, 'store'])->name('committees.store');
    Route::delete('/committees/{id}', [CommitteeController::class, 'destroy'])->name('committees.destroy');

    // API routes (protected - require authentication)
    Route::prefix('api')->name('api.')->group(function () {
        Route::apiResource('brackets', BracketController::class)->except(['show', 'create', 'edit', 'index']);
        Route::put('/brackets/{id}/update-player-names', [BracketController::class, 'updatePlayerNames'])->name('brackets.updatePlayerNames');
        Route::put('/brackets/{id}/update-player-colors', [BracketController::class, 'updatePlayerColors'])->name('brackets.updatePlayerColors');
        Route::get('/events/{eventId}/tasks', [TaskController::class, 'indexForEvent'])->name('events.tasks.indexForEvent');
        Route::get('/events/{eventId}/activities', [ActivitiesController::class, 'indexForEvent'])->name('events.activities.indexForEvent');
        Route::apiResource('tasks', TaskController::class)->except(['show', 'create', 'edit']);

        // Memorandum API routes, note the parameter name change to 'event'
        Route::post('/events/{event}/memorandum', [MemorandumController::class, 'storeOrUpdate'])->name('memorandum.storeOrUpdate');
        Route::delete('/events/{event}/memorandum', [MemorandumController::class, 'destroy'])->name('memorandum.destroy');

        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::put('/notifications/{notification}', [NotificationController::class, 'update'])->name('notifications.update');
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
