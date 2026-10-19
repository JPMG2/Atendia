<?php

declare(strict_types=1);

namespace App\Classes\Report;

use App\Classes\Main\Client;
use App\Classes\Main\ServiceMenu;
use App\Enums\ReportAudience;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/** The "Servicios" list as a file, cut by the same search and state as the screen. */
class ServicesReport extends ListReport
{
    public string $title {
        get => __('reports/offer.services.title');
    }

    public string $filename {
        get => __('reports/offer.services.filename');
    }

    public array $columns {
        get => [
            __('reports/offer.services.name'),
            __('reports/offer.services.category'),
            trim(__('reports/offer.services.price').' '.$this->currency()),
            __('reports/offer.services.duration'),
            __('reports/offer.services.bookable'),
            __('reports/offer.services.featured'),
            __('reports/offer.services.status'),
        ];
    }

    public ReportAudience $audience {
        get => ReportAudience::Client;
    }

    public int $total {
        get => $this->menu()?->services->count() ?? 0;
    }

    public function authorize(User $user): bool
    {
        return $user->can('access-client-app') && $user->can('manage-business') && $user->business !== null;
    }

    protected function rows(ReportScope $scope): array
    {
        return $this->services($scope)
            ->map(fn (Service $service): array => [
                $service->name,
                (string) ($service->category?->name ?? __('reports/offer.services.uncategorized')),
                $service->price !== null ? (float) $service->price : (string) $this->priceNote($service),
                (int) $service->duration_minutes,
                $service->is_bookable ? __('reports/offer.yes') : __('reports/offer.no'),
                $service->is_featured ? __('reports/offer.yes') : __('reports/offer.no'),
                $service->is_active ? __('reports/offer.services.active') : __('reports/offer.services.paused'),
            ])
            ->values()
            ->all();
    }

    protected function shown(ReportScope $scope): int
    {
        return $this->services($scope)->count();
    }

    protected function filters(ReportScope $scope): array
    {
        $state = match ($scope->value('filtro')) {
            'active' => __('reports/offer.services.active'),
            'paused' => __('reports/offer.services.paused'),
            default => '',
        };

        return collect([
            ['label' => __('reports/offer.filter_search'), 'value' => $scope->value('buscar') === '' ? '' : '«'.$scope->value('buscar').'»'],
            ['label' => __('reports/offer.filter_status'), 'value' => $state],
        ])->filter(fn (array $filter): bool => $filter['value'] !== '')->values()->all();
    }

    /** @return Collection<int, Service> */
    private function services(ReportScope $scope): Collection
    {
        return $this->menu()?->filtered($scope->value('buscar'), $scope->value('filtro')) ?? new Collection;
    }

    /** "Gratis" or "A consultar" when there is no figure to print. */
    private function priceNote(Service $service): string
    {
        return in_array($service->price_type, ['free', 'talk'], true) ? __('reports/offer.services.'.$service->price_type) : '';
    }

    private function currency(): string
    {
        return (string) auth()->user()?->business?->currency?->code;
    }

    private function menu(): ?ServiceMenu
    {
        return Client::for(auth()->user())->serviceMenu;
    }
}
