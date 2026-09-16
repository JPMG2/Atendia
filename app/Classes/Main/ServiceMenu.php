<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Actions\Business\SaveBusinessCategoryOrder;
use App\Actions\Business\SaveBusinessService;
use App\Actions\Business\SaveBusinessServiceCategory;
use App\Actions\Business\SyncOfferKnowledge;
use App\Actions\Business\ToggleBusinessService;
use App\Models\Business;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceType;
use Illuminate\Database\Eloquent\Collection;

/**
 * The service-menu piece (Fresha's name for it): the tenant's services and
 * the shelves they group under, plus every write the screen needs. Queries
 * live HERE so the Blade never builds one.
 */
class ServiceMenu
{
    public function __construct(private Business $business) {}

    /**
     * The whole offer in creation order, shelves preloaded. Small by design:
     * WhatsApp caps a catalog at 500 items, so one read is the cheap path.
     *
     * @var Collection<int, Service>
     */
    public Collection $services {
        get => $this->business->services()->with('category')->orderBy('id')->get();
    }

    /**
     * The shelves in the drag order the client chose.
     *
     * @var Collection<int, ServiceCategory>
     */
    public Collection $categories {
        get => $this->business->serviceCategories()->orderBy('sort_order')->orderBy('id')->get();
    }

    /**
     * The sheet's type picker: suggested types first, all below.
     *
     * @var array<int, string>
     */
    public array $typeOptions {
        get => ServiceType::adoptableBy($this->business, goods: false);
    }

    /**
     * @return list<array{id: int, label: string, hint: ?string, data_type: string, is_required: bool, is_multiple: bool, options: ?array<int, string>, unit: ?string}>
     */
    public function attributeSet(?int $typeId): array
    {
        return ServiceType::attributeSetFor($typeId);
    }

    /**
     * @param  array<string, mixed>  $data  Already validated by the calling form.
     */
    public function save(array $data, ?int $id = null): Service
    {
        $service = app(SaveBusinessService::class)->handle($this->business, $data, $id);

        // Every offer change reaches the assistant's knowledge in the act.
        app(SyncOfferKnowledge::class)->handle($this->business, 'services');

        return $service;
    }

    public function toggle(int $id): Service
    {
        $service = app(ToggleBusinessService::class)->handle($this->business, $id);

        app(SyncOfferKnowledge::class)->handle($this->business, 'services');

        return $service;
    }

    public function addCategory(string $name): ServiceCategory
    {
        return app(SaveBusinessServiceCategory::class)->handle($this->business, $name);
    }

    /**
     * @param  list<int>  $orderedIds
     */
    public function reorderCategories(array $orderedIds): void
    {
        app(SaveBusinessCategoryOrder::class)->handle($this->business, $orderedIds);
    }
}
