<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Cashiering\BillingController;
use App\Http\Controllers\Cashiering\CashierController;
use App\Http\Controllers\Cashiering\FinancialAccountController;
use App\Http\Controllers\Cashiering\InvoiceController;
use App\Http\Controllers\FrontOffice\GuestRequestController;
use App\Http\Controllers\FrontOffice\HouseStatusController;
use App\Http\Controllers\FrontOffice\LostFoundController;
use App\Http\Controllers\FrontOffice\QueueController;
use App\Http\Controllers\FrontOffice\RoomMoveController;
use App\Http\Controllers\FrontOffice\RoomRackController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Housekeeping\HousekeepingController;
use App\Http\Controllers\Housekeeping\RoomsManagementController;
use App\Http\Controllers\Miscellaneous\NightAuditController;
use App\Http\Controllers\Profiles\ProfileController;
use App\Http\Controllers\Reservations\AvailabilityController;
use App\Http\Controllers\Reservations\BlockController;
use App\Http\Controllers\Reservations\ConfirmationLetterController;
use App\Http\Controllers\Reservations\DepositController;
use App\Http\Controllers\Reservations\FixedChargeController;
use App\Http\Controllers\Reservations\MealPlanController;
use App\Http\Controllers\Reservations\NavigatorController;
use App\Http\Controllers\Reservations\OverbookingController;
use App\Http\Controllers\Reservations\ReservationCardController;
use App\Http\Controllers\Reservations\ReservationItemController;
use App\Http\Controllers\Setup\AuditLogController;
use App\Http\Controllers\Setup\ExchangeRateController;
use App\Http\Controllers\Setup\ItemController;
use App\Http\Controllers\Setup\OutletController;
use App\Http\Controllers\Setup\RateCodeController;
use App\Http\Controllers\Setup\ReservationOptionsController;
use App\Http\Controllers\Setup\RoleController;
use App\Http\Controllers\Setup\TransactionCodeController;
use App\Http\Controllers\Setup\UserController;
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
    Route::get('/sifre', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('/sifre', [PasswordController::class, 'update'])->middleware('throttle:10,1')->name('password.update');
    Route::get('/', HomeController::class)->name('home');

    // Çeşitli → Gece Kapanışı (Night Audit)
    Route::middleware('permission:night_audit.view')->prefix('gece-kapanisi')->name('night-audit.')->group(function () {
        Route::get('/', [NightAuditController::class, 'index'])->name('index');
        Route::post('/', [NightAuditController::class, 'run'])->middleware('permission:night_audit.run')->name('run');
        Route::put('/cevaplar', [NightAuditController::class, 'answers'])->middleware('permission:night_audit.override')->name('answers');
    });

    // Kat Hizmetleri (Housekeeping)
    Route::middleware('permission:housekeeping.view')->prefix('kat-hizmetleri')->name('housekeeping.')->group(function () {
        Route::get('/', [HousekeepingController::class, 'index'])->name('index');
        Route::post('/durum', [HousekeepingController::class, 'status'])->middleware('permission:housekeeping.status')->name('status');
        Route::post('/ariza', [HousekeepingController::class, 'outage'])->middleware('permission:housekeeping.outage')->name('outage');
        Route::post('/ariza/{outage}/kaldir', [HousekeepingController::class, 'release'])->whereNumber('outage')->middleware('permission:housekeeping.outage')->name('release');
    });

    // Ön Büro → Oda Planı (Room Rack)
    Route::get('/oda-plani', [RoomRackController::class, 'index'])->middleware('permission:reservations.view')->name('front-office.room-rack');
    // Front Desk → Guest Requests (istek / şikâyet)
    Route::middleware('permission:front_office.requests')->prefix('istekler')->name('requests.')->group(function () {
        Route::get('/', [GuestRequestController::class, 'index'])->name('index');
        Route::post('/', [GuestRequestController::class, 'store'])->name('store');
        Route::get('/{trace}', [GuestRequestController::class, 'show'])->whereNumber('trace')->name('show');
        Route::post('/{trace}/durum', [GuestRequestController::class, 'status'])->whereNumber('trace')->name('status');
        Route::post('/{trace}/not', [GuestRequestController::class, 'note'])->whereNumber('trace')->name('note');
    });
    Route::get('/oda-degisimleri', [RoomMoveController::class, 'index'])->middleware('permission:reservations.view')->name('room-moves.index');
    Route::post('/oda-degisimleri/{move}/neden', [RoomMoveController::class, 'reason'])->whereNumber('move')->middleware('permission:reservations.edit')->name('room-moves.reason');
    Route::post('/oda-plani/{reservation}/tasi', [RoomRackController::class, 'move'])->whereNumber('reservation')->middleware('permission:reservations.edit')->name('front-office.room-rack.move');

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
        Route::get('/{reservation}/onay-mektubu', [ConfirmationLetterController::class, 'show'])->name('letter');
        Route::post('/{reservation}/onay-mektubu', [ConfirmationLetterController::class, 'send'])->middleware('permission:reservations.edit')->name('letter-send');
        Route::get('/hesap/{folio}/ozet', [NavigatorController::class, 'folioSummary'])->name('folio-summary');
        Route::post('/tercihler', [NavigatorController::class, 'preferences'])->name('preferences');
        Route::post('/{reservation}/geri-al', [NavigatorController::class, 'reinstate'])->whereNumber('reservation')->middleware('permission:reservations.edit')->name('reinstate');
        Route::post('/{reservation}/giris-geri-al', [NavigatorController::class, 'undoCheckIn'])->whereNumber('reservation')->middleware('permission:reservations.checkin')->name('undo-check-in');
        Route::post('/{reservation}/iptal', [NavigatorController::class, 'cancel'])->whereNumber('reservation')->middleware('permission:reservations.edit')->name('cancel');
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

    // Quick Keys → All Shortcuts
    Route::view('/kisayollar', 'shortcuts')->name('shortcuts');

    // Rooms Management → Out of Order, Room History
    Route::get('/arizali-odalar', [RoomsManagementController::class, 'outOfOrder'])->middleware('permission:housekeeping.view')->name('rooms.out-of-order');
    Route::get('/oda-gecmisi', [RoomsManagementController::class, 'history'])->name('rooms.history');

    // Rooms Management → House Status (Ctrl+H)
    Route::get('/house-status', HouseStatusController::class)->name('front-office.house-status');

    // Customer Relation → Profiles (Profil Navigatörü)
    Route::middleware('permission:profiles.view')->prefix('profiller')->name('profiles.')->group(function () {
        Route::get('/', [ProfileController::class, 'index'])->name('index');
        Route::get('/{profile}/ozet', [ProfileController::class, 'summary'])->whereNumber('profile')->name('summary');
        Route::middleware('permission:profiles.edit')->group(function () {
            Route::get('/yeni', [ProfileController::class, 'choose'])->name('choose');
            Route::get('/yeni/{type}', [ProfileController::class, 'create'])->name('create');
            Route::post('/yeni/{type}', [ProfileController::class, 'store'])->name('store');
            Route::get('/{profile}/duzenle', [ProfileController::class, 'edit'])->whereNumber('profile')->name('edit');
            Route::put('/{profile}', [ProfileController::class, 'update'])->whereNumber('profile')->name('update');
            Route::get('/{profile}/birlestir', [ProfileController::class, 'mergeForm'])->whereNumber('profile')->name('merge-form');
            Route::post('/{profile}/birlestir', [ProfileController::class, 'merge'])->whereNumber('profile')->name('merge');
        });
    });

    // Multiboard: pansiyon değişikliği ve öğün sayıları
    Route::get('/overbooking', [OverbookingController::class, 'index'])->middleware('permission:reservations.view')->name('overbooking.index');
    Route::post('/overbooking', [OverbookingController::class, 'save'])->middleware('permission:reservations.overbooking')->name('overbooking.save');
    Route::get('/musaitlik', [AvailabilityController::class, 'index'])->middleware('permission:reservations.view')->name('availability.index');

    // Reservation → Block / Group Reservation
    Route::middleware('permission:reservations.view')->prefix('bloklar')->name('blocks.')->group(function () {
        Route::get('/', [BlockController::class, 'index'])->name('index');
        Route::get('/{block}', [BlockController::class, 'show'])->whereNumber('block')->name('show');
        Route::middleware('permission:reservations.edit')->group(function () {
            Route::get('/yeni', [BlockController::class, 'create'])->name('create');
            Route::post('/', [BlockController::class, 'store'])->name('store');
            Route::get('/{block}/duzenle', [BlockController::class, 'edit'])->whereNumber('block')->name('edit');
            Route::put('/{block}', [BlockController::class, 'update'])->whereNumber('block')->name('update');
            Route::post('/{block}/rezervasyonlar', [BlockController::class, 'pickUp'])->whereNumber('block')->name('pick-up');
            Route::post('/{block}/islem', [BlockController::class, 'bulk'])->whereNumber('block')->name('bulk');
        });
    });

    Route::post('/rezervasyon/{reservation}/depozito', [DepositController::class, 'store'])->whereNumber('reservation')->middleware('permission:reservations.edit')->name('reservations.deposits.store');
    Route::post('/rezervasyon/{reservation}/depozito/{deposit}', [DepositController::class, 'status'])->whereNumber(['reservation', 'deposit'])->middleware('permission:reservations.edit')->name('reservations.deposits.status');
    Route::post('/rezervasyon/{reservation}/envanter', [ReservationItemController::class, 'store'])->whereNumber('reservation')->middleware('permission:reservations.edit')->name('reservations.items.store');
    Route::delete('/rezervasyon/{reservation}/envanter/{item}', [ReservationItemController::class, 'destroy'])->whereNumber(['reservation', 'item'])->middleware('permission:reservations.edit')->name('reservations.items.destroy');
    Route::get('/ayarlar/degisiklik-kaydi', [AuditLogController::class, 'index'])->middleware('permission:setup.audit')->name('setup.audit');
    Route::middleware('permission:setup.options')->prefix('ayarlar/envanter')->name('setup.items.')->group(function () {
        Route::get('/', [ItemController::class, 'index'])->name('index');
        Route::post('/', [ItemController::class, 'store'])->name('store');
        Route::put('/{item}', [ItemController::class, 'update'])->whereNumber('item')->name('update');
        Route::delete('/{item}', [ItemController::class, 'destroy'])->whereNumber('item')->name('destroy');
    });
    Route::post('/rezervasyon/{reservation}/sabit-ucret', [FixedChargeController::class, 'store'])->whereNumber('reservation')->middleware('permission:reservations.edit')->name('reservations.fixed-charges.store');
    Route::delete('/rezervasyon/{reservation}/sabit-ucret/{charge}', [FixedChargeController::class, 'destroy'])->whereNumber(['reservation', 'charge'])->middleware('permission:reservations.edit')->name('reservations.fixed-charges.destroy');
    Route::post('/rezervasyon/{reservation}/pansiyon', [MealPlanController::class, 'change'])->whereNumber('reservation')->middleware('permission:reservations.edit')->name('reservations.board');
    Route::get('/ogun-sayilari', [MealPlanController::class, 'forecast'])->middleware('permission:reservations.view')->name('meal-plan.forecast');

    // Front Desk → Queue Rooms
    Route::middleware('permission:reservations.view')->group(function () {
        Route::get('/bekleyen-odalar', [QueueController::class, 'index'])->name('queue.index');
        Route::post('/rezervasyon/{reservation}/kuyruk', [QueueController::class, 'add'])->whereNumber('reservation')->name('queue.add');
        Route::delete('/rezervasyon/{reservation}/kuyruk', [QueueController::class, 'remove'])->whereNumber('reservation')->name('queue.remove');
    });

    // Front Desk → Lost & Found
    Route::middleware('permission:front_office.lost_found')->prefix('kayip-esya')->name('lost-found.')->group(function () {
        Route::get('/', [LostFoundController::class, 'index'])->name('index');
        Route::get('/yeni/{kind}', [LostFoundController::class, 'create'])->whereIn('kind', ['lost', 'found'])->name('create');
        Route::post('/yeni/{kind}', [LostFoundController::class, 'store'])->whereIn('kind', ['lost', 'found'])->name('store');
        Route::post('/eslestir', [LostFoundController::class, 'match'])->name('match');
        Route::get('/{item}', [LostFoundController::class, 'show'])->whereNumber('item')->name('show');
        Route::get('/{item}/duzenle', [LostFoundController::class, 'edit'])->whereNumber('item')->name('edit');
        Route::put('/{item}', [LostFoundController::class, 'update'])->whereNumber('item')->name('update');
        Route::delete('/{item}', [LostFoundController::class, 'destroy'])->whereNumber('item')->name('destroy');
        Route::post('/{item}/ayir', [LostFoundController::class, 'unmatch'])->whereNumber('item')->name('unmatch');
        Route::post('/{item}/kapat', [LostFoundController::class, 'resolve'])->whereNumber('item')->name('resolve');
        Route::post('/{item}/not', [LostFoundController::class, 'note'])->whereNumber('item')->name('note');
    });

    // Ön Büro → New Financial Account (PM hesabı)
    Route::middleware('permission:cashiering.accounts')->prefix('hesaplar')->name('accounts.')->group(function () {
        Route::get('/yeni', [FinancialAccountController::class, 'create'])->name('create');
        Route::post('/', [FinancialAccountController::class, 'store'])->name('store');
        Route::get('/{folio}/duzenle', [FinancialAccountController::class, 'edit'])->name('edit');
        Route::put('/{folio}', [FinancialAccountController::class, 'update'])->name('update');
    });

    // Ayarlar → Rate Management → Fiyat kodları (Fidelio Rates / Rate Edit)
    Route::middleware('permission:setup.rates')->prefix('ayarlar/fiyat-kodlari')->name('setup.rates.')->group(function () {
        Route::get('/', [RateCodeController::class, 'index'])->name('index');
        Route::get('/yeni', [RateCodeController::class, 'create'])->name('create');
        Route::post('/', [RateCodeController::class, 'store'])->name('store');
        Route::get('/{rate}/ozet', [RateCodeController::class, 'summary'])->name('summary');
        Route::get('/{rate}/duzenle', [RateCodeController::class, 'edit'])->name('edit');
        Route::post('/{rate}/kopyala', [RateCodeController::class, 'copy'])->name('copy');
        Route::put('/{rate}', [RateCodeController::class, 'update'])->name('update');
        Route::delete('/{rate}', [RateCodeController::class, 'destroy'])->name('destroy');
        Route::get('/{rate}/detay/yeni', [RateCodeController::class, 'detailCreate'])->name('details.create');
        Route::post('/{rate}/detay', [RateCodeController::class, 'detailStore'])->name('details.store');
        Route::get('/{rate}/detay/{detail}/duzenle', [RateCodeController::class, 'detailEdit'])->name('details.edit');
        Route::get('/{rate}/detay/{detail}/kopyala', [RateCodeController::class, 'detailCopy'])->name('details.copy');
        Route::put('/{rate}/detay/{detail}', [RateCodeController::class, 'detailUpdate'])->name('details.update');
        Route::delete('/{rate}/detay/{detail}', [RateCodeController::class, 'detailDestroy'])->name('details.destroy');
    });

    // Ayarlar → Kasa → Döviz kurları (Fidelio Exchange Rates)
    Route::middleware('permission:setup.exchange_rates')->prefix('ayarlar/doviz-kurlari')->name('setup.exchange.')->group(function () {
        Route::get('/', [ExchangeRateController::class, 'index'])->name('index');
        Route::post('/cek', [ExchangeRateController::class, 'fetch'])->name('fetch');
        Route::put('/kaynak', [ExchangeRateController::class, 'source'])->name('source');
        Route::get('/{currency}/duzenle', [ExchangeRateController::class, 'edit'])->where('currency', '[A-Z]{3}')->name('edit');
        Route::put('/{currency}', [ExchangeRateController::class, 'update'])->where('currency', '[A-Z]{3}')->name('update');
    });

    // Ayarlar → Rezervasyon seçenekleri (Fidelio Global Settings → Reservation)
    Route::middleware('permission:setup.options')->group(function () {
        Route::get('/ayarlar/rezervasyon-secenekleri', [ReservationOptionsController::class, 'edit'])->name('setup.options.reservation');
        Route::put('/ayarlar/rezervasyon-secenekleri', [ReservationOptionsController::class, 'update'])->name('setup.options.reservation.update');
    });

    // Ayarlar → Sistem → Kullanıcılar ve Roller
    Route::middleware('permission:setup.users')->prefix('ayarlar')->group(function () {
        Route::get('/kullanicilar', [UserController::class, 'index'])->name('setup.users.index');
        Route::get('/kullanicilar/yeni', [UserController::class, 'create'])->name('setup.users.create');
        Route::post('/kullanicilar', [UserController::class, 'store'])->name('setup.users.store');
        Route::get('/kullanicilar/{user}/duzenle', [UserController::class, 'edit'])->name('setup.users.edit');
        Route::put('/kullanicilar/{user}', [UserController::class, 'update'])->name('setup.users.update');
        Route::post('/kullanicilar/{user}/sifre', [UserController::class, 'resetPassword'])->name('setup.users.password');
        Route::get('/roller', [RoleController::class, 'index'])->name('setup.roles.index');
        Route::get('/roller/yeni', [RoleController::class, 'create'])->name('setup.roles.create');
        Route::post('/roller', [RoleController::class, 'store'])->name('setup.roles.store');
        Route::get('/roller/{role}/duzenle', [RoleController::class, 'edit'])->whereNumber('role')->name('setup.roles.edit');
        Route::get('/roller/{role}/kopyala', [RoleController::class, 'copy'])->whereNumber('role')->name('setup.roles.copy');
        Route::put('/roller/{role}', [RoleController::class, 'update'])->whereNumber('role')->name('setup.roles.update');
        Route::delete('/roller/{role}', [RoleController::class, 'destroy'])->whereNumber('role')->name('setup.roles.destroy');
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
        Route::get('/{folio}/yazdir', [InvoiceController::class, 'folio'])->whereNumber('folio')->name('print');
        Route::post('/{folio}/fatura', [InvoiceController::class, 'issue'])->whereNumber('folio')->middleware('permission:cashiering.post')->name('invoice');
    });
    Route::get('/fatura/{invoice}', [InvoiceController::class, 'show'])->whereNumber('invoice')->middleware('permission:cashiering.billing')->name('invoices.show');

    // Cashiering → Close Cashier
    Route::middleware('permission:cashiering.post')->prefix('kasa')->name('cashier.')->group(function () {
        Route::get('/', [CashierController::class, 'index'])->name('index');
        Route::post('/kapat', [CashierController::class, 'close'])->name('close');
        Route::get('/vardiya/{shift}', [CashierController::class, 'report'])->whereNumber('shift')->name('report');
    });
});
