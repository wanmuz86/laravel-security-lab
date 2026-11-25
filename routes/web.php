<?php

use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectSearchController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/projects');

Route::middleware('guest')->group(function () {

    Route::post('/forgot-password', [
        PasswordResetController::class, 'requestReset',
    ]);

    Route::post('/reset-password', [
        PasswordResetController::class, 'reset',
    ])->name('password.update');


    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    // Route::post('/login', [AuthController::class, 'login']);
     Route::post('/login', [
        LoginController::class, 'store',
    ])->name('login');


    // Lab 4: pages linked from the password-reset notification (the POST handlers are built in Part D).
    Route::get('/forgot-password', fn () => view('auth.forgot-password'))->name('password.request');
    Route::get('/reset-password/{token}', fn (string $token) => view('auth.reset-password', [
        'token' => $token,
        'email' => request('email'),
    ]))->name('password.reset');
});

Route::middleware('auth')->group(function () {
    // Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('/logout', [
        LoginController::class, 'destroy',
    ])->name('logout');

    Route::get('/dashboard', fn () => redirect()->route('projects.index'))->name('dashboard');

    // Projects
    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('/projects/create', [ProjectController::class, 'create'])->name('projects.create');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('/projects/search', [ProjectSearchController::class, 'search'])->name('projects.search');
    Route::get('/projects/{id}', [ProjectController::class, 'show'])->name('projects.show');
    Route::get('/projects/{id}/edit', [ProjectController::class, 'edit'])->name('projects.edit');
    Route::put('/projects/{id}', [ProjectController::class, 'update'])->name('projects.update');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');

    // Comments (Lab 3)
    Route::post('/projects/{project}/comments', [CommentController::class, 'store'])->name('projects.comments.store');

    // Documents
    Route::post('/projects/{id}/documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('/documents/{id}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::get('/projects/{project}/documents/{document}', [DocumentController::class, 'downloadNested'])
        ->name('projects.documents.download');

    // Organization administration
    Route::middleware('org.admin')->group(function () {
        Route::get('/team', [TeamController::class, 'index'])->name('team.index');
        Route::patch('/team/{user}/role', [TeamController::class, 'updateRole'])->name('team.role');
    });
});
