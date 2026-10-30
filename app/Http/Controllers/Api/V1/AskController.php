<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Classes\Main\AskDesk;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AskQuestionRequest;
use App\Http\Requests\Api\V1\RateAnswerRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Pregúntale" on the phone: the web panel's own piece, words and quota. The
 * thread lives on the phone, as it lives in the web component: the server
 * keeps each answer only for a day, so it can be rated.
 */
class AskController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $desk = $this->desk($request);
        $screens = (array) config('atendia.app.screens');

        return response()->json(['data' => [
            'title' => __('ask.title'),
            'subtitle' => __('ask.subtitle'),
            'hello' => __('ask.hello', ['name' => $desk->firstName]),
            'intro' => $this->intro(),
            'today' => [
                'title' => __('ask.today.title'),
                'clear' => __('ask.today.clear'),
                'rows' => array_map(fn (array $item): array => [
                    'key' => $item['key'],
                    'icon' => $item['icon'],
                    'label' => trans_choice('ask.today.'.$item['key'], $item['count'], ['count' => $item['count']]),
                    // Only to a screen the app has: a row that leads nowhere is drawn without its arrow.
                    'target' => in_array($item['route'], $screens, true) ? ['screen' => $item['route'], 'query' => []] : null,
                ], $desk->attention),
            ],
            'suggestions_title' => __('ask.suggestions_title'),
            'suggestions' => $desk->suggestions,
            'placeholder' => __('ask.placeholder'),
            'send' => __('ask.send'),
            'thinking' => __('ask.thinking'),
            'rate' => ['up' => __('ask.rate.up'), 'down' => __('ask.rate.down'), 'thanks' => __('ask.rate.thanks')],
            'max' => AskDesk::QUESTION_MAX,
            ...$this->quota($desk),
        ]]);
    }

    public function ask(AskQuestionRequest $request): JsonResponse
    {
        $desk = $this->desk($request);

        if (! $desk->canAsk) {
            return response()->json(['message' => $this->usedUp($desk), 'code' => 'ask_used_up', 'data' => $this->quota($desk)], 429);
        }

        $question = $request->question();
        $answer = $desk->answer($question, $desk->thread($request->thread()));

        if ($desk->busy) {
            return response()->json(['message' => __('ask.busy'), 'code' => 'ask_busy'], 409);
        }

        if ($answer === null && ! $desk->canAsk) {
            return response()->json(['message' => $this->usedUp($desk), 'code' => 'ask_used_up', 'data' => $this->quota($desk)], 429);
        }

        return response()->json(['data' => [
            // The phone hands the id back as the thread of the next question; the server keeps the words.
            'answer' => $answer === null ? null : [
                'id' => $desk->remember($question, $answer),
                'text' => $answer,
                'parts' => AskDesk::appParts($answer, (array) config('atendia.app.screens')),
            ],
            'failed' => $answer === null ? __('ask.failed') : null,
            ...$this->quota($desk),
        ]]);
    }

    public function rate(RateAnswerRequest $request): JsonResponse
    {
        abort_unless($this->desk($request)->rate($request->string('id')->value(), $request->string('rating')->value()), 404);

        return response()->json(['message' => __('ask.rate.thanks')]);
    }

    /** The owner of a plan that opens it; anyone else gets the web's 403. */
    private function desk(Request $request): AskDesk
    {
        $desk = new AskDesk($request->user()?->business, $request->user());

        abort_unless($desk->mayAsk && $desk->plan->allowsAsk, 403);

        return $desk;
    }

    /**
     * The quota line under the box, or the sentence that replaces the box.
     *
     * @return array{quota: array{left: int, cap: int, before: string, after: string}, used_up: string|null}
     */
    private function quota(AskDesk $desk): array
    {
        return [
            'quota' => [
                'left' => $desk->questionsLeft,
                'cap' => $desk->plan->askPerMonth,
                'before' => __('ask.quota.left'),
                'after' => __('ask.quota.of_short', ['cap' => $desk->plan->askPerMonth]),
            ],
            'used_up' => $desk->questionsLeft > 0 ? null : $this->usedUp($desk),
        ];
    }

    private function usedUp(AskDesk $desk): string
    {
        return __('ask.used_up', ['cap' => $desk->plan->askPerMonth, 'date' => $desk->renewsOn]);
    }

    /**
     * The web's intro with its name lit up, as pieces: the phone draws the
     * name in jade and "IA" as the brand pill, from the same sentence.
     *
     * @return list<array{kind: 'text'|'brand'|'pill', text: string}>
     */
    private function intro(): array
    {
        [$before, $after] = explode('%A%', __('ask.intro', ['assistant' => '%A%'])) + [1 => ''];
        [$name, $tail] = explode('%I%', __('ask.assistant', ['ia' => '%I%'])) + [1 => ''];

        return array_values(array_filter([
            ['kind' => 'text', 'text' => $before],
            ['kind' => 'brand', 'text' => $name],
            ['kind' => 'pill', 'text' => __('ask.ia')],
            ['kind' => 'brand', 'text' => $tail],
            ['kind' => 'text', 'text' => $after],
        ], fn (array $part): bool => $part['text'] !== ''));
    }
}
