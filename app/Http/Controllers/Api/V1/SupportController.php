<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Support\CreateSupportTicket;
use App\Classes\Main\SupportDesk;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SaveSupportTicketRequest;
use App\Models\SupportTicketKind;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * "Soporte técnico" for the app: the web widget's own words and kinds, and the report written through
 * the same Action, so the team hears of it by the same mail and WhatsApp. Anyone with a business may
 * report, as in the web's top bar; the image the widget offers waits for the phone's picker.
 */
class SupportController extends Controller
{
    public function show(): JsonResponse
    {
        $desk = new SupportDesk;

        return response()->json(['data' => ['copy' => $desk->copy, 'kinds' => $desk->kinds, 'screens' => $desk->screens, 'kind' => $desk->defaultKind]]);
    }

    public function store(SaveSupportTicketRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->business === null) {
            throw ValidationException::withMessages(['body' => __('support.no_business')]);
        }

        $validated = $request->validated();
        try {
            $ticket = app(CreateSupportTicket::class)->handle(
                user: $user,
                body: $validated['body'],
                kind: SupportTicketKind::byCode($validated['kind']),
                screen: $validated['screen'],
                context: $this->context($request),
            );
        } catch (Throwable $e) {
            // The web widget says "could not send, try again": the phone says it too, never a bare 500.
            report($e);

            return response()->json(['message' => __('support.failed')], 500);
        }

        // `thanks` is the web's thank-you panel line, with how the answer will reach them.
        return response()->json([
            'message' => __('support.sent', ['code' => $ticket->code]),
            'data' => ['code' => $ticket->code, 'thanks' => __('support.thanks_body', ['code' => $ticket->code])],
        ], 201);
    }

    /**
     * What the web captures without asking, as far as a phone knows it.
     *
     * @return array<string, mixed>
     */
    private function context(Request $request): array
    {
        return array_filter([
            'route' => $request->input('screen'),
            'agent' => 'Aliadito app',
            'plan' => $request->user()->business?->subscription?->plan,
            'locale' => app()->getLocale(),
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
