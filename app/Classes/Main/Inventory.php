<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Actions\Business\DeleteBusinessProduct;
use App\Actions\Business\SaveBusinessProduct;
use App\Actions\Business\SyncOfferKnowledge;
use App\Actions\Business\ToggleBusinessProduct;
use App\Dto\ProductDto;
use App\Models\Business;
use App\Models\Product;
use App\Models\ProductImport;
use App\Models\ServiceType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

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

    /**
     * The inventory as the screen shows it: an accent-free search over name and
     * code, and a stock filter (all, available, out). The export reads the same cut.
     *
     * @return Collection<int, Product>
     */
    public function filtered(string $search = '', string $filter = 'all'): Collection
    {
        $needle = Str::ascii(mb_strtolower(trim($search)));

        return $this->products->filter(fn (Product $product): bool => match ($filter) {
            'available' => (bool) $product->in_stock,
            'out' => ! $product->in_stock,
            default => true,
        } && ($needle === ''
            || str_contains(Str::ascii(mb_strtolower($product->name)), $needle)
            || str_contains(Str::ascii(mb_strtolower($product->code ?? '')), $needle)));
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

    /** @return string One of DeleteBusinessProduct's constants. */
    public function remove(int $id): string
    {
        $result = app(DeleteBusinessProduct::class)->handle($this->business, $id);

        if ($result === DeleteBusinessProduct::DELETED) {
            app(SyncOfferKnowledge::class)->handle($this->business, 'products');
        }

        return $result;
    }

    /** The "Deshacer" of {@see remove()}. */
    public function restore(int $id): bool
    {
        $product = $this->business->products()->onlyTrashed()->find($id);

        if ($product === null) {
            return false;
        }

        $product->restore();

        app(SyncOfferKnowledge::class)->handle($this->business, 'products');

        return true;
    }

    /**
     * The words the app prints for "Tus productos", the web screen's own.
     *
     * @var array<string, mixed>
     */
    public array $copy {
        get => [
            'title' => __('client.products.title'),
            'sub' => __('client.products.sub'),
            'add' => __('client.products.add'),
            'search' => __('client.products.search_placeholder'),
            'filters' => [
                'all' => __('client.products.filter_all'),
                'available' => __('client.products.filter_available'),
                'out' => __('client.products.filter_out'),
            ],
            'noResults' => __('client.products.no_results'),
            'loadMore' => __('pagination.load_more'),
            'emptyTitle' => __('client.products.empty_title'),
            'emptyBody' => __('client.products.empty_body'),
            'emptyOr' => __('client.products.empty_or'),
            'undo' => __('client.products.undo'),
            'deleteMessage' => __('client.products.delete_message'),
            'deleteAccept' => __('client.products.delete_accept'),
            'sheet' => [
                'new' => __('client.products.sheet_new_title'),
                'hint' => __('client.products.sheet_hint'),
                'cancel' => __('client.products.sheet_cancel'),
                'save' => __('client.products.sheet_save'),
                'available' => __('client.products.sheet_available'),
                'fields' => [
                    'name' => __('client.products.field_name'),
                    'code' => __('client.products.field_code'),
                    'price' => __('client.products.field_price'),
                    'stock' => __('client.products.field_stock'),
                    'type' => __('client.products.field_type'),
                    'description' => __('client.products.field_description'),
                ],
                'helps' => [
                    'stock' => __('client.products.stock_help'),
                    'type' => __('client.products.type_help'),
                    'description' => __('client.products.description_help'),
                ],
            ],
        ];
    }

    /**
     * One row as the web's row says it: the price, the code, the stock state and the
     * name already inside each action's label.
     *
     * @return array<string, mixed>
     */
    public function rowOf(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'code' => $product->code,
            'price' => $product->price !== null ? '$ '.number_format((float) $product->price, 0, ',', '.') : null,
            'inStock' => (bool) $product->in_stock,
            // "Out of stock switches OFF, never out": the assistant says so and the row stays.
            'outNote' => $product->in_stock ? null : __('client.products.out_note'),
            'editLabel' => __('client.products.edit', ['name' => $product->name]),
            'toggleLabel' => __('client.products.toggle_stock', ['name' => $product->name]),
            'deleteLabel' => __('client.products.delete', ['name' => $product->name]),
            'deleteTitle' => __('client.products.delete_title', ['name' => $product->name]),
        ];
    }

    /**
     * The whole screen in one answer: the window of the same cut the web paints and exports,
     * every row said in the web's words.
     *
     * @return array<string, mixed>
     */
    public function page(string $search, string $filter, int $visible): array
    {
        $filtered = $this->filtered($search, $filter);
        $rows = $filtered->values()->take($visible);

        return [
            'any' => $this->business->products()->exists(),
            'rows' => $rows->map(fn (Product $product): array => $this->rowOf($product))->all(),
            'countLabel' => trans_choice('client.products.count', $filtered->count(), ['count' => $filtered->count()]),
            'shown' => $rows->count(),
            'total' => $filtered->count(),
            'showing' => __('pagination.showing', ['shown' => $rows->count(), 'total' => $filtered->count()]),
            'hasMore' => $rows->count() < $filtered->count(),
        ];
    }

    /**
     * The sheet the web opens: the stored boxes (or a blank one), the types to pick
     * (producto ones only) and the type's own fields.
     *
     * @return array<string, mixed>
     */
    public function sheetOf(?Product $product): array
    {
        $dto = $product === null ? new ProductDto : ProductDto::fromArray($product->toArray());
        // The decimal cast prints "185000.00"; the owner typed "185000" and finds it back that way.
        $trim = fn (?string $value): ?string => $value === null ? null : rtrim(rtrim($value, '0'), '.');

        return [
            'id' => $product?->id,
            'title' => $product === null ? __('client.products.sheet_new_title') : __('client.products.sheet_title', ['name' => $product->name]),
            'values' => [
                'name' => $dto->name,
                'code' => $dto->code,
                'price' => $trim($dto->price),
                'stock' => $trim($dto->stock),
                'service_type_id' => $dto->service_type_id,
                'description' => $dto->description,
                'in_stock' => $dto->in_stock,
                'attribute_values' => (object) $dto->attribute_values,
            ],
            'types' => collect($this->typeOptions)->map(fn (string $label, int $id): array => ['id' => $id, 'name' => $label])->values()->all(),
            'attributes' => $this->attributeSet($dto->service_type_id),
        ];
    }
}
