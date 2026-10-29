<?php

declare(strict_types=1);

namespace App\Classes\Report;

use App\Dto\CatalogColumnDto;
use App\Enums\ReportAudience;
use App\Interfaces\Catalog\DataTable;
use App\Interfaces\Catalog\HasColumns;
use App\Models\AdoptionNudge;
use App\Models\BusinessActivity;
use App\Models\BusinessSector;
use App\Models\CatalogForm;
use App\Models\Country;
use App\Models\CountryHoliday;
use App\Models\Currency;
use App\Models\CurrentStatus;
use App\Models\DemoTag;
use App\Models\MentionGoal;
use App\Models\Province;
use App\Models\Region;
use App\Models\SeasonalWindow;
use App\Models\ServiceAttribute;
use App\Models\ServiceModality;
use App\Models\ServiceType;
use App\Models\SocialNetwork;
use App\Models\SubscriptionPlan;
use App\Models\SupportReply;
use App\Models\SupportTicketKind;
use App\Models\TaxCondition;
use App\Models\User;
use App\Models\WhatsappVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/**
 * Any master of the catalog hub as a file. The hub lists every master through
 * the same `catalogRows()` contract, so one report serves all of them: the
 * master is named in the request and its own permission is the lock.
 */
class CatalogReport extends ListReport
{
    /** @var array<string, class-string<DataTable>> The master (the part of its component after "catalog.") and the model behind it. */
    public const MASTERS = [
        'adoption-nudge' => AdoptionNudge::class,
        'business-activity' => BusinessActivity::class,
        'business-sector' => BusinessSector::class,
        'country' => Country::class,
        'country-holiday' => CountryHoliday::class,
        'currency' => Currency::class,
        'demo-tag' => DemoTag::class,
        'mention-goal' => MentionGoal::class,
        'plan' => SubscriptionPlan::class,
        'province' => Province::class,
        'region' => Region::class,
        'seasonal-window' => SeasonalWindow::class,
        'service-attribute' => ServiceAttribute::class,
        'service-modality' => ServiceModality::class,
        'service-type' => ServiceType::class,
        'social-network' => SocialNetwork::class,
        'status' => CurrentStatus::class,
        'support-reply' => SupportReply::class,
        'support-ticket-kind' => SupportTicketKind::class,
        'tax-condition' => TaxCondition::class,
        'whatsapp-variant' => WhatsappVariant::class,
    ];

    /**
     * A key whose cell the screen draws from its worded copy, so the file says that word in its place:
     * "inherits" or "all year" rather than a blank, "switched off" rather than a bare no. The switch of
     * every master is worded apart, by `status()`.
     *
     * @var array<string, array<string, string>>
     */
    private const array EXPORTED_AS_SHOWN = [
        'demo-tag' => ['business_name' => 'business_shown', 'season' => 'season_shown'],
        'seasonal-window' => ['running' => 'running_shown'],
        'whatsapp-variant' => ['phone' => 'phone_shown', 'opening_hours' => 'hours_shown', 'running' => 'running_shown'],
    ];

    public string $title {
        get => __('reports/admin.catalog.title', ['master' => $this->form()?->title ?? '']);
    }

    public string $filename {
        get => __('reports/admin.catalog.filename').'-'.$this->master();
    }

    public array $columns {
        get => array_map($this->heading(...), $this->keys());
    }

    public ReportAudience $audience {
        get => ReportAudience::Platform;
    }

    public int $total {
        get => $this->records()->count();
    }

    public function authorize(User $user): bool
    {
        $form = $this->form();

        return $form !== null && $form->is_active && ($form->permission_key === null || $user->can($form->permission_key));
    }

