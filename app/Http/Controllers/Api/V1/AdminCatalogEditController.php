<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Catalog\RemoveCatalogRecord;
use App\Classes\Main\CatalogEditor;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A catalog row created, edited and removed from the phone through the web's
 * own pieces (`CatalogEditor`, `CatalogRemoval`). A master the phone does not
 * edit yet, or one this person may not open, answers 404 like an unknown one.
 */
class AdminCatalogEditController extends Controller
{
    public function sheet(Request $request, string $master, ?int $id = null): JsonResponse
    {
        $editor = $this->editor($request, $master);
        $sheet = $editor->sheet($id);

        abort_if($sheet === null, 404, __('notifications.not_found'));

        return response()->json(['data' => $sheet]);
    }

    public function store(Request $request, string $master): JsonResponse
    {
        $saved = $this->editor($request, $master)->save(null, $request->all());

        return response()->json(['message' => $saved['message'], 'data' => ['id' => $saved['model']->getKey()]], 201);
    }

    public function update(Request $request, string $master, int $id): JsonResponse
    {
        $saved = $this->editor($request, $master)->save($id, $request->all());

        abort_if($saved === null, 404, __('notifications.not_found'));

        return response()->json(['message' => $saved['message'], 'data' => ['id' => $id]]);
    }

    /** What the delete button asks, decided by the server as on the web. */
    public function removal(Request $request, string $master, int $id): JsonResponse
    {
        return response()->json(['data' => $this->editor($request, $master)->removal->check($id)]);
    }

    public function destroy(Request $request, string $master, int $id): JsonResponse
    {
        ['result' => $result, 'notification' => $notification] = $this->editor($request, $master)->removal->remove($id);

        return response()->json(['message' => $notification->message, 'data' => ['status' => $result]], match ($result) {
            RemoveCatalogRecord::REMOVED => 200,
            RemoveCatalogRecord::IN_USE => 409,
            default => 404,
        });
    }

    public function deactivate(Request $request, string $master, int $id): JsonResponse
    {
        $notification = $this->editor($request, $master)->removal->deactivate($id);

        abort_if($notification === null, 404, __('notifications.not_found'));

        return response()->json(['message' => $notification->message]);
    }

    private function editor(Request $request, string $master): CatalogEditor
    {
        $editor = new CatalogEditor($master, $request->user());

        abort_if($editor->form === null, 404);

        return $editor;
    }
}
