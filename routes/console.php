<?php

declare(strict_types=1);

use App\Models\PanelNotification;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Evening, when the day's conversations are in and the owner still reads.
// Every 15 minutes: each command picks the businesses whose LOCAL time is due
// (config atendia.schedule), so nobody gets a birthday greeting at 05:15.

// They all walk business by business over WhatsApp and the model: a slow tick
// still running when the next starts would re-pick rows it has not stamped yet
// and send the same reminder twice. A lock older than one tick is stale.
Schedule::command('atendia:whatsapp-digest')->everyFifteenMinutes()->withoutOverlapping(15);
// Monday morning: the week's learning recap opens the owner's planning.
Schedule::command('atendia:knowledge-digest')->everyFifteenMinutes()->withoutOverlapping(15);
Schedule::command('atendia:birthday-greetings')->everyFifteenMinutes()->withoutOverlapping(15);
Schedule::command('atendia:handoff-reminders')->everyTenMinutes()->withoutOverlapping(10);
// The day-before nudge for tomorrow's bookings; each one leaves once.
Schedule::command('atendia:appointment-reminders')->everyFifteenMinutes()->withoutOverlapping(15);
// Finished threads get read whole once: questions, who solved them, mood.
Schedule::command('atendia:analyze-conversations')->everyTenMinutes()->withoutOverlapping(10);
Schedule::command('atendia:retry-ai-backlog')->everyThirtyMinutes()->withoutOverlapping(30);

// The net under the connection webhook: a `close` lost to a bridge restart
// would otherwise leave the panel green over a number that answers nobody.
Schedule::command('atendia:whatsapp-reconcile')->everyFiveMinutes()->withoutOverlapping(5);

// Payment reminders (10 and 5 days), grace days and pausing unpaid assistants.
Schedule::command('atendia:billing-cycle')->everyFifteenMinutes()->withoutOverlapping(15);

// Nightly, off the busy hours: the bell's inbox drops what aged out of its
// window (config atendia.bell.keep_days). Nobody empties it by hand.
Schedule::command('model:prune', ['--model' => [PanelNotification::class]])->dailyAt('04:20');

// Weekly on purpose: an account that went quiet is a call to make this week,
// and a daily copy of the same list stops being read by the second one.
Schedule::command('atendia:adoption-alert')->dailyAt('09:00')->withoutOverlapping(60);

// The day before a team member's plazo for the second step ends: one mail, with time to act.
Schedule::command('atendia:staff-two-factor-warning')->dailyAt('09:05')->withoutOverlapping(30);

// The month's revenue, kept so next month has something to be compared with.
// Daily and before midnight: a server down on the 1st would otherwise lose
// that month's photo for good.
Schedule::command('atendia:revenue-snapshot')->dailyAt('23:40')->withoutOverlapping(30);

// What went wrong today, brought to her. Daily and in the evening, and quiet
// on a clean day: a nightly "nothing happened" teaches her to archive unread.
Schedule::command('atendia:incidents-digest')
    ->dailyAt((string) config('atendia.incidents.digest_time'))
    ->withoutOverlapping(30);

// Same reasoning, the other side of the ledger: a client whose AI is eating
// its own plan has to reach her before the month is spent, not when she next
// opens the screen. Weekly, and only while it is still over.
Schedule::command('atendia:ai-alert')->weeklyOn(1, '09:30')->withoutOverlapping(60);