    protected function rows(ReportScope $scope): array
    {
        $keys = $this->keys();
        $shown = self::EXPORTED_AS_SHOWN[$this->master()] ?? [];
        $badges = $this->badges();

        return $this->records()
            ->map(fn (array $row): array => array_map(fn (string $key): string|int|float => match (true) {
                $key === 'active' => $this->status((bool) $row[$key]),
                is_string($row[$key]) && isset($badges[$key][$row[$key]]) => $badges[$key][$row[$key]]['label'],
                default => $this->cell($row[$shown[$key] ?? $key] ?? $row[$key] ?? null),
            }, $keys))
            ->all();
    }

    /**
     * The label each value of a badge column wears on the screen ("fixed" reads "Cada año").
     *
     * @return array<string, array<string, array{label: string, tone: string}>>
     */
    private function badges(): array
    {
        $model = self::MASTERS[$this->master()] ?? null;

        return $model !== null && is_subclass_of($model, HasColumns::class)
            ? collect((new $model)->catalogColumns())->filter(fn (CatalogColumnDto $column): bool => $column->badges !== [])->mapWithKeys(fn (CatalogColumnDto $column): array => [$column->key => $column->badges])->all()
            : [];
    }

    protected function shown(ReportScope $scope): int
    {
        return $this->total;
    }

    protected function filters(ReportScope $scope): array
    {
        return [];
    }

    /** The master named in the request; anything outside the registry is no master at all. */
    private function master(): string
    {
        $named = ReportScope::fromRequest()->value('maestro');

        return array_key_exists($named, self::MASTERS) ? $named : '';
    }

    private function form(): ?CatalogForm
    {
        return $this->master() === '' ? null : CatalogForm::query()->where('component', 'catalog.'.$this->master())->first();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function records(): Collection
    {
        $model = self::MASTERS[$this->master()] ?? null;

        return $model === null ? new Collection : (new $model)->catalogRows();
    }

    /**
     * The row keys that mean something to a person; the id is bookkeeping, the glyph is markup, and
     * the list's worded copies of a key already exported ("US$ 79", "Hereda") would say it twice. A plan's
     * flags go out as the tags its screen draws ("Más elegido", "14 días de prueba"), not as yes or no.
     *
     * @return list<string>
     */
    private function keys(): array
    {
        return array_values(array_diff(array_keys($this->records()->first() ?? ['name' => '']), [
            'id', 'icon_svg', 'icon_filled',
            'price_label', 'conversations_label', 'featured', 'trial', 'archived', 'business_shown', 'business_inherits', 'season_shown', 'landing_url',
            'content_shown', 'content_empty', 'phone_shown', 'phone_inherits', 'hours_shown', 'hours_inherits', 'inherits_note', 'company_url', 'running_shown', 'dates',
        ]));
    }

    private function heading(string $key): string
    {
        $master = $this->master();

        if ($key === 'active' && Lang::has($this->wording().'.columns.status')) {
            return (string) __($this->wording().'.columns.status');
        }

        foreach (['catalog.'.str_replace('-', '_', $master).'.columns.'.$key, 'catalog.'.$master.'.columns.'.$key, 'reports/admin.catalog.keys.'.$key] as $path) {
            if (Lang::has($path)) {
                return (string) __($path);
            }
        }

        return Str::headline($key);
    }

    /** The lang group of the master's screen, where its own words live. */
    private function wording(): string
    {
        return 'catalog.'.str_replace('-', '_', $this->master());
    }

    /** The switch in the master's own words and gender ("Activa", "Vigente", "Apagada"), as its Status column draws it. */
    private function status(bool $active): string
    {
        $path = $this->wording().'.status.'.($active ? 'active' : 'inactive');

        return Lang::has($path) ? (string) __($path) : (string) $this->cell($active);
    }

    private function cell(mixed $value): string|int|float
    {
        return match (true) {
            is_bool($value) => $value ? __('reports/admin.yes') : __('reports/admin.no'),
            is_int($value), is_float($value) => $value,
            is_array($value) => collect($value)->map(fn (mixed $item): mixed => is_array($item) ? $item['label'] ?? null : $item)->filter(fn (mixed $item): bool => is_scalar($item))->implode(', '),
            is_scalar($value) => (string) $value,
            default => '',
        };
    }
}
