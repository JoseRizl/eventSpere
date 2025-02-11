<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::inertia('/home', 'Home')->name('home');

    Route::inertia('/event-list', 'List/EventList')->name('event.list');
    Route::inertia('/category-list', 'List/CategoryList')->name('category.list');

    Route::inertia('/create-category', 'Create/CreateCategory')->name('category.create');
    Route::inertia('/create-event', 'Create/CreateEvent')->name('event.create');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    //temp
    Route::inertia('/foundation-day', 'FoundationDay')->name('foundation.day');
    Route::inertia('/edit-event', 'Create/EditEvent')->name('event.edit');
    Route::inertia('/edit-category', 'Create/EditCategory')->name('category.edit');
    Route::inertia('/task-form', 'Create/TaskForm')->name('task.form');
});



Route::middleware('guest')->group(function () {
    Route::inertia('/register', 'Auth/Register')->name('register');
    Route::post('/register', [AuthController::class, 'register']);

    Route::inertia('/', 'Auth/Login')->name('login');
    Route::post('/', [AuthController::class, 'login']);
});

Route::get('/{any}', function () {
    return Inertia::render('App'); // Use a wrapper page like App.vue
})->where('any', '.*')->middleware('auth');
