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
