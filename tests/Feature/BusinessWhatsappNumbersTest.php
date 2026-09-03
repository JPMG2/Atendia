<?php

declare(strict_types=1);

use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The business's two WhatsApp numbers
|--------------------------------------------------------------------------
| The AI answers on the business's number; whatever it cannot (or should
| not) answer is handed over to the fallback one — a human's phone. Two
| separate columns on purpose: they play different roles.
*/

test('a business holds the number the AI answers on and a fallback for handover', function (): void {
    $business = Business::factory()->create([
        'whatsapp_number' => '+54 9 341 512 4408',
        'fallback_whatsapp_number' => '+54 9 341 555 0199',
    ]);

    $business->refresh();

    expect($business->whatsapp_number)->toBe('+54 9 341 512 4408')
        ->and($business->fallback_whatsapp_number)->toBe('+54 9 341 555 0199');
});

test('both numbers are optional until wizard step 5 or the panel fills them', function (): void {
    $business = Business::factory()->create();

    expect($business->whatsapp_number)->toBeNull()
        ->and($business->fallback_whatsapp_number)->toBeNull();
});

test('changing either number lands in the audit trail', function (): void {
    $business = Business::factory()->create();

    $business->update(['fallback_whatsapp_number' => '+54 9 341 555 0199']);

    $lastLog = Activity::inLog('business')
        ->where('subject_id', $business->id)
        ->latest('id')
        ->first();

    // In v5 the changes live in `attribute_changes`, not in `properties`.
    expect($lastLog->attribute_changes['attributes'])->toHaveKey('fallback_whatsapp_number');
});
