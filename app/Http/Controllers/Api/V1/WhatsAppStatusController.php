<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The light: what the bridge lets us say about the link. `unverified` means
 * the bridge did not answer lately, so the last stamp is not proof.
 */
class WhatsAppStatusController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $business = $request->user()->business;

        abort_if($business === null, 403);

        return response()->json(['data' => [
            'state' => $business->linkState()->value,
            'number' => $business->whatsapp_number,
            'connected_since' => $business->whatsapp_connected_at,
            // As the web's "connected" card says it, on the business's clock.
            'since' => $business->whatsapp_connected_at === null ? null : [
                'label' => __('whatsapp.connected.since'),
                'at' => $business->whatsapp_connected_at->toImmutable()->setTimezone($business->localTimezone())->format('d/m/Y H:i'),
            ],
            // The web's paused card in its own words; lifting it stays on the web (new password, then a QR).
            'paused' => $business->isPausedForLostPhone() ? [
                'tag' => __('whatsapp.lost_phone.paused_tag'),
                'title' => __('whatsapp.lost_phone.paused_title'),
                'since' => [
                    'label' => __('whatsapp.lost_phone.paused_since'),
                    'at' => $business->lost_phone_paused_at->toImmutable()->setTimezone($business->localTimezone())->format('d/m/Y H:i'),
                ],
                'by' => $business->lostPhonePausedBy ? __('whatsapp.lost_phone.paused_by', ['name' => $business->lostPhonePausedBy->name]) : null,
                'effects' => __('whatsapp.lost_phone.effects'),
                'billing' => __('whatsapp.lost_phone.billing'),
                'resume' => __('whatsapp.lost_phone.app_resume'),
            ] : null,
        ]]);
    }
}
