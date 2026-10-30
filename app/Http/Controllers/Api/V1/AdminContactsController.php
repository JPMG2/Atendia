<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PlatformContact;
use Illuminate\Http\JsonResponse;

/**
 * "Radar de personas" as the web prints it: the same model reading, the same
 * hundred people, the same words. Read only.
 */
class AdminContactsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $people = PlatformContact::radar();
        $reach = PlatformContact::reach();
        $unknown = __('contacts.unknown');

        return response()->json(['data' => [
            'title' => __('contacts.title'),
            'sub' => __('contacts.sub'),
            'read_at' => __('contacts.read_at', ['time' => now()->translatedFormat('d/m/Y H:i')]),
            'empty' => $people->isEmpty() ? ['title' => __('contacts.empty_title'), 'body' => __('contacts.empty_body')] : null,
            'cards' => [
                ['key' => 'people', 'label' => __('contacts.reach.people'), 'value' => $reach['people'], 'tint' => 'brand'],
                ['key' => 'shared', 'label' => __('contacts.reach.shared'), 'value' => $reach['shared'], 'tint' => 'accent'],
                ['key' => 'conversations', 'label' => __('contacts.reach.conversations'), 'value' => $reach['conversations'], 'tint' => 'info'],
                ['key' => 'countries', 'label' => __('contacts.reach.countries'), 'value' => $reach['countries'], 'tint' => 'warning'],
            ],
            // Said out loud instead of a zero to interpret, as the web does.
            'none_shared' => $people->isNotEmpty() && $reach['shared'] === 0 ? __('contacts.none_shared') : null,
            'labels' => [
                'businesses' => __('contacts.table.businesses'),
                'conversations' => __('contacts.table.conversations'),
                'country' => __('contacts.table.country'),
                'last_seen' => __('contacts.table.last_seen'),
            ],
            'rows' => $people->map(fn (PlatformContact $person): array => [
                'id' => $person->id,
                'phone' => $person->phone,
                'name' => $person->name ?? $unknown,
                'businesses' => $person->businesses_count,
                'shared' => $person->businesses_count > 1 ? __('contacts.shared_tag', ['count' => $person->businesses_count]) : null,
                'conversations' => $person->conversations_count,
                'country' => $person->country_code ?? $unknown,
                'last_seen' => $person->last_activity_at?->translatedFormat('d/m/Y') ?? $unknown,
            ])->values()->all(),
        ]]);
    }
}
