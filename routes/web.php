<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;

Route::middleware('guest')->group(function () {
    Route::get('/', [AuthController::class, 'index'])->name('login');
    Route::get('/login', fn () => redirect()->route('login'));
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');    
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');    
    Route::get('/perfil', [ProfileController::class, 'index'])->name('profile.index');    
    Route::post('/perfil', [ProfileController::class, 'store'])->name('profile.store');    
    Route::put('/perfil/update', [ProfileController::class, 'update'])->name('profile.update');    
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});