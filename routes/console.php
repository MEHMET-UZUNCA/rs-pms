<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Günlük döviz kurları (TCMB kurları 15:30'da yayınlanır; sabah da bir kez çekilir). Sunucuda cron: * * * * * php artisan schedule:run
Schedule::command('pms:fetch-exchange-rates')->dailyAt('09:05')->timezone('Europe/Istanbul')->withoutOverlapping();
Schedule::command('pms:fetch-exchange-rates')->dailyAt('15:40')->timezone('Europe/Istanbul')->withoutOverlapping();

// KVKK: saklama süresi dolan misafir profilleri (Rezervasyon seçeneklerinde süre girilmişse) her ayın 1'inde gece anonimleştirilir
Schedule::command('pms:anonymize-profiles')->monthlyOn(1, '03:30')->timezone('Asia/Nicosia')->withoutOverlapping();

// Veritabanı yedeği: her gece 04:15 (gece kapanışından sonra) ve gün içinde 4 saatte bir (docs/15-sunucu-kurulum.md)
Schedule::command('pms:backup')->dailyAt('04:15')->timezone('Asia/Nicosia')->withoutOverlapping();
Schedule::command('pms:backup --keep=30')->cron('0 8,12,16,20 * * *')->timezone('Asia/Nicosia')->withoutOverlapping();
