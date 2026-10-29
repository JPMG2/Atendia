<?php

declare(strict_types=1);

namespace App\Classes\Report;

use App\Enums\ReportAudience;
use App\Interfaces\Catalog\DataTable;
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

        return $this->records()
            ->map(fn (array $row): array => array_map(fn (string $key): string|int|float => $this->cell($row[$key] ?? null), $keys))
            ->all();
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
     * the list's worded copies of a key already exported ("US$ 79", "Hereda", the tags) would say it twice.
     *
     * @return list<string>
     */
    private function keys(): array
    {
        return array_values(array_diff(array_keys($this->records()->first() ?? ['name' => '']), [
            'id', 'icon_svg', 'icon_filled',
            'price_label', 'conversations_label', 'tags', 'business_shown', 'business_inherits', 'season_shown', 'landing_url',
            'content_shown', 'content_empty', 'phone_shown', 'phone_inherits', 'hours_shown', 'hours_inherits', 'inherits_note', 'company_url',
        ]));
    }

    private function heading(string $key): string
    {
        $master = $this->master();

        foreach (['catalog.'.str_replace('-', '_', $master).'.columns.'.$key, 'catalog.'.$master.'.columns.'.$key, 'reports/admin.catalog.keys.'.$key] as $path) {
            if (Lang::has($path)) {
                return (string) __($path);
            }
        }

        return Str::headline($key);
    }

    private function cell(mixed $value): string|int|float
    {
        return match (true) {
            is_bool($value) => $value ? __('reports/admin.yes') : __('reports/admin.no'),
            is_int($value), is_float($value) => $value,
            is_array($value) => collect($value)->filter(fn (mixed $item): bool => is_scalar($item))->implode(', '),
            is_scalar($value) => (string) $value,
            default => '',
        };
    }
}
