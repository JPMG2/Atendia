<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Support\AnswerSupportTicket;
use App\Classes\Main\SupportCase;
use App\Classes\Main\SupportHelpDigest;
use App\Classes\Main\SupportQueue;
use App\Http\Controllers\Controller;
use App\Livewire\Forms\Admin\SupportReplyForm;
use App\Models\SupportReply;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

/**
 * The support desk as the web prints it, from the same pieces and in the same words. Replying and resolving
 * run the web's own Action (`AnswerSupportTicket`) with its rules; taking a report and blocking it stay on
 * the web; the file a business attached is announced, never served.
 */
class AdminSupportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $queue = $request->query('queue');
        $queue = is_string($queue) && in_array($queue, SupportQueue::QUEUES, true) ? $queue : 'answer';
        $pages = max(1, min((int) (SupportQueue::CEILING / SupportQueue::PAGE), (int) $request->query('page', 1)));

        // A kind the desk does not know is no filter at all: it must not be reported as one.
        $kind = $this->text($request, 'kind');

        $desk = new SupportQueue(
            $queue,
            array_key_exists($kind, (new SupportQueue)->kindOptions) ? $kind : '',
            $this->digits($request, 'business'),
            $this->who($request),
            mb_substr(trim($this->text($request, 'search')), 0, 100),
            SupportQueue::PAGE * $pages,
        );

        return response()->json(['data' => [
            ...$desk->head,
            'queue' => $queue,
            'tabs' => $desk->tabs,
            'filters' => $desk->filters,
            'applied' => ['kind' => $desk->kind, 'business' => $desk->business, 'who' => $desk->who, 'search' => $desk->search],
            'is_filtered' => $desk->isFiltered,
            'labels' => $desk->labels,
            'empty' => $desk->empty,
            // At the web's own ceiling the app is told there is no more: a "load more" that cannot advance must not be drawn.
            'has_more' => $desk->hasMore && $pages < (int) (SupportQueue::CEILING / SupportQueue::PAGE),
            'rows' => $desk->rows,
        ]]);
    }

    public function help(): JsonResponse
    {
        $digest = new SupportHelpDigest;

        return response()->json(['data' => [
            'empty' => $digest->empty,
            'deflection' => $digest->deflectionCard,
            'failing' => $digest->failingCard,
            'stale' => $digest->staleCard,
            'pain' => $digest->painCard,
        ]]);
    }

    public function show(int $ticket): JsonResponse
    {
        $case = SupportCase::find($ticket);

        abort_if($case === null, 404);

        return response()->json(['data' => [
            'id' => $case->ticket->id,
            'head' => $case->head,
            'state' => $case->state,
            'handling' => $case->handling,
            'panels' => $case->panels,
            'thread' => $case->thread,
            'ai' => $case->ai,
            'handoff' => $case->handoff,
            'context' => $case->context,
            'notes' => $case->notesSheet,
            // Null once the business closed its account: the report stays readable, the customer is gone.
            'customer' => $case->customerSheet,
            'settled' => $case->settled,
            'composer' => $case->composer,
        ]]);
    }

    /**
     * Answers the business, as the web's box: the outcome says by which road it left, or that it did not.
     * The phone names each answer (`request_id`): the same one sent again after a lost response gets the
     * first outcome back and is never sent twice.
     */
    public function reply(Request $request, AnswerSupportTicket $answer, int $ticket): JsonResponse
    {
        $case = $this->openCase($ticket);
        $validated = Validator::make(['body' => trim((string) (is_string($request->input('body')) ? $request->input('body') : ''))], SupportReplyForm::fieldRules(), [], SupportReplyForm::fieldNames())->validate();

        $send = function () use ($answer, $case, $validated, $request): array {
            $delivery = $answer->reply($case->ticket, $validated['body'], $request->user());

            return ['message' => $delivery->outcome(), 'reached' => $delivery->reached()];
        };

        $requestId = $request->input('request_id');

        if (! is_string($requestId) || preg_match('/^[A-Za-z0-9-]{8,64}$/', $requestId) !== 1) {
            return response()->json($send());
        }

        // Two taps at once wait on the same lock: the second reads the first one's outcome.
        $key = "support-reply:{$ticket}:{$requestId}";
        $outcome = Cache::lock($key.':lock', 30)->block(20, fn (): array => Cache::get($key) ?? tap($send(), fn (array $sent) => Cache::put($key, $sent, now()->addHour())));

        return response()->json($outcome);
    }

    /** A saved reply's text as it is NOW, as the web reads it when it is picked; null once it was switched off. */
    public function savedReply(int $reply): JsonResponse
    {
        return response()->json(['data' => ['text' => SupportReply::bodyOf($reply)]]);
    }

    public function resolve(AnswerSupportTicket $answer, int $ticket): JsonResponse
    {
        // Resolving is not just a column: it rings the business's bell.
        $answer->resolve($this->openCase($ticket)->ticket);

        return response()->json(['message' => __('support.admin.saved')]);
    }

    /** A report the box may still act on: gone is 404, settled is 409 (the web shows "Reabrir" there instead). */
    private function openCase(int $ticket): SupportCase
    {
        $case = SupportCase::find($ticket);

        abort_if($case === null, 404, __('notifications.not_found'));
        abort_if($case->settled !== null, 409, $case->settled);

        return $case;
    }

    /** A query value only when it is a plain string: `?kind[]=x` is read as nothing, never as a 500. */
    private function text(Request $request, string $key): string
    {
        $value = $request->query($key);

        return is_string($value) ? $value : '';
    }

    private function digits(Request $request, string $key): string
    {
        $value = $this->text($request, $key);

        return ctype_digit($value) && strlen($value) <= 18 ? $value : '';
    }

    /** A user id, or "none" for the reports nobody has. */
    private function who(Request $request): string
    {
        $value = $this->text($request, 'who');

        return $value === 'none' ? 'none' : $this->digits($request, 'who');
    }
}
