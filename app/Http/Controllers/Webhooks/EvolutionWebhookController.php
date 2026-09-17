<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessIncomingWhatsAppMessage;
use App\Models\Business;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Receives Evolution's event deliveries. Always answers 200 fast: the ack
 * only means "landed", the processing is queued — a slow reply here makes
 * Evolution pile up retries against us.
 */
class EvolutionWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $handled = match ($request->input('event')) {
            'messages.upsert' => $this->queueIncomingMessage($request),
            'connection.update' => $this->recordConnectionState($request),
            default => false,
        };

        return response()->json(['handled' => $handled]);
    }

    /**
     * People type in bursts ("Hola" / "¿están?" / "¿tienen turnos?"): each
     * text lands in a short-lived buffer and the job waits out the debounce
     * window, so the burst gets ONE thought-out answer instead of three.
     */
    private const int DEBOUNCE_SECONDS = 8;

    private function queueIncomingMessage(Request $request): bool
    {
        $key = (array) $request->input('data.key', []);
        $remoteJid = (string) ($key['remoteJid'] ?? '');

        // Only direct messages from customers: own echoes would loop the
        // assistant against itself, and groups/broadcasts are not a customer
        // asking the business something.
        if (($key['fromMe'] ?? false) === true || ! str_ends_with($remoteJid, '@s.whatsapp.net')) {
            return false;
        }

        $instance = (string) $request->input('instance', '');
        $from = (string) strstr($remoteJid, '@', true);
        $senderName = (string) $request->input('data.pushName', '');
        $messageId = (string) ($key['id'] ?? '');

        // A voice note skips the buffer — nobody sends them in bursts — and
        // carries its audio along for the job to transcribe.
        if ($request->input('data.message.audioMessage') !== null) {
            $audio = (string) $request->input('data.message.base64', '');

            if ($audio === '') {
                return false;
            }

            ProcessIncomingWhatsAppMessage::dispatch(
                instance: $instance, from: $from, senderName: $senderName,
                text: '', messageId: $messageId, audioBase64: $audio,
            );

            return true;
        }

        $text = (string) ($request->input('data.message.conversation')
            ?? $request->input('data.message.extendedTextMessage.text', ''));

        if (trim($text) === '') {
            return false;
        }

        $sender = "{$instance}:{$from}";
        $buffered = (array) Cache::get("wa:buf:{$sender}", []);
        $buffered[] = $text;
        Cache::put("wa:buf:{$sender}", $buffered, now()->addMinutes(3));
        Cache::put("wa:last:{$sender}", $messageId, now()->addMinutes(3));

        ProcessIncomingWhatsAppMessage::dispatch(
            instance: $instance, from: $from, senderName: $senderName,
            text: $text, messageId: $messageId,
        )->delay(self::DEBOUNCE_SECONDS);

        return true;
    }

    /**
     * Keeps `whatsapp_connected_at` honest: `open` stamps it, `close` clears
     * it. The in-between `connecting` states change nothing — a blip while
     * Baileys resyncs must not flicker the dashboard to "disconnected".
     */
    private function recordConnectionState(Request $request): bool
    {
        $business = Business::forWhatsAppInstance((string) $request->input('instance', ''));

        if ($business === null) {
            return false;
        }

        $state = (string) $request->input('data.state', '');

        if ($state === 'open' && ! $business->isConnected()) {
            $business->update(['whatsapp_connected_at' => now()]);
        }

        if ($state === 'close' && $business->isConnected()) {
            $business->update(['whatsapp_connected_at' => null]);
        }

        return in_array($state, ['open', 'close'], true);
    }
}
