<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Actions\Business\DeleteBusinessService;
use App\Actions\Business\SaveBusinessCategoryOrder;
use App\Actions\Business\SaveBusinessService;
use App\Actions\Business\SaveBusinessServiceCategory;
use App\Actions\Business\SyncOfferKnowledge;
use App\Actions\Business\ToggleBusinessService;
use App\Dto\ServiceDto;
use App\Livewire\Forms\Client\ServiceCategoryForm;
use App\Models\Business;
use App\Models\BusinessActivity;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

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
     * The offer as the screen shows it: an accent-free search over the name and
     * a state filter (all, active, paused). ORIGINAL KEYS KEPT: the screen slices
     * by position, and the export reads the same cut.
     *
     * @return Collection<int, Service>
     */
    public function filtered(string $search = '', string $filter = 'all'): Collection
    {
        $needle = Str::ascii(mb_strtolower(trim($search)));

        return $this->services->filter(fn (Service $service): bool => match ($filter) {
            'active' => (bool) $service->is_active,
            'paused' => ! $service->is_active,
            default => true,
        } && ($needle === '' || str_contains(Str::ascii(mb_strtolower($service->name)), $needle)));
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

    /** @return string One of DeleteBusinessService's constants. */
    public function remove(int $id): string
    {
        $result = app(DeleteBusinessService::class)->handle($this->business, $id);

        if ($result === DeleteBusinessService::DELETED) {
            app(SyncOfferKnowledge::class)->handle($this->business, 'services');
        }

        return $result;
    }

    /** The "Deshacer" of {@see remove()}: brings the service back with its photos and its price. */
    public function restore(int $id): bool
    {
        $service = $this->business->services()->onlyTrashed()->find($id);

        if ($service === null) {
            return false;
        }

        $service->restore();

        app(SyncOfferKnowledge::class)->handle($this->business, 'services');

        return true;
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

    /**
     * Drops one shelf at a position among the others, as the web's drag does.
     *
     * @return bool false when the shelf is not this business's
     */
    public function moveCategory(int $categoryId, int $position): bool
    {
        $ids = $this->categories->pluck('id')->all();

        if (! in_array($categoryId, $ids, true)) {
            return false;
        }

        $order = array_values(array_diff($ids, [$categoryId]));
        array_splice($order, max(0, $position), 0, [$categoryId]);

        $this->reorderCategories($order);

        return true;
    }

    /**
     * Puts one shelf right before or after another, whatever shelves lie between them that the
     * screen does not draw (an empty one has no header).
     *
     * @return bool false when either shelf is not this business's
     */
    public function moveCategoryNear(int $categoryId, int $neighbourId, bool $after): bool
    {
        $ids = $this->categories->pluck('id')->all();

        if ($categoryId === $neighbourId || ! in_array($categoryId, $ids, true) || ! in_array($neighbourId, $ids, true)) {
            return false;
        }

        $order = array_values(array_diff($ids, [$categoryId]));
        array_splice($order, array_search($neighbourId, $order, true) + ($after ? 1 : 0), 0, [$categoryId]);

        $this->reorderCategories($order);

        return true;
    }

    /**
     * The words the app prints for "Tus servicios", the web screen's own.
     *
     * @var array<string, mixed>
     */
    public array $copy {
        get => [
            'title' => __('client.services.title'),
            'sub' => __('client.services.sub'),
            'add' => __('client.services.add'),
            'addCategory' => __('client.services.add_category'),
            'search' => __('client.services.search_placeholder'),
            'filters' => [
                'all' => __('client.services.filter_all'),
                'active' => __('client.services.filter_active'),
                'paused' => __('client.services.filter_paused'),
            ],
            'noResults' => __('client.services.no_results'),
            'loadMore' => __('pagination.load_more'),
            'suggestions' => __('client.services.suggestions'),
            'emptyTitle' => __('client.services.empty_title'),
            'emptyBody' => __('client.services.empty_body'),
            'undo' => __('client.services.undo'),
            'deleteMessage' => __('client.services.delete_message'),
            'deleteAccept' => __('client.services.delete_accept'),
            'sheet' => [
                'new' => __('client.services.sheet_new_title'),
                'hint' => __('client.services.sheet_hint'),
                'cancel' => __('client.services.sheet_cancel'),
                'save' => __('client.services.sheet_save'),
                'fields' => [
                    'name' => __('client.services.field_name'),
                    'category' => __('client.services.field_category'),
                    'type' => __('client.services.field_type'),
                    'minutes' => __('client.services.field_minutes'),
                    'priceType' => __('client.services.field_price_type'),
                    'amount' => __('client.services.field_amount'),
                    'deposit' => __('client.services.field_deposit'),
                    'description' => __('client.services.field_description'),
                    'prep' => __('client.services.field_prep'),
                ],
                'helps' => [
                    'type' => __('client.services.type_help'),
                    'deposit' => __('client.services.deposit_help'),
                    'description' => __('client.services.description_help'),
                    'prep' => __('client.services.prep_help'),
                    'featured' => __('client.services.featured_help'),
                ],
                'offered' => __('client.services.offered'),
                'featured' => __('client.services.featured'),
                'uncategorized' => __('client.services.uncategorized'),
                'newCategory' => __('client.services.category_new_option'),
                'priceTypes' => [
                    'fixed' => __('client.services.price_fixed'),
                    'from' => __('client.services.price_from'),
                    'free' => __('client.services.price_free'),
                    'talk' => __('client.services.price_talk'),
                ],
            ],
            'category' => [
                'title' => __('client.services.category_sheet_title'),
                'hint' => __('client.services.category_sheet_hint'),
                'name' => __('client.services.field_category_name'),
                'save' => __('client.services.category_save'),
                'max' => ServiceCategoryForm::NAME_MAX,
            ],
        ];
    }

    /**
     * One row as the web's row says it: the badge, the price in its own words, the
     * length and the deposit, and the name already inside each action's label.
     *
     * @return array<string, mixed>
     */
    public function rowOf(Service $service): array
    {
        $price = match (true) {
            $service->price_type === 'free' => ['kind' => 'free', 'label' => __('client.services.price_free')],
            $service->price_type === 'talk' => ['kind' => 'talk', 'label' => __('client.services.price_talk')],
            $service->price !== null => ['kind' => 'amount', 'label' => $service->price_type === 'from'
                ? __('client.services.price_from_amount', ['amount' => $service->priceAmount()])
                : '$ '.$service->priceAmount()],
            default => ['kind' => 'none', 'label' => __('client.services.no_price')],
        };

        return [
            'id' => $service->id,
            'name' => $service->name,
            'description' => $service->description,
            'featured' => (bool) $service->is_featured,
            'active' => (bool) $service->is_active,
            'status' => __($service->is_active ? 'client.services.active' : 'client.services.paused'),
            'price' => $price,
            'minutes' => $service->duration_minutes === null ? null : $service->duration_minutes.' min',
            'deposit' => $service->deposit === null ? null : __('client.services.deposit_short', ['amount' => number_format((float) $service->deposit, 0, ',', '.')]),
            'editLabel' => __('client.services.edit', ['name' => $service->name]),
            'toggleLabel' => __($service->is_active ? 'client.services.pause' : 'client.services.resume', ['name' => $service->name]),
            'deleteLabel' => __('client.services.delete', ['name' => $service->name]),
            'deleteTitle' => __('client.services.delete_title', ['name' => $service->name]),
        ];
    }

    /**
     * The whole screen in one answer: the cut of {@see listing()} with every row said in the
     * web's words, the meter of priced services, and what an empty business is offered.
     *
     * @param  list<string>  $collapsed
     * @return array<string, mixed>
     */
    public function page(string $search, string $filter, int $visible, array $collapsed = []): array
    {
        $listing = $this->listing($search, $filter, $visible, $collapsed);
        $activity = $this->business->primaryActivity();

        return [
            'any' => $listing['all'] > 0,
            'grouped' => $listing['grouped'],
            'groups' => array_map(fn (array $group): array => [...$group, 'rows' => array_map(fn (Service $service): array => $this->rowOf($service), $group['rows'])], $listing['groups']),
            'rows' => array_map(fn (Service $service): array => $this->rowOf($service), $listing['flat']),
            'shown' => $listing['shown'],
            'total' => $listing['total'],
            'showing' => __('pagination.showing', ['shown' => $listing['shown'], 'total' => $listing['total']]),
            'hasMore' => $listing['shown'] < $listing['total'],
            'countLabel' => trans_choice('client.services.count', $listing['count'], ['count' => $listing['count']]),
            'meter' => [
                'label' => __('client.services.meter', ['done' => $listing['priced'], 'total' => $listing['all']]),
                'percent' => $listing['all'] > 0 ? (int) round($listing['priced'] / $listing['all'] * 100) : 0,
            ],
            // The trade's own names: what the empty screen offers as a first service.
            'suggestions' => $listing['all'] > 0 || $activity === null ? [] : BusinessActivity::suggestionsName(code: $activity->code),
        ];
    }

    /**
     * The sheet the web opens: the stored boxes (or a blank one, with the name a suggestion
     * chip may have typed), the shelves and types to pick from, and the type's own fields.
     *
     * @return array<string, mixed>
     */
    public function sheetOf(?Service $service, string $name = ''): array
    {
        $dto = $service === null ? new ServiceDto(name: $name) : ServiceDto::fromArray($service->toArray());
        // The decimal cast prints "12000.00"; the owner typed "12000" and finds it back that way.
        $trim = fn (?string $value): ?string => $value === null ? null : rtrim(rtrim($value, '0'), '.');

        return [
            'id' => $service?->id,
            'title' => $service === null ? __('client.services.sheet_new_title') : __('client.services.sheet_title', ['name' => $service->name]),
            'deleteTitle' => $service === null ? null : __('client.services.delete_title', ['name' => $service->name]),
            'values' => [
                'name' => $dto->name,
                'service_category_id' => $dto->service_category_id,
                'service_type_id' => $dto->service_type_id,
                'price_type' => $dto->price_type,
                'price' => $trim($dto->price),
                'deposit' => $trim($dto->deposit),
                'duration_minutes' => $dto->duration_minutes,
                'description' => $dto->description,
                'prep_note' => $dto->prep_note,
                'is_active' => $dto->is_active,
                'is_featured' => $dto->is_featured,
                'attribute_values' => (object) $dto->attribute_values,
            ],
            'categories' => $this->categories->map(fn (ServiceCategory $category): array => ['id' => $category->id, 'name' => $category->name])->all(),
            'types' => collect($this->typeOptions)->map(fn (string $label, int $id): array => ['id' => $id, 'name' => $label])->values()->all(),
            'attributes' => $this->attributeSet($dto->service_type_id),
        ];
    }

    /**
     * The list the screen draws: shelves (Destacados first, then the client's
     * own, then the loose ones) while nothing is searched or filtered, a flat
     * list of hits otherwise. `$visible` is the window the "load more" grows;
     * a collapsed shelf keeps its header and hides its rows. One place, so the
     * web screen and the app can never count or group differently.
     *
     * @param  list<string>  $collapsed  Shelf keys folded away ('_featured', a category id, '' for the loose ones).
     * @return array{
     *     grouped: bool,
     *     groups: list<array{key: string, name: string, count: int, collapsed: bool, featured: bool, rows: list<Service>}>,
     *     flat: list<Service>,
     *     shown: int,
     *     total: int,
     *     count: int,
     *     priced: int,
     *     all: int
     * }
     */
    public function listing(string $search, string $filter, int $visible, array $collapsed = []): array
    {
        $services = $this->services;
        $categories = $this->categories;
        $filtered = $this->filtered($search, $filter);
        $grouped = trim($search) === '' && $filter === 'all' && $categories->isNotEmpty();

        $flat = $filtered->sortByDesc('is_featured')->values()->take($visible)->all();
        $groups = $grouped ? $this->shelves($services, $categories, $visible, $collapsed) : [];

        $featuredCount = in_array('_featured', $collapsed, true) ? 0 : $services->filter(fn (Service $service): bool => $service->is_featured)->count();

        return [
            'grouped' => $grouped,
            'groups' => $groups,
            'flat' => $flat,
            // Rows actually on screen: what the load-more footer counts.
            'shown' => $grouped ? array_sum(array_map(fn (array $group): int => count($group['rows']), $groups)) : count($flat),
            // Loadable rows: a collapsed shelf keeps its own out of the count.
            'total' => $grouped
                ? $featuredCount + $services->filter(fn (Service $service): bool => ! in_array((string) ($service->service_category_id ?? ''), $collapsed, true))->count()
                : $filtered->count(),
            'count' => $filtered->count(),
            'priced' => $services->filter(fn (Service $service): bool => $service->hasResolvedPrice())->count(),
            'all' => $services->count(),
        ];
    }

    /**
     * @param  Collection<int, Service>  $services
     * @param  Collection<int, ServiceCategory>  $categories
     * @param  list<string>  $collapsed
     * @return list<array{key: string, name: string, count: int, collapsed: bool, featured: bool, rows: list<Service>}>
     */
    private function shelves(Collection $services, Collection $categories, int $visible, array $collapsed): array
    {
        // WhatsApp collections repeat a product across collections: a featured row keeps its own shelf too.
        $buckets = [['_featured', __('client.services.featured_title'), $services->filter(fn (Service $service): bool => $service->is_featured)]];

        foreach ($categories as $category) {
            $buckets[] = [(string) $category->id, $category->name, $services->where('service_category_id', $category->id)];
        }

        $buckets[] = ['', __('client.services.uncategorized'), $services->whereNull('service_category_id')];

        $groups = [];
        $shown = 0;

        foreach ($buckets as [$key, $name, $members]) {
            if ($members->isEmpty()) {
                continue;
            }

            if ($shown >= $visible) {
                break;
            }

            $folded = in_array($key, $collapsed, true);
            $rows = [];

            if (! $folded) {
                foreach ($members as $service) {
                    if ($shown >= $visible) {
                        break;
                    }

                    $rows[] = $service;
                    $shown++;
                }
            }

            $groups[] = [
                'key' => $key,
                'name' => $name,
                'count' => $members->count(),
                'collapsed' => $folded,
                'featured' => $key === '_featured',
                'rows' => $rows,
            ];
        }

        return $groups;
    }
}
