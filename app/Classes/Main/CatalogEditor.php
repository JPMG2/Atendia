<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Interfaces\Catalog\EditableFromPhone;
use App\Livewire\Forms\Catalog\AdoptionNudgeForm;
use App\Livewire\Forms\Catalog\BusinessActivityForm;
use App\Livewire\Forms\Catalog\BusinessSectorForm;
use App\Livewire\Forms\Catalog\CountryForm;
use App\Livewire\Forms\Catalog\CountryHolidayForm;
use App\Livewire\Forms\Catalog\CurrencyForm;
use App\Livewire\Forms\Catalog\CurrentStatusForm;
use App\Livewire\Forms\Catalog\MentionGoalForm;
use App\Livewire\Forms\Catalog\ProvinceForm;
use App\Livewire\Forms\Catalog\RegionForm;
use App\Livewire\Forms\Catalog\SeasonalWindowForm;
use App\Livewire\Forms\Catalog\ServiceAttributeForm;
use App\Livewire\Forms\Catalog\ServiceModalityForm;
use App\Livewire\Forms\Catalog\SocialNetworkForm;
use App\Livewire\Forms\Catalog\SupportReplyForm;
use App\Livewire\Forms\Catalog\SupportTicketKindForm;
use App\Livewire\Forms\Catalog\TaxConditionForm;
use App\Livewire\Forms\Catalog\WhatsappVariantForm;
use App\Models\CatalogForm;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

/**
 * One catalog master edited from the phone with what the web form uses: its
 * DTO to normalise, its rules and names to validate, its Actions to save and
 * `CatalogRemoval` to delete. Only masters listed in `FORMS` are editable;
 * the lock is the web's, the hub's permission and the master's own.
 */
final class CatalogEditor
{
    /** @var array<string, class-string<EditableFromPhone>> */
    public const array FORMS = [
        'currency' => CurrencyForm::class,
        'status' => CurrentStatusForm::class,
        'country' => CountryForm::class,
        'province' => ProvinceForm::class,
        'region' => RegionForm::class,
        'social-network' => SocialNetworkForm::class,
        'tax-condition' => TaxConditionForm::class,
        'business-sector' => BusinessSectorForm::class,
        'mention-goal' => MentionGoalForm::class,
        'support-reply' => SupportReplyForm::class,
        'business-activity' => BusinessActivityForm::class,
        'service-modality' => ServiceModalityForm::class,
        'support-ticket-kind' => SupportTicketKindForm::class,
        'adoption-nudge' => AdoptionNudgeForm::class,
        'service-attribute' => ServiceAttributeForm::class,
        'seasonal-window' => SeasonalWindowForm::class,
        'whatsapp-variant' => WhatsappVariantForm::class,
        'country-holiday' => CountryHolidayForm::class,
    ];

    /** Masters whose web form offers no delete (a holiday is switched off, never removed): nor does the phone. */
    public const array UNDELETABLE = ['country-holiday'];

    /** The field the web form's header shows next to "Editar" (its `title-key`); `name` when it says none. */
    public const array TITLE_KEYS = [
        'currency' => 'code',
        'country' => 'code',
        'tax-condition' => 'code',
    ];

    public function __construct(
        public readonly string $slug,
        private readonly ?User $user,
    ) {}

    /** The master's row of the hub when this person may open it and the phone edits it; null otherwise. */
    public ?CatalogForm $form {
        get => array_key_exists($this->slug, self::FORMS) ? (new CatalogTable($this->slug, $this->user))->form : null;
    }

    /** @var class-string<EditableFromPhone> */
    private string $spec {
        get => self::FORMS[$this->slug];
    }

    /** @var class-string<Model> */
    private string $model {
        get => $this->spec::wiring()->model;
    }

    /** Whether a row of this master may be removed at all: an edit-only master or one the web never deletes may not. */
    public bool $deletable {
        get => $this->spec::wiring()->create !== null && ! in_array($this->slug, self::UNDELETABLE, true);
    }

    /** The words the list adds when the master is editable: its create button. */
    public string $createLabel {
        get => __('catalog.'.$this->words.'.create');
    }

    /**
     * The form as the phone draws it: the web's fields with today's values (a
     * new record starts from the DTO's blanks), and the words around them.
     * Null when the record is gone.
     *
     * @return array<string, mixed>|null
     */
    public function sheet(?int $id): ?array
    {
        $record = $id === null ? null : $this->model::editable($id);

        if ($id !== null && $record === null) {
            return null;
        }

        $dto = $this->spec::wiring()->dto;
        $state = ($record === null ? new $dto : $dto::fromArray($record->toArray()))->toArray();
        // A week is always its seven days, as the web's grid draws it: a blank one opens a day at 09:00–18:00, not two empty boxes.
        $fields = array_map(fn (array $field): array => [
            ...$field,
            'value' => $field['type'] === 'week' ? OpeningHours::formState($state[$field['key']] ?? null) : ($state[$field['key']] ?? null),
        ], $this->spec::phoneFields());
        // A master with no `name` (a goal is a number) is named by its first field, never by a blank.
        $titleKey = self::TITLE_KEYS[$this->slug] ?? (array_key_exists('name', $state) ? 'name' : $fields[0]['key']);

        return [
            'id' => $id,
            // The web's header: "Editando" over the record's identifier, or the new record's title.
            'title' => $record === null ? __('catalog.'.$this->words.'.new_title') : $this->titleOf($fields, $titleKey, $state[$titleKey] ?? null),
            'eyebrow' => $record === null ? null : __('catalog.common.editing'),
            'fields' => $fields,
            'deletable' => $record !== null && $this->deletable,
            'copy' => [
                'save' => $record === null ? $this->createLabel : __('catalog.common.save'),
                'delete' => __('catalog.common.delete'),
                'cancel' => __('catalog.common.cancel'),
                'unsaved' => __('client.business.unsaved_pill'),
                'leave_title' => __('client.business.unsaved.title'),
                'leave_message' => __('client.business.unsaved.message'),
                'leave_accept' => __('client.business.unsaved.accept'),
            ],
        ];
    }

