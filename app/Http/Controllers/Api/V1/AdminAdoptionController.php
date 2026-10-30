<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Classes\Main\AdoptionBoard;
use App\Dto\AdoptionRowDto;
use App\Enums\AdoptionSituation;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Adopción" as the web prints it, from the same piece and in the same words.
 * Read only: "Escribirle" opens a mail program and notes it, and stays on the web.
 */
class AdminAdoptionController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $board = new AdoptionBoard(is_string($request->query('view')) ? $request->query('view') : null);
        $situation = $board->situation;
        $viewer = $request->user()?->id;
        // "Ver ficha" only for whoever may open Negocios: no door that ends in a 403.
        $business = $request->user()?->can('businesses.view') === true;

        return response()->json(['data' => [
            'title' => __('adoption.title'),
            'sub' => __('adoption.sub'),
            'count' => trans_choice('adoption.count', $board->accounts->count(), ['count' => $board->accounts->count()]),
            'empty' => $board->accounts->isEmpty() ? ['title' => __('adoption.empty_title'), 'body' => __('adoption.empty_body')] : null,
            'flow' => [
                'title' => __('adoption.flow_title'),
                'hint' => __('adoption.flow_hint'),
                'steps' => $board->flow->map(fn (array $point): array => [
                    'key' => $point['step']->value,
                    'label' => $point['step']->label(),
                    'reached' => $point['reached'],
                    'share' => $point['share'],
                    // The web prints the loss, "sin pérdida" or nothing at the last step: the same words here.
                    'loss' => match (true) {
                        $point['stopped'] === null => null,
                        $point['stopped'] > 0 => trans_choice('adoption.flow_loss', $point['stopped'], ['count' => $point['stopped']]),
                        default => __('adoption.flow_none'),
                    },
                    'lost' => ($point['stopped'] ?? 0) > 0,
                ])->values()->all(),
            ],
            'list' => ['title' => __('adoption.list_title'), 'hint' => __('adoption.list_hint')],
            'view' => $situation->value,
            'tabs' => array_map(fn (AdoptionSituation $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
                'badge' => $board->counts[$case->value],
            ], AdoptionSituation::cases()),
            'legend' => __('adoption.legend.'.$situation->value),
            'empty_tab' => ['title' => __('adoption.empty_tab.'.$situation->value.'.title'), 'body' => __('adoption.empty_tab.'.$situation->value.'.body')],
            'go' => __('adoption.actions.view_business'),
            'rows' => $board->rows->map(fn (AdoptionRowDto $row): array => [
                'key' => md5($row->email),
                'business' => $row->business ?? __('adoption.no_business'),
                'owner' => $row->owner,
                'email' => $row->email,
                'stage' => $row->step->label(),
                'active' => $row->situation === AdoptionSituation::Active,
                'since' => $board->since($row),
                'idle' => $board->idle($row),
                'why' => $board->why($row),
                'rule' => $board->rule($row),
                'written' => $board->writtenNote($row, $viewer),
                'target' => $row->businessId === null || ! $business ? null : ['screen' => 'admin.businesses', 'query' => ['negocio' => $row->businessId]],
            ])->values()->all(),
        ]]);
    }
}
