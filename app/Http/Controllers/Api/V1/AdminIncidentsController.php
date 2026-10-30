<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Classes\Main\Incidents;
use App\Dto\IncidentRowDto;
use App\Enums\IncidentKind;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Qué salió mal" as the web prints it, from the same piece and in the same
 * words. Read only; "Ver el log" stays on the web, the app has no log screen.
 */
class AdminIncidentsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $desk = Incidents::now();
        // "Ver el negocio" only for whoever may open Negocios: no door that ends in a 403.
        $business = $request->user()?->can('businesses.view') === true;

        return response()->json(['data' => [
            'title' => __('incidents.title'),
            'sub' => __('incidents.sub'),
            'read_at' => __('incidents.read_at', ['time' => $desk->readAt->translatedFormat('d/m/Y H:i')]),
            'pending' => trans_choice('incidents.pending', $desk->all->count(), ['count' => $desk->all->count()]),
            'empty' => $desk->isEmpty ? ['title' => __('incidents.all_good_title'), 'body' => __('incidents.all_good_body')] : null,
            'open_tab' => $desk->openTab,
            'go' => __('incidents.open_business'),
            'none' => __('incidents.none_of_kind'),
            'labels' => ['customer' => __('incidents.table.customer'), 'waiting' => __('incidents.table.waiting'), 'last' => __('incidents.table.last')],
            'tabs' => array_map(function (array $tab) use ($desk, $business): array {
                $kind = IncidentKind::from($tab['value']);
                $concentrated = $desk->concentration($kind);

                return [
                    'value' => $tab['value'],
                    'label' => $tab['label'],
                    'badge' => $tab['badge'],
                    'help' => __('incidents.kinds.'.$kind->value.'.help'),
                    'concentrated' => $concentrated === null ? null : [
                        'line' => $desk->concentrationLine($kind),
                        'target' => $business ? ['screen' => 'admin.businesses', 'query' => ['negocio' => $concentrated['businessId']]] : null,
                    ],
                    'rows' => $desk->ofKind($kind)->map(fn (IncidentRowDto $row): array => [
                        'label' => __('incidents.kinds.'.$row->kind->value.'.label'),
                        'tone' => $row->kind->tone(),
                        'business' => $row->business ?? __('incidents.no_business'),
                        'customer' => $row->customer,
                        'waiting' => $row->waiting,
                        'excerpt' => $row->excerpt,
                        'target' => $row->businessId === null || ! $business ? null : ['screen' => 'admin.businesses', 'query' => ['negocio' => $row->businessId]],
                    ])->values()->all(),
                ];
            }, $desk->tabs),
        ]]);
    }
}
