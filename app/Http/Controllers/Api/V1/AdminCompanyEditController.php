<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Classes\Main\CompanyEditor;
use App\Classes\Main\OpeningHours;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * The company edited from the phone: the web form's two steps, with its rules
 * and its save (`CompanyEditor`, which the web form uses too), its words and
 * its chained lists. A body is partial: what it does not name stays. Logos
 * are not here; only the web uploads them.
 */
class AdminCompanyEditController extends Controller
{
    public function sheet(CompanyEditor $editor, string $step): JsonResponse
    {
        return response()->json(['data' => $editor->sheet($step)]);
    }

    /** The lists that follow a choice: provinces and tax conditions of a country, regions of a province. */
    public function places(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'country' => ['nullable', 'integer', 'min:1'],
            'province' => ['nullable', 'integer', 'min:1'],
        ]);

        $places = CompanyEditor::places(
            isset($validated['country']) ? (int) $validated['country'] : null,
            isset($validated['province']) ? (int) $validated['province'] : null,
        );

        return response()->json(['data' => [
            'provinces' => $places['provinces'],
            'regions' => $places['regions'],
            'taxConditions' => $places['taxConditions'],
        ]]);
    }

    public function update(Request $request, CompanyEditor $editor, NotificationService $notifications, string $step): JsonResponse
    {
        if ($step === CompanyEditor::STEP_MAIN) {
            $validated = $this->validated($step, CompanyEditor::clean([
                ...CompanyEditor::current($step),
                ...$request->only(array_keys(CompanyEditor::rules($step))),
            ]));
            $company = $editor->saveMain($validated);
            $message = $notifications->notificationFor($company, $company->wasRecentlyCreated ? 'created' : 'updated')->message;

            return response()->json(['message' => $message, 'data' => $editor->sheet($step)]);
        }

        // The second step never creates the company: the first one does, as on the web.
        $company = Company::query()->first();

        abort_if($company === null, 404, __('notifications.not_found'));

        $payload = CompanyEditor::clean([...CompanyEditor::current($step), ...$request->only(array_diff(CompanyEditor::COMMERCIAL_COLUMNS, ['opening_schedule']))]);

        if ($request->has('week')) {
            // The shape first: a day whose hour is not text is refused, never cast into "Array".
            $request->validate([
                'week' => ['array'],
                'week.*' => ['array'],
                'week.*.open' => ['required', 'boolean'],
                'week.*.opens_at' => ['nullable', 'string'],
                'week.*.closes_at' => ['nullable', 'string'],
            ], [], ['week.*.opens_at' => __('company.hours.opens'), 'week.*.closes_at' => __('company.hours.closes')]);

            $payload['opening_schedule'] = OpeningHours::toStored($request->input('week')) ?: null;
        }

        // Rows left blank are not networks, like the web's starting row; without `social` the networks stay.
        if ($request->has('social')) {
            $rows = $request->input('social');
            $payload['social'] = is_array($rows)
                ? array_values(array_filter($rows, fn (mixed $row): bool => ! is_array($row) || filled($row['social_network_id'] ?? null) || filled($row['url'] ?? null)))
                : $rows;
        }

        $validated = $this->validated($step, $payload);
        $linksChanged = $editor->saveCommercial($company, $validated);

        $message = $linksChanged && ! $company->wasChanged()
            ? $notifications->updatedRelated($company)->message
            : $notifications->notificationFor($company, 'updated')->message;

        return response()->json(['message' => $message, 'data' => $editor->sheet($step)]);
    }

    /** Removes a saved network at once, as the web's row does after asking. */
    public function removeSocial(CompanyEditor $editor, NotificationService $notifications, int $link): JsonResponse
    {
        $removed = $editor->removeSocialLink($link);

        abort_if($removed === null, 404, __('notifications.not_found'));

        return response()->json([
            'message' => $notifications->notificationFor($removed, 'deleted')->message,
            'data' => $editor->sheet(CompanyEditor::STEP_COMMERCIAL),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validated(string $step, array $payload): array
    {
        return Validator::make($payload, CompanyEditor::rules($step), [], CompanyEditor::names())->validate();
    }
}
