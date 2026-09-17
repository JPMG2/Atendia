<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessIncomingWhatsAppMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives Evolution's event deliveries. Always answers 200 fast: the ack
 * only means "landed", the processing is queued — a slow reply here makes
 * Evolution pile up retries against us.
 */
class EvolutionWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if ($request->input('event') !== 'messages.upsert') {
            return response()->json(['handled' => false]);
        }

        $key = (array) $request->input('data.key', []);
        $remoteJid = (string) ($key['remoteJid'] ?? '');

        // Only direct texts from customers: own echoes would loop the
        // assistant against itself, and groups/broadcasts are not a customer
        // asking the business something.
        if (($key['fromMe'] ?? false) === true || ! str_ends_with($remoteJid, '@s.whatsapp.net')) {
            return response()->json(['handled' => false]);
        }

        $text = (string) ($request->input('data.message.conversation')
            ?? $request->input('data.message.extendedTextMessage.text', ''));

        if (trim($text) === '') {
            return response()->json(['handled' => false]);
        }

        ProcessIncomingWhatsAppMessage::dispatch(
            instance: (string) $request->input('instance', ''),
            from: (string) strstr($remoteJid, '@', true),
            senderName: (string) $request->input('data.pushName', ''),
            text: $text,
            messageId: (string) ($key['id'] ?? ''),
        );

        return response()->json(['handled' => true]);
    }
}
