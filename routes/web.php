<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Setup\OutletController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/giris', [LoginController::class, 'show'])->name('login');
    Route::post('/giris', [LoginController::class, 'login'])->middleware('throttle:20,1');
});

Route::middleware(['auth', 'property'])->group(function () {
    Route::post('/cikis', [LoginController::class, 'logout'])->name('logout');
    Route::get('/', HomeController::class)->name('home');

    // Ayarlar → Kasa → Outlet'ler
    Route::middleware('permission:setup.outlets')->prefix('ayarlar/outletler')->name('setup.outlets.')->group(function () {
        Route::get('/', [OutletController::class, 'index'])->name('index');
        Route::get('/yeni', [OutletController::class, 'create'])->name('create');
        Route::post('/', [OutletController::class, 'store'])->name('store');
        Route::get('/{outlet}/duzenle', [OutletController::class, 'edit'])->name('edit');
        Route::get('/{outlet}/kopyala', [OutletController::class, 'copy'])->name('copy');
        Route::put('/{outlet}', [OutletController::class, 'update'])->name('update');
        Route::delete('/{outlet}', [OutletController::class, 'destroy'])->name('destroy');
        Route::post('/{outlet}/tasi/{direction}', [OutletController::class, 'move'])->whereIn('direction', ['up', 'down'])->name('move');
    });
});
