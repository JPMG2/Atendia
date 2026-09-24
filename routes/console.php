<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Evening, when the day's conversations are in and the owner still reads.
// Every 15 minutes: each command picks the businesses whose LOCAL time is due
// (config atendia.schedule), so nobody gets a birthday greeting at 05:15.
Schedule::command('atendia:whatsapp-digest')->everyFifteenMinutes();
// Monday morning: the week's learning recap opens the owner's planning.
Schedule::command('atendia:knowledge-digest')->everyFifteenMinutes();
Schedule::command('atendia:birthday-greetings')->everyFifteenMinutes();
Schedule::command('atendia:handoff-reminders')->everyTenMinutes();
// Finished threads get read whole once: questions, who solved them, mood.
Schedule::command('atendia:analyze-conversations')->everyTenMinutes();
Schedule::command('atendia:retry-ai-backlog')->everyThirtyMinutes();

// Payment reminders (10 and 5 days), grace days and pausing unpaid assistants.
Schedule::command('atendia:billing-cycle')->everyFifteenMinutes();
