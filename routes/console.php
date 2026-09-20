<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Evening, when the day's conversations are in and the owner still reads.
Schedule::command('atendia:whatsapp-digest')->dailyAt('20:30');
Schedule::command('atendia:birthday-greetings')->dailyAt('09:15');
