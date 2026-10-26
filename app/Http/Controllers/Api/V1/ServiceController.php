<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Business\DeleteBusinessService;
use App\Classes\Main\Client;
use App\Classes\Main\ServiceMenu;
use App\Dto\DtoCast;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SaveServiceRequest;
use App\Livewire\Forms\Client\ServiceCategoryForm;
use App\Models\Service;
use App\Rules\AttributeValueRules;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * "Tus servicios" for the app: the web's own list cut, the sheet it opens, and every
 * write through the web's rules and Actions. Owner only, as the web screen; a service
 * of another business is a 404.
 */
class ServiceController extends Controller
{
    public const int WINDOW = 8;

    public function index(Request $request): JsonResponse
    {
        $query = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'filter' => ['sometimes', 'in:all,active,paused'],
            'visible' => ['sometimes', 'integer', 'min:1', 'max:600'],
            'collapsed' => ['sometimes', 'array', 'max:60'],
            // The loose shelf's key is '', which the framework turns into null on the way in.
            'collapsed.*' => ['nullable', 'string', 'max:20'],
        ]);
        $menu = $this->menu($request);
        $collapsed = array_map(fn (?string $key): string => (string) $key, $query['collapsed'] ?? []);

        return response()->json(['data' => [
            'copy' => $menu->copy,
            ...$menu->page((string) ($query['search'] ?? ''), $query['filter'] ?? 'all', (int) ($query['visible'] ?? self::WINDOW), $collapsed),
        ]]);
    }

    /** A blank sheet; a suggestion chip lands here with its name already typed. */
    public function blank(Request $request): JsonResponse
    {
        $name = $request->validate(['name' => ['sometimes', 'nullable', 'string', 'max:120']])['name'] ?? '';
        $menu = $this->menu($request);

        return response()->json(['data' => ['copy' => $menu->copy, 'sheet' => $menu->sheetOf(null, (string) $name)]]);
    }

    public function show(Request $request, int $service): JsonResponse
    {
        $menu = $this->menu($request);

        return response()->json(['data' => ['copy' => $menu->copy, 'sheet' => $menu->sheetOf($this->service($menu, $service))]]);
    }

    /** The own fields of one type, for the sheet that grows when a type is picked. */
    public function type(Request $request, int $type): JsonResponse
    {
        $menu = $this->menu($request);

        // Only a type the sheet offers: a product's type is not a service's.
        abort_unless(array_key_exists($type, $menu->typeOptions), 404);

        return response()->json(['data' => ['attributes' => $menu->attributeSet($type)]]);
    }

    public function store(SaveServiceRequest $request, NotificationService $notifications): JsonResponse
    {
        return $this->saved($request, $notifications, null);
    }

    public function update(SaveServiceRequest $request, NotificationService $notifications, int $service): JsonResponse
    {
        return $this->saved($request, $notifications, $service);
    }

    public function toggle(Request $request, int $service): JsonResponse
    {
        $menu = $this->menu($request);
        $this->service($menu, $service);

        $toggled = $menu->toggle($service);

        return response()->json(['data' => ['row' => $menu->rowOf($toggled->load('category'))]]);
    }

    public function destroy(Request $request, int $service): JsonResponse
    {
        $menu = $this->menu($request);
        $this->service($menu, $service);

        return match ($menu->remove($service)) {
            DeleteBusinessService::DELETED => response()->json(['message' => __('client.services.deleted'), 'data' => ['undo' => __('client.services.undo'), 'id' => $service]]),
            // History on it: the web says to pause it instead, and the row stays.
            DeleteBusinessService::HELD => throw ValidationException::withMessages(['service' => __('client.services.delete_held')]),
            default => abort(404),
        };
    }

    /** The toast's "Deshacer". */
    public function restore(Request $request, int $service): JsonResponse
    {
        abort_unless($this->menu($request)->restore($service), 404);

        return response()->json(['message' => __('client.services.restored')]);
    }

    public function storeCategory(Request $request, NotificationService $notifications): JsonResponse
    {
        $menu = $this->menu($request);

        $validated = Validator::make(
            // A name that is not text (a list, an object) is read as nothing sent, so the sheet's own refusal answers.
            ['name' => is_string($request->input('name')) ? (DtoCast::squish($request->input('name')) ?? '') : ''],
            ServiceCategoryForm::fieldRules($request->user()->business_id),
            [],
            ServiceCategoryForm::fieldNames(),
        )->validate();

        $category = $menu->addCategory($validated['name']);

        return response()->json([
            'message' => $notifications->notificationFor($category, 'created')->message,
            'data' => ['category' => ['id' => $category->id, 'name' => $category->name]],
        ], 201);
    }

    public function moveCategory(Request $request, int $category): JsonResponse
    {
        // Either an absolute place (the web's drag) or "next to this shelf": the app only sees the shelves
        // that have services, so it names its visible neighbour instead of counting places among them.
        $body = $request->validate([
            'position' => ['required_without:near', 'integer', 'min:0', 'max:200'],
            'near' => ['required_without:position', 'integer'],
            'after' => ['sometimes', 'boolean'],
        ]);
        $menu = $this->menu($request);

        abort_unless(isset($body['near'])
            ? $menu->moveCategoryNear($category, (int) $body['near'], $request->boolean('after'))
            : $menu->moveCategory($category, (int) $body['position']), 404);

        return response()->json(['data' => new \stdClass]);
    }

    private function saved(SaveServiceRequest $request, NotificationService $notifications, ?int $id): JsonResponse
    {
        $menu = $this->menu($request);
        $validated = $request->validated();

        // The empty keys validation needed are noise on the stored row.
        $validated['attribute_values'] = AttributeValueRules::strip($validated['attribute_values'] ?? []);

        $service = $menu->save($validated, $id);

        return response()->json([
            'message' => $notifications->notificationFor($service, $id === null ? 'created' : 'updated')->message,
            'data' => ['copy' => $menu->copy, 'sheet' => $menu->sheetOf($service->refresh())],
        ], $id === null ? 201 : 200);
    }

    private function service(ServiceMenu $menu, int $id): Service
    {
        $service = $menu->services->firstWhere('id', $id);

        abort_if($service === null, 404);

        return $service;
    }

    private function menu(Request $request): ServiceMenu
    {
        $menu = Client::for($request->user())->serviceMenu;

        // EnsureAppAccess already refused anyone without a business.
        abort_if($menu === null, 404);

        return $menu;
    }
}
