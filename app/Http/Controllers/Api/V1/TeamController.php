<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Classes\Main\Client;
use App\Classes\Main\Team;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SaveTeamMemberRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * "Equipo" for the app: the people, the open offers and the seats, read from
 * the web's own Team piece, and every write through the Action the web calls.
 * The business is the caller's; an id of another business is a 404.
 */
class TeamController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->overview($request)]);
    }

    public function invite(SaveTeamMemberRequest $request): JsonResponse
    {
        $team = $this->team($request);
        $validated = $request->validated();

        // The plan's seats are people: refused here as the web refuses it.
        if (! $team->canOfferSeatTo($validated['email'])) {
            $seats = $team->seats;

            throw ValidationException::withMessages(['email' => __('team.notify.seats_full', [
                'plan' => __('plan.names.'.$seats['plan']),
                'cap' => $seats['cap'],
            ])]);
        }

        $team->invite($validated, $request->user());

        return $this->done($request, __('team.notify.invited', ['email' => $validated['email']]), 201);
    }

    public function resend(Request $request, int $invitation): JsonResponse
    {
        $this->team($request)->resend($invitation, $request->user());

        return $this->done($request, __('team.notify.resent'));
    }

    public function cancel(Request $request, int $invitation): JsonResponse
    {
        $this->team($request)->cancel($invitation);

        return $this->done($request, __('team.notify.cancelled'));
    }

    public function update(SaveTeamMemberRequest $request, int $member): JsonResponse
    {
        $this->team($request)->updateMember($member, $request->validated());

        return $this->done($request, __('team.notify.member_saved'));
    }

    public function remove(Request $request, int $member): JsonResponse
    {
        $this->team($request)->remove($member);

        return $this->done($request, __('team.notify.removed'));
    }

    private function done(Request $request, string $message, int $status = 200): JsonResponse
    {
        return response()->json(['message' => $message, 'data' => $this->overview($request)], $status);
    }

    /**
     * @return array<string, mixed>
     */
    private function overview(Request $request): array
    {
        $overview = $this->team($request)->overview;

        // The web marks "(Tú)" next to the caller's own row.
        $overview['members'] = array_map(fn (array $member): array => [...$member, 'isYou' => $member['id'] === $request->user()->id], $overview['members']);

        return $overview;
    }

    private function team(Request $request): Team
    {
        $team = Client::for($request->user())->team;

        // EnsureAppAccess already refused anyone without a business.
        abort_if($team === null, 404);

        return $team;
    }
}
