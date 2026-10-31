<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Ai\Tools\AdminEmailBusiness;
use App\Classes\Main\AdminAskDesk;
use App\Classes\Main\AskDesk;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AdminAskDraftRequest;
use App\Http\Requests\Api\V1\AdminAskQuestionRequest;
use App\Models\Company;
use App\Models\Menu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The admin's "Asistente IA" on the phone: the web panel's own piece, words
 * and daily cap. The thread is the SDK's stored conversation, as on the web;
 * the phone only carries its id back, and the agent checks whose it is.
 */
class AdminAskController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $desk = new AdminAskDesk($request->user());
        $screens = Menu::adminAppScreens();
        // The conversation the phone was following, read back from the SDK's store; anything not hers is no thread at all.
        $following = Str::isUuid($request->query('conversation')) ? (string) $request->query('conversation') : null;
        $thread = $desk->thread($following);

        return response()->json(['data' => [
            'title' => __('ask_admin.title'),
            'subtitle' => __('ask_admin.subtitle'),
            'hello' => __('ask_admin.hello', ['name' => $desk->firstName]),
            'intro' => __('ask_admin.intro', ['brand' => Company::brand()]),
            'today' => [
                'title' => __('ask_admin.today.title'),
                'clear' => __('ask_admin.today.clear'),
                'rows' => array_map(fn (array $item): array => [
                    'key' => $item['key'],
                    'icon' => $item['icon'],
                    'label' => trans_choice('ask_admin.today.'.$item['key'], $item['count'], ['count' => $item['count']]),
                    // Only to a screen the app has and this person may open: a row that leads nowhere is drawn without its arrow.
                    'target' => in_array($item['route'], $screens, true) ? ['screen' => $item['route'], 'query' => []] : null,
                ], $desk->attention->items),
            ],
            'thread' => array_map(fn (array $turn): array => $turn['role'] === 'owner'
                ? ['role' => 'admin', 'text' => $turn['text']]
                : ['role' => 'ai', 'text' => $turn['text'], 'parts' => AskDesk::appParts($turn['text'], $screens)], $thread),
            'conversation' => $thread === [] ? null : $following,
            // An e-mail the run paused on is drawn again, waiting for her, wherever she left it.
            'draft' => $thread === [] ? null : $this->draftCard($desk->pendingDraft($following)),
            'draft_words' => $this->draftWords(),
            'report_browser' => __('ask_admin.report.browser'),
            'suggestions_title' => __('ask_admin.suggestions_title'),
            'suggestions' => $desk->suggestions,
            'placeholder' => __('ask_admin.placeholder'),
            'send' => __('ask_admin.send'),
            'thinking' => __('ask_admin.thinking'),
            'new_chat' => __('ask_admin.new_chat'),
            'max' => AdminAskDesk::QUESTION_MAX,
            ...$this->quota($desk),
        ]]);
    }

    public function ask(AdminAskQuestionRequest $request): JsonResponse
    {
        $desk = new AdminAskDesk($request->user());

        if ($desk->questionsLeft === 0) {
            return response()->json(['message' => $desk->usedUp, 'code' => 'ask_used_up', 'data' => $this->quota($desk)], 429);
        }

        // A run paused on an e-mail is decided first, as the web's panel asks: a new question would drop the draft unseen.
        if ($desk->pendingDraft($request->conversation()) !== null) {
            return response()->json(['message' => __('ask_admin.draft.waiting'), 'code' => 'draft_waiting'], 409);
        }

        $reply = $desk->answer($request->question(), $request->conversation());

        return response()->json(['data' => [
            'answer' => $reply === null || blank($reply['text']) ? null : [
                'text' => $reply['text'],
                'parts' => AskDesk::appParts($reply['text'], Menu::adminAppScreens()),
            ],
            'draft' => $this->draftCard($reply['draft'] ?? null),
            'failed' => $reply === null ? __('ask_admin.failed') : null,
            // The phone hands it back with the next question; the agent follows it only when it is this admin's.
            'conversation' => $reply['conversation'] ?? $request->conversation(),
            ...$this->quota($desk),
        ]]);
    }

    /**
     * Her decision on the drafted e-mail: sent (as she corrected it) or not. A
     * draft no longer waiting answers 409 with the web's sentence; nothing is sent twice.
     */
    public function decide(AdminAskDraftRequest $request): JsonResponse
    {
        $desk = new AdminAskDesk($request->user());
        $send = $request->boolean('send');

        // Sending runs the model once more: with today's cap spent it waits for tomorrow; not sending is always free.
        if ($send && $desk->questionsLeft === 0) {
            return response()->json(['message' => $desk->usedUp, 'code' => 'ask_used_up', 'data' => $this->quota($desk)], 429);
        }

        $decided = $desk->decide(
            $request->validated('conversation'),
            $request->validated('draft'),
            $send,
            (string) $request->validated('subject'),
            (string) $request->validated('message'),
        );

        if ($decided === null) {
            return response()->json(['message' => __('ask_admin.draft.gone'), 'code' => 'draft_gone'], 409);
        }

        return response()->json(['data' => [
            'outcome' => $decided['outcome'],
            'note' => __('ask_admin.draft.'.$decided['outcome']),
            'answer' => blank($decided['text']) ? null : [
                'text' => $decided['text'],
                'parts' => AskDesk::appParts($decided['text'], Menu::adminAppScreens()),
            ],
            'draft' => $this->draftCard($decided['draft']),
            'conversation' => $decided['conversation'],
        ]]);
    }

    /**
     * The draft as the phone draws it: no tool arguments travel, only what she reads and edits.
     *
     * @param  array<string, mixed>|null  $draft
     * @return array{id: string, business: string, recipients: int, to: string, subject: string, message: string}|null
     */
    private function draftCard(?array $draft): ?array
    {
        return $draft === null ? null : array_diff_key($draft, ['arguments' => true]);
    }

    /** @return array<string, string|int> */
    private function draftWords(): array
    {
        return [
            ...collect(['title', 'hint', 'to', 'subject', 'message', 'send', 'drop', 'sent', 'dropped', 'waiting'])
                ->mapWithKeys(fn (string $key): array => [$key => __('ask_admin.draft.'.$key)])
                ->all(),
            'subject_max' => AdminEmailBusiness::SUBJECT_MAX,
            'message_max' => AdminEmailBusiness::MESSAGE_MAX,
        ];
    }

    /**
     * The line under the box, or the sentence that replaces the box.
     *
     * @return array{quota: array{left: int, line: string}, used_up: string|null}
     */
    private function quota(AdminAskDesk $desk): array
    {
        return [
            'quota' => ['left' => $desk->questionsLeft, 'line' => trans_choice('ask_admin.left', $desk->questionsLeft, ['count' => $desk->questionsLeft])],
            'used_up' => $desk->questionsLeft > 0 ? null : $desk->usedUp,
        ];
    }
}
