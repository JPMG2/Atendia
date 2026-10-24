<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Classes\Main\Client;
use App\Http\Controllers\Controller;
use App\Models\PanelNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The web's bell, as the phone reads it: the same feed, the same bundling, the
 * same words, and the same read marks (one per person, never shared).
 */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $bell = Client::for($request->user())->bell;

        // Said by the same lines as the web's empty bell, so the two never differ in a region's words.
        $empty = ['title' => __('bell.empty.title'), 'body' => __('bell.empty.body')];

        if ($bell === null) {
            return response()->json(['data' => ['unread' => 0, 'digest' => '', 'empty' => $empty, 'groups' => []]]);
        }

        $groups = $bell->groups;
        $unread = $bell->unread;

        return response()->json(['data' => [
            'unread' => $unread,
            'digest' => $unread > 0 ? $bell->digestFor($groups) : '',
            'empty' => $empty,
            'groups' => $groups->map(fn (Collection $rows, string $group): array => [
                'group' => $group,
                'label' => __('bell.groups.'.$group),
                'rows' => $rows->map(fn (array $row): array => [
                    'key' => $row['key'],
                    'ids' => $row['ids'],
                    'type' => $row['type']->value,
                    'text' => $row['text'],
                    'time' => $bell->timeOf($row['at']),
                    'at' => $row['at'],
                    'read' => $row['read'],
                ])->values()->all(),
            ])->values()->all(),
        ]]);
    }

    /** A bundled row stands for several ids: opening it reads them all. */
    public function read(Request $request): JsonResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer', 'min:1', 'max:9223372036854775807'],
        ])['ids'];

        Client::for($request->user())->bell?->read($ids);

        return $this->unreadCount($request);
    }

    public function readAll(Request $request): JsonResponse
    {
        PanelNotification::markAllReadFor($request->user());

        return $this->unreadCount($request);
    }

    private function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['data' => ['unread' => PanelNotification::unreadCountFor($request->user())]]);
    }
}
