<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Classes\Main\Client;
use App\Classes\Main\Inbox;
use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Read-only on purpose: WhatsApp is where the conversation lives. */
class ConversationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['cursor' => ['sometimes', 'string', 'max:500']]);

        return ConversationResource::collection(
            $this->inbox($request)->page((int) config('atendia.app.conversations_per_page'), $request->query('cursor'))
        );
    }

    public function show(Request $request, int $conversation): JsonResponse
    {
        $request->validate(['before' => ['sometimes', 'integer', 'min:1']]);

        $inbox = $this->inbox($request);
        $before = $request->integer('before') ?: null;
        $thread = $inbox->thread($conversation, (int) config('atendia.app.messages_per_page'), $before);

        abort_if($thread === null, 404);

        $oldest = $thread->messages->first();

        return ConversationResource::make($thread)
            ->additional(['meta' => [
                'has_older' => $oldest !== null && $inbox->hasMessagesBefore($thread->id, $oldest->id),
            ]])
            ->response();
    }

    private function inbox(Request $request): Inbox
    {
        $inbox = Client::for($request->user())->inbox;

        abort_if($inbox === null, 403);

        return $inbox;
    }
}
