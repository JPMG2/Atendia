<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Classes\Main\SupportCase;
use App\Classes\Main\SupportHelpDigest;
use App\Classes\Main\SupportQueue;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The support desk as the web prints it, from the same pieces and in the same words. Read only: replying,
 * taking a report, blocking and resolving move a business's case and message it, so they wait for the
 * delivery of actions; the file a business attached is announced, never served.
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
        ]]);
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
