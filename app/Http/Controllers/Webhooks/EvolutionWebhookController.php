<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessIncomingWhatsAppMessage;
use App\Models\Business;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
    private const int DEBOUNCE_SECONDS = 4;

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
                audioSeconds: (int) $request->input('data.message.audioMessage.seconds', 0),
            );

            return true;
        }

        $media = $this->inboundMedia($request);

        $text = $media !== null ? $media['caption'] : (string) ($request->input('data.message.conversation')
            ?? $request->input('data.message.extendedTextMessage.text', ''));

        if ($media === null && trim($text) === '') {
            return false;
        }

        $sender = "{$instance}:{$from}";

        // Locked: two webhooks of one burst run in parallel under php-fpm, and
        // an unlocked read-append-write dropped one of the texts. A photo joins
        // the same burst: "foto" + "¿tienen esto?" is one question.
        Cache::lock("wa:buflock:{$sender}", 5)->block(3, function () use ($sender, $text, $media, $messageId): void {
            $buffered = (array) Cache::get("wa:buf:{$sender}", []);
            $buffered[] = $media ?? $text;
            Cache::put("wa:buf:{$sender}", $buffered, now()->addMinutes(3));
            Cache::put("wa:last:{$sender}", $messageId, now()->addMinutes(3));
        });

        ProcessIncomingWhatsAppMessage::dispatch(
            instance: $instance, from: $from, senderName: $senderName,
            text: $text, messageId: $messageId,
            // The owner answering a ping by quoting it: the quote names the customer.
            quotedMessageId: $request->input('data.message.extendedTextMessage.contextInfo.stanzaId'),
            media: $media,
        )->delay(self::DEBOUNCE_SECONDS);

        return true;
    }

    /** Extensions a stored file may carry; anything else lands as .bin so it is never served as markup. */
    private const array MEDIA_EXTENSIONS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];

    /**
     * A photo, a document or a location. The bytes are parked on the private
     * disk right here: a base64 photo inside the cache buffer and the job
     * payload would weigh megabytes each.
     *
     * @return array{kind: string, tmp: ?string, mime: string, name: string, caption: string, lat: ?float, lng: ?float, label: string}|null
     */
    private function inboundMedia(Request $request): ?array
    {
        $message = (array) $request->input('data.message', []);
        $blank = ['tmp' => null, 'mime' => '', 'name' => '', 'caption' => '', 'lat' => null, 'lng' => null, 'label' => ''];

        if (is_array($location = $message['locationMessage'] ?? null)) {
            return ['kind' => 'location', ...$blank,
                'lat' => (float) ($location['degreesLatitude'] ?? 0),
                'lng' => (float) ($location['degreesLongitude'] ?? 0),
                'label' => implode(', ', array_filter([trim((string) ($location['name'] ?? '')), trim((string) ($location['address'] ?? ''))])),
            ];
        }

        $kind = isset($message['imageMessage']) ? 'image' : 'document';
        $part = $message['imageMessage'] ?? $message['documentMessage'] ?? $message['documentWithCaptionMessage']['message']['documentMessage'] ?? null;

        if (! is_array($part)) {
            return null;
        }

        $mime = strtolower((string) ($part['mimetype'] ?? ''));
        $bytes = base64_decode((string) ($message['base64'] ?? ''), true);
        $tmp = null;

        // Evolution can skip the base64 on a heavy file: the message still
        // lands, as a placeholder the team can open on their phone.
        if (is_string($bytes) && $bytes !== '') {
            $tmp = 'wa-inbound/'.Str::uuid().'.'.(self::MEDIA_EXTENSIONS[$mime] ?? 'bin');
            Storage::disk('local')->put($tmp, $bytes);
        }

        return ['kind' => $kind, ...$blank,
            'tmp' => $tmp,
            'mime' => $mime,
            'name' => mb_substr(trim((string) ($part['fileName'] ?? '')), 0, 120),
            'caption' => trim((string) ($part['caption'] ?? '')),
        ];
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
