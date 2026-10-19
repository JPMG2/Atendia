<?php

declare(strict_types=1);

namespace App\Classes\Report;

use App\Classes\Main\Client;
use App\Classes\Main\Inventory;
use App\Enums\ReportAudience;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/** The "Productos" list as a file, cut by the same search and stock filter as the screen. */
class ProductsReport extends ListReport
{
    public string $title {
        get => __('reports/offer.products.title');
    }

    public string $filename {
        get => __('reports/offer.products.filename');
    }

    public array $columns {
        get => [
            __('reports/offer.products.name'),
            __('reports/offer.products.code'),
            trim(__('reports/offer.products.price').' '.$this->currency()),
            __('reports/offer.products.stock'),
            __('reports/offer.products.available'),
            __('reports/offer.products.status'),
        ];
    }

    public ReportAudience $audience {
        get => ReportAudience::Client;
    }

    public int $total {
        get => $this->inventory()?->products->count() ?? 0;
    }

    public function authorize(User $user): bool
    {
        return $user->can('access-client-app') && $user->can('manage-business') && $user->business !== null;
    }

    protected function rows(ReportScope $scope): array
    {
        return $this->products($scope)
            ->map(fn (Product $product): array => [
                $product->name,
                (string) $product->code,
                $product->price !== null ? (float) $product->price : '',
                $product->stock !== null ? (int) $product->stock : '',
                $product->in_stock ? __('reports/offer.yes') : __('reports/offer.no'),
                $product->is_active ? __('reports/offer.products.active') : __('reports/offer.products.paused'),
            ])
            ->values()
            ->all();
    }

    protected function shown(ReportScope $scope): int
    {
        return $this->products($scope)->count();
    }

    protected function filters(ReportScope $scope): array
    {
        $stock = match ($scope->value('filtro')) {
            'available' => __('reports/offer.products.filter_available'),
            'out' => __('reports/offer.products.filter_out'),
            default => '',
        };

        return collect([
            ['label' => __('reports/offer.filter_search'), 'value' => $scope->value('buscar') === '' ? '' : '«'.$scope->value('buscar').'»'],
            ['label' => __('reports/offer.products.available'), 'value' => $stock],
        ])->filter(fn (array $filter): bool => $filter['value'] !== '')->values()->all();
    }

    /** @return Collection<int, Product> */
    private function products(ReportScope $scope): Collection
    {
        return $this->inventory()?->filtered($scope->value('buscar'), $scope->value('filtro')) ?? new Collection;
    }

    private function currency(): string
    {
        return (string) auth()->user()?->business?->currency?->code;
    }

    private function inventory(): ?Inventory
    {
        return Client::for(auth()->user())->inventory;
    }
}
