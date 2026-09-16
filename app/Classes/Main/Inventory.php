<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Actions\Business\SaveBusinessProduct;
use App\Actions\Business\SyncOfferKnowledge;
use App\Actions\Business\ToggleBusinessProduct;
use App\Models\Business;
use App\Models\Product;
use App\Models\ProductImport;
use App\Models\ServiceType;
use Illuminate\Database\Eloquent\Collection;

/**
 * The inventory piece: the goods the tenant sells and every write its
 * screen needs. Queries live HERE so the Blade never builds one; the
 * spreadsheet path stays with the import pipeline, which this piece only
 * reports on.
 */
class Inventory
{
    public function __construct(private Business $business) {}

    /**
     * The whole inventory in creation order. Small by design: WhatsApp caps
     * a catalog at 500 items, so one read is the cheap path.
     *
     * @var Collection<int, Product>
     */
    public Collection $products {
        get => $this->business->products()->orderBy('id')->get();
    }

    /** The newest finished import — what the lane's footnote reports. */
    public ?ProductImport $lastImport {
        get => $this->business->productImports()->where('status', 'done')->latest('id')->first();
    }

    /**
     * The sheet's type picker: only producto-modality types, suggested first.
     *
     * @var array<int, string>
     */
    public array $typeOptions {
        get => ServiceType::adoptableBy($this->business, goods: true);
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
    public function save(array $data, ?int $id = null): Product
    {
        $product = app(SaveBusinessProduct::class)->handle($this->business, $data, $id);

        // Every offer change reaches the assistant's knowledge in the act.
        app(SyncOfferKnowledge::class)->handle($this->business, 'products');

        return $product;
    }

    public function toggle(int $id): Product
    {
        $product = app(ToggleBusinessProduct::class)->handle($this->business, $id);

        app(SyncOfferKnowledge::class)->handle($this->business, 'products');

        return $product;
    }
}
