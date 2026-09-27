<?php

declare(strict_types=1);

namespace App\Http\Controllers\Conversations;

use App\Http\Controllers\Controller;
use App\Models\ConversationMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a file a customer sent, from the PRIVATE disk. The tenant scope
 * resolves the message, so a business only ever reaches its own. Only a
 * known photo type opens inline: a customer's file is untrusted markup.
 */
class MessageMediaController extends Controller
{
    public function __invoke(Request $request, ConversationMessage $message, int $index): StreamedResponse
    {
        $item = $message->media[$index] ?? null;
        $path = is_array($item) ? ($item['path'] ?? null) : null;

        abort_if(! is_string($path) || ! Storage::disk('local')->exists($path), 404);

        $inline = $item['kind'] === 'image' && in_array($item['mime'] ?? '', ['image/jpeg', 'image/png', 'image/webp'], true);
        $name = (string) ($item['name'] ?? basename($path));

        return Storage::disk('local')->response($path, $name, [
            'Content-Type' => $inline ? $item['mime'] : 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ], $inline && ! $request->boolean('download') ? 'inline' : 'attachment');
    }
}
