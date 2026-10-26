<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Classes\Main\Client;
use App\Classes\Main\Directory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SaveCustomerRequest;
use App\Models\Customer;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * "Clientes" for the app: the web's own directory, cut the same way, and the
 * fiche it opens, written through the same rules. Any member who can use the
 * panel can, as on the web; a customer of another business is a 404.
 */
class CustomerController extends Controller
{
    public const PER_PAGE = 15;

    public function index(Request $request): JsonResponse
    {
        $query = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'filter' => ['sometimes', 'in:all,optin,birthday'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:10000'],
        ]);
        $directory = $this->directory($request);
        $page = $directory->page((string) ($query['search'] ?? ''), $query['filter'] ?? 'all', (int) ($query['page'] ?? 1), self::PER_PAGE);

        return response()->json(['data' => [
            'copy' => $directory->copy,
            ...$page,
            // The web's empty card offers to connect the WhatsApp to whoever may, while it is not.
            'connect' => ! $page['any'] && $request->user()->can('manage-business') && ! $request->user()->business?->isConnected()
                ? __('whatsapp.connect.cta')
                : null,
        ]]);
    }

    public function show(Request $request, int $customer): JsonResponse
    {
        $directory = $this->directory($request);

        return response()->json(['data' => ['copy' => $directory->copy, 'sheet' => $directory->sheetOf($this->customer($directory, $customer))]]);
    }

    public function update(SaveCustomerRequest $request, int $customer): JsonResponse
    {
        $directory = $this->directory($request);
        $record = $this->customer($directory, $customer);

        $directory->save($record, $request->validated());

        return $this->done($directory, $record, app(NotificationService::class)->notificationFor($record, 'updated')->message);
    }

    public function optIn(Request $request, int $customer): JsonResponse
    {
        $directory = $this->directory($request);
        $record = $this->customer($directory, $customer);

        // The web hides the button once they said yes, and a blocked person is never written to: nothing to ask.
        abort_if($record->marketing_opt_in_at !== null || $record->blocked_at !== null, 404);

        // The web's own refusal: without a connected WhatsApp there is nothing to send it over.
        if (! $directory->requestOptIn($record)) {
            throw ValidationException::withMessages(['opt_in' => __('client.customers.opt_in_unavailable')]);
        }

        return $this->done($directory, $record->refresh(), __('client.customers.opt_in_sent'));
    }

    public function merge(Request $request, int $customer): JsonResponse
    {
        $shown = $request->validate(['duplicate_id' => ['required', 'integer']])['duplicate_id'];
        $directory = $this->directory($request);
        $record = $this->customer($directory, $customer);

        $merged = $directory->merge($record, (int) $shown);

        // Nothing to merge is a customer without a twin, as the web never offers the button.
        abort_if($merged === null, 404);
        // The twin changed since the phone showed it: the question that was asked is not this one.
        abort_if($merged === false, 409, __('notifications.not_found'));

        return $this->done($directory, $record->refresh(), __('client.customers.merged'));
    }

    private function done(Directory $directory, Customer $customer, string $message): JsonResponse
    {
        return response()->json(['message' => $message, 'data' => ['copy' => $directory->copy, 'sheet' => $directory->sheetOf($customer->refresh())]]);
    }

    private function customer(Directory $directory, int $id): Customer
    {
        $customer = $directory->customer($id);

        abort_if($customer === null, 404);

        return $customer;
    }

    private function directory(Request $request): Directory
    {
        $directory = Client::for($request->user())->directory;

        // EnsureAppAccess already refused anyone without a business.
        abort_if($directory === null, 404);

        return $directory;
    }
}
