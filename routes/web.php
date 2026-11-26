<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Cashiering\BillingController;
use App\Http\Controllers\FrontOffice\RoomRackController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Miscellaneous\NightAuditController;
use App\Http\Controllers\Reservations\NavigatorController;
use App\Http\Controllers\Reservations\ReservationCardController;
use App\Http\Controllers\Setup\OutletController;
use App\Http\Controllers\Setup\TransactionCodeController;
use Illuminate\Support\Facades\Route;

// Dil seçimi (girişten önce de çalışır; girişli kullanıcıda kalıcı olarak kaydedilir)
Route::post('/dil/{locale}', function (string $locale) {
    abort_unless(array_key_exists($locale, config('app.locales')), 404);
    session(['locale' => $locale]);
    auth()->user()?->update(['locale' => $locale]);

    return back();
})->name('locale');

Route::middleware('guest')->group(function () {
    Route::get('/giris', [LoginController::class, 'show'])->name('login');
    Route::post('/giris', [LoginController::class, 'login'])->middleware('throttle:20,1');
});

Route::middleware(['auth', 'property'])->group(function () {
    Route::post('/cikis', [LoginController::class, 'logout'])->name('logout');
    Route::get('/', HomeController::class)->name('home');

    // Çeşitli → Gece Kapanışı (Night Audit)
    Route::middleware('permission:night_audit.view')->prefix('gece-kapanisi')->name('night-audit.')->group(function () {
        Route::get('/', [NightAuditController::class, 'index'])->name('index');
        Route::post('/', [NightAuditController::class, 'run'])->middleware('permission:night_audit.run')->name('run');
    });

    // Ön Büro → Oda Planı (Room Rack)
    Route::get('/oda-plani', [RoomRackController::class, 'index'])->middleware('permission:reservations.view')->name('front-office.room-rack');

    // Rezervasyon Navigatörü
    Route::middleware('permission:reservations.view')->prefix('rezervasyon')->name('reservations.')->group(function () {
        Route::get('/', [NavigatorController::class, 'index'])->name('navigator');
        Route::middleware('permission:reservations.edit')->group(function () {
            Route::get('/yeni', [ReservationCardController::class, 'create'])->name('create');
            Route::post('/', [ReservationCardController::class, 'store'])->name('store');
            Route::get('/profiller', [ReservationCardController::class, 'profiles'])->name('profiles');
            Route::get('/{reservation}', [ReservationCardController::class, 'edit'])->whereNumber('reservation')->name('edit');
            Route::put('/{reservation}', [ReservationCardController::class, 'update'])->whereNumber('reservation')->name('update');
        });
        Route::get('/{reservation}/ozet', [NavigatorController::class, 'summary'])->name('summary');
        Route::get('/hesap/{folio}/ozet', [NavigatorController::class, 'folioSummary'])->name('folio-summary');
        Route::post('/{reservation}/giris', [NavigatorController::class, 'checkIn'])->middleware('permission:reservations.checkin')->name('check-in');
    });

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

    // Ayarlar → Kasa → Departman Kodları
    Route::middleware('permission:setup.transaction_codes')->prefix('ayarlar/departman-kodlari')->name('setup.transaction-codes.')->group(function () {
        Route::get('/', [TransactionCodeController::class, 'index'])->name('index');
        Route::get('/yeni', [TransactionCodeController::class, 'create'])->name('create');
        Route::post('/', [TransactionCodeController::class, 'store'])->name('store');
        Route::get('/{code}/duzenle', [TransactionCodeController::class, 'edit'])->name('edit');
        Route::get('/{code}/kopyala', [TransactionCodeController::class, 'copy'])->name('copy');
        Route::put('/{code}', [TransactionCodeController::class, 'update'])->name('update');
        Route::delete('/{code}', [TransactionCodeController::class, 'destroy'])->name('destroy');
        Route::post('/{code}/tasi/{direction}', [TransactionCodeController::class, 'move'])->whereIn('direction', ['up', 'down'])->name('move');
    });

    // Kasa → Folyo (Billing)
    Route::middleware('permission:cashiering.billing')->prefix('folyo')->name('billing.')->group(function () {
        Route::get('/rezervasyon/{reservation}', [BillingController::class, 'reservation'])->name('reservation');
        Route::get('/hesap/{folio}', [BillingController::class, 'account'])->name('account');
        Route::post('/kayit', [BillingController::class, 'post'])->middleware('permission:cashiering.post')->name('post');
        Route::post('/odeme', [BillingController::class, 'payment'])->middleware('permission:cashiering.post')->name('payment');
        Route::post('/kayit/{posting}/duzelt', [BillingController::class, 'correct'])->middleware('permission:cashiering.reverse')->name('correct');
        Route::post('/kayit/{posting}/aktar', [BillingController::class, 'transfer'])->middleware('permission:cashiering.post')->name('transfer');
        Route::post('/rezervasyon/{reservation}/cikis', [BillingController::class, 'checkOut'])->middleware('permission:reservations.checkout')->name('check-out');
    });
});
