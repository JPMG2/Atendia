<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Classes\Main\BusinessCards;
use App\Classes\Main\Client;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SaveBusinessCardRequest;
use App\Livewire\Forms\Client\IdentityForm;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * The editable cards of "Mi negocio" (identity, location, bookings, contact,
 * currency) for the app. Each card is read and saved through the web's own
 * rules and Action; the phone only draws what {@see BusinessCards} describes.
 */
class BusinessCardController extends Controller
{
    public function show(Request $request, string $card): JsonResponse
    {
        return response()->json(['data' => $this->client($request)->businessCards->card($card)]);
    }

    public function update(SaveBusinessCardRequest $request, NotificationService $notifications, string $card): JsonResponse
    {
        $client = $this->client($request);
        $validated = $request->validated();

        $message = match ($card) {
            'identity' => $notifications->notificationFor($client->personalData->saveIdentity([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
            ]), 'updated')->message,
            'location' => $notifications->notificationFor($client->personalData->saveIdentity([
                'address' => $validated['address'] ?? null,
                'city' => $validated['city'] ?? null,
                'has_premises' => $validated['hasPremises'] ?? null,
            ]), 'updated')->message,
            'appointments' => $this->saveBookings($client, $validated),
            'contact' => $notifications->notificationFor($client->personalData->saveContact($validated) ?? abort(404), 'updated')->message,
            'billing' => $notifications->notificationFor($client->taxDetails->save($validated), 'updated')->message,
            'social' => $this->saveSocial($request, $client, $notifications, $validated),
        };

        return response()->json(['message' => $message, 'data' => $client->businessCards->card($card)]);
    }

    /**
     * The logo is the one file of the cards. It passes the same moderation
     * rule as on the web BEFORE it is stored, and only then does it replace
     * the old one.
     */
    public function logo(Request $request, NotificationService $notifications): JsonResponse
    {
        $client = $this->client($request);

        $validated = Validator::make($request->only('logo_file'), ['logo_file' => ['required', ...IdentityForm::fieldRules()['logo_file']]], [], IdentityForm::fieldNames())->validate();

        $business = $client->personalData->saveIdentity([], $validated['logo_file']);

        return response()->json(['message' => $notifications->notificationFor($business, 'updated')->message, 'data' => $client->businessCards->card('identity')]);
    }

    /**
     * The links in display order; whatever left the screen leaves the table. The
     * words say whether the list really changed, as the web's card does.
     *
     * @param  array<string, mixed>  $validated
     */
    private function saveSocial(Request $request, Client $client, NotificationService $notifications, array $validated): string
    {
        $business = $request->user()->business;

        // No list in the body is "leave them as they are", not "delete them all".
        if (! array_key_exists('social', $validated)) {
            return $notifications->notificationFor($business, 'updated')->message;
        }

        $changed = $client->socialMedia->save(array_map(
            fn (array $row): array => ['social_network_id' => $row['social_network_id'], 'url' => $row['url']],
            $validated['social'],
        ));

        return ($changed ? $notifications->updatedRelated($business) : $notifications->notificationFor($business, 'updated'))->message;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function saveBookings(Client $client, array $validated): string
    {
        $client->agenda->saveSettings($validated);

        return __('client.business.appointments.saved');
    }

    private function client(Request $request): Client
    {
        $client = Client::for($request->user());

        // EnsureAppAccess already refused anyone without a business.
        abort_if($client->businessCards === null, 404);

        return $client;
    }
}
