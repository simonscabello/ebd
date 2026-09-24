<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Lembretes push, no fuso da igreja (config/ebd.php). Em produção um serviço
| separado roda `php artisan schedule:work`; ver README > Deploy no Railway.
*/
$timezone = (string) config('ebd.timezone');

Schedule::command('ebd:remind-readings morning')->dailyAt('09:00')->timezone($timezone)->onOneServer()->withoutOverlapping();
Schedule::command('ebd:remind-readings evening')->dailyAt('20:00')->timezone($timezone)->onOneServer()->withoutOverlapping();
Schedule::command('ebd:remind-lesson')->saturdays()->at('08:00')->timezone($timezone)->onOneServer()->withoutOverlapping();
