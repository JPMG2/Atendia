<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Business\DeleteBusinessProduct;
use App\Classes\Main\Client;
use App\Classes\Main\Inventory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SaveProductRequest;
use App\Models\Product;
use App\Rules\AttributeValueRules;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * "Tus productos" for the app: the web's own list cut, the sheet it opens, and every write
 * through the web's rules and Actions. Owner only, as the web screen; a product of another
 * business is a 404. The spreadsheet import stays on the web.
 */
class ProductController extends Controller
{
    public const int WINDOW = 8;

    public function index(Request $request): JsonResponse
    {
        $query = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'filter' => ['sometimes', 'in:all,available,out'],
            'visible' => ['sometimes', 'integer', 'min:1', 'max:600'],
        ]);
        $inventory = $this->inventory($request);

        return response()->json(['data' => [
            'copy' => $inventory->copy,
            ...$inventory->page((string) ($query['search'] ?? ''), $query['filter'] ?? 'all', (int) ($query['visible'] ?? self::WINDOW)),
        ]]);
    }

    /** A blank sheet. */
    public function blank(Request $request): JsonResponse
    {
        $inventory = $this->inventory($request);

        return response()->json(['data' => ['copy' => $inventory->copy, 'sheet' => $inventory->sheetOf(null)]]);
    }

    public function show(Request $request, int $product): JsonResponse
    {
        $inventory = $this->inventory($request);

        return response()->json(['data' => ['copy' => $inventory->copy, 'sheet' => $inventory->sheetOf($this->product($inventory, $product))]]);
    }

    /** The own fields of one type, for the sheet that grows when a type is picked. */
    public function type(Request $request, int $type): JsonResponse
    {
        $inventory = $this->inventory($request);

        // Only a type of products: a service's type is not a product's.
        abort_unless(array_key_exists($type, $inventory->typeOptions), 404);

        return response()->json(['data' => ['attributes' => $inventory->attributeSet($type)]]);
    }

    public function store(SaveProductRequest $request, NotificationService $notifications): JsonResponse
    {
        return $this->saved($request, $notifications, null);
    }

    public function update(SaveProductRequest $request, NotificationService $notifications, int $product): JsonResponse
    {
        return $this->saved($request, $notifications, $product);
    }

    /** The row flips in stock / out of stock. */
    public function toggle(Request $request, int $product): JsonResponse
    {
        $inventory = $this->inventory($request);
        $this->product($inventory, $product);

        return response()->json(['data' => ['row' => $inventory->rowOf($inventory->toggle($product))]]);
    }

    public function destroy(Request $request, int $product): JsonResponse
    {
        $inventory = $this->inventory($request);
        $this->product($inventory, $product);

        return match ($inventory->remove($product)) {
            DeleteBusinessProduct::DELETED => response()->json(['message' => __('client.products.deleted'), 'data' => ['undo' => __('client.products.undo'), 'id' => $product]]),
            // A customer asked about it: the web says to mark it out of stock instead, and the row stays.
            DeleteBusinessProduct::HELD => throw ValidationException::withMessages(['product' => __('client.products.delete_held')]),
            default => abort(404),
        };
    }

    /** The toast's "Deshacer". */
    public function restore(Request $request, int $product): JsonResponse
    {
        abort_unless($this->inventory($request)->restore($product), 404);

        return response()->json(['message' => __('client.products.restored')]);
    }

    private function saved(SaveProductRequest $request, NotificationService $notifications, ?int $id): JsonResponse
    {
        $inventory = $this->inventory($request);
        $validated = $request->validated();

        // The empty keys validation needed are noise on the stored row.
        $validated['attribute_values'] = AttributeValueRules::strip($validated['attribute_values'] ?? []);

        $product = $inventory->save($validated, $id);

        return response()->json([
            'message' => $notifications->notificationFor($product, $id === null ? 'created' : 'updated')->message,
            'data' => ['copy' => $inventory->copy, 'sheet' => $inventory->sheetOf($product->refresh())],
        ], $id === null ? 201 : 200);
    }

    private function product(Inventory $inventory, int $id): Product
    {
        $product = $inventory->products->firstWhere('id', $id);

        abort_if($product === null, 404);

        return $product;
    }

    private function inventory(Request $request): Inventory
    {
        $inventory = Client::for($request->user())->inventory;

        // EnsureAppAccess already refused anyone without a business.
        abort_if($inventory === null, 404);

        return $inventory;
    }
}
