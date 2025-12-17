<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;

Route::middleware('guest')->group(function () {
    Route::get('/', [AuthController::class, 'index'])->name('login');
    Route::get('/login', fn () => redirect()->route('login'));
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');    
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');  

    Route::get('/perfil', [ProfileController::class, 'index'])->name('profile.index');
    Route::put('/perfil/update', [ProfileController::class, 'update'])->name('profile.update');

    Route::middleware('admin')->group(function () {
        Route::get('/usuarios', [UserController::class, 'index'])->name('users.index');    
        Route::post('/usuarios', [UserController::class, 'store'])->name('users.store');    
        Route::put('/usuarios/{user}', [UserController::class, 'updateUser'])->name('users.updateUser');   
        Route::delete('/usuarios/{user}', [UserController::class, 'destroy'])->name('users.destroy');   
    });

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});