    /**
     * Creates (no id) or updates a record. A body is partial on an edit: what
     * it does not name stays as stored. The web's DTO normalises it, its rules
     * validate it (a 422 keyed by field) and its Action writes it.
     *
     * @param  array<string, mixed>  $body
     * @return array{model: Model, message: string, offer: array<string, mixed>|null}|null null when the record to update is gone
     */
    public function save(?int $id, array $body): ?array
    {
        $wiring = $this->spec::wiring();
        $record = $id === null ? null : $this->model::editable($id);

        if ($id !== null && $record === null) {
            return null;
        }

        $dto = $wiring->dto;
        $state = ($record === null ? new $dto : $dto::fromArray($record->toArray()))->toArray();
        $draft = $dto::fromArray([...$state, ...$this->shaped($body)]);
        $payload = $draft->toPayload();

        // The form's own state rides along: a holiday's `kind` is not a column, yet it decides which date is demanded.
        $whole = [...$draft->toArray(), ...$payload];
        $rules = $this->spec::fieldRules($id, $whole);
        // A ruled form field that is no column (a holiday's `kind`) is checked too: the phone, unlike the browser, can send anything.
        $checked = [...array_intersect_key($whole, $rules), ...$payload];

        // The Action gets only the columns: the rest was checked, never written.
        $validated = array_intersect_key(Validator::make($checked, $rules, [], $this->spec::fieldNames())->validate(), $payload);

        $model = $record === null
            ? app($wiring->create)->handle($validated)
            : app($wiring->update)->handle($id, $validated);

        return [
            'model' => $model,
            'message' => app(NotificationService::class)->notificationFor($model, $record === null ? 'created' : 'updated')->message,
            // What the web asks after this save, if the master asks anything (a holiday: tell the country's businesses?).
            'offer' => method_exists($this->spec, 'offerAfterSave') ? $this->spec::offerAfterSave($record, $model->refresh()) : null,
        ];
    }

    /**
     * What the body names of the form's fields, each checked for its kind and
     * cast to it BEFORE the DTO sees it: the web's browser never sends "1,5"
     * to a number or a word to a switch, the phone or any other caller may, and
     * that is a field error (422), never a crash. A null switch is refused too.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function shaped(array $body): array
    {
        $fields = collect($this->spec::phoneFields())->keyBy('key');
        $sent = array_intersect_key($body, $fields->all());

        $kinds = $fields->map(fn (array $field): array => match ($field['type']) {
            'number' => ['sometimes', 'nullable', 'integer'],
            'switch' => ['sometimes', 'boolean'],
            'week' => ['sometimes', 'nullable', 'array'],
            'select' => ['sometimes', 'nullable', is_int($field['options'][0]['value'] ?? null) ? 'integer' : 'string'],
            default => ['sometimes', 'nullable', 'string'],
        })->all();

        // A week is seven days at most, each a switch and two hours: a day that is not one is refused, not dropped.
        foreach ($fields->where('type', 'week')->keys() as $week) {
            $kinds[$week][] = fn (string $attribute, mixed $value, \Closure $fail): mixed => is_array($value) && array_diff(array_map('strval', array_keys($value)), ['0', '1', '2', '3', '4', '5', '6']) !== []
                ? $fail(__('validation.array', ['attribute' => $this->spec::fieldNames()[$week] ?? $week]))
                : null;
            $kinds["{$week}.*"] = ['array'];
            $kinds["{$week}.*.open"] = ['sometimes', 'boolean'];
            $kinds["{$week}.*.opens_at"] = ['sometimes', 'nullable', 'string'];
            $kinds["{$week}.*.closes_at"] = ['sometimes', 'nullable', 'string'];
        }

        Validator::make($sent, $kinds, [], $this->spec::fieldNames())->validate();

        return collect($sent)->map(fn (mixed $value, string $key): mixed => match (true) {
            $value === null => null,
            end($kinds[$key]) === 'integer' => (int) $value,
            end($kinds[$key]) === 'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            default => $value,
        })->all();
    }

    /**
     * A list's value is named by the words of its option, never by its key.
     *
     * @param  list<array<string, mixed>>  $fields
     */
    private function titleOf(array $fields, string $key, mixed $value): string
    {
        $options = collect($fields)->firstWhere('key', $key)['options'] ?? null;

        return (string) (is_array($options) ? (collect($options)->firstWhere('value', $value)['label'] ?? $value) : $value);
    }

    public CatalogRemoval $removal {
        get => new CatalogRemoval(new ($this->model));
    }

    /** The master's key in `lang/catalog.php`. */
    private string $words {
        get => str_replace('-', '_', $this->slug);
    }
}
