<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Classes\Main\AdminAskDesk;
use App\Classes\Main\AskDesk;
use App\Http\Controllers\Controller;
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

        $reply = $desk->answer($request->question(), $request->conversation());

        return response()->json(['data' => [
            'answer' => $reply === null ? null : [
                'text' => $reply['text'],
                'parts' => AskDesk::appParts($reply['text'], Menu::adminAppScreens()),
            ],
            'failed' => $reply === null ? __('ask_admin.failed') : null,
            // The phone hands it back with the next question; the agent follows it only when it is this admin's.
            'conversation' => $reply['conversation'] ?? $request->conversation(),
            ...$this->quota($desk),
        ]]);
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
