<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Dto\BusinessDto;
use App\Dto\DtoCast;
use App\Livewire\Forms\Client\AppointmentsForm;
use App\Livewire\Forms\Client\BillingForm;
use App\Livewire\Forms\Client\ContactForm;
use App\Livewire\Forms\Client\IdentityForm;
use App\Livewire\Forms\Client\LocationForm;
use App\Livewire\Forms\Client\SocialForm;
use App\Models\Business;
use App\Models\Company;
use App\Models\Country;
use App\Models\Currency;
use App\Models\SocialLink;
use App\Models\SocialNetwork;
use App\Models\TaxCondition;
use Illuminate\Support\Facades\Storage;

/**
 * The editable cards of "Mi negocio" as the mobile app asks for them: the
 * words, the fields and what each holds today, from the web's own copy and
 * data, and the very rules the web's forms validate with. A card is described
 * once here; the phone only draws it. The save is the web's Action behind each
 * card, reached through the client's pieces, never from here.
 */
class BusinessCards
{
    /** @var list<string> */
    public const array KEYS = ['identity', 'location', 'appointments', 'contact', 'billing', 'social'];

    public function __construct(private Business $business) {}

    /**
     * One card: its words, its fields with today's value, and the notes the
     * web prints beside the same fields.
     *
     * @return array<string, mixed>
     */
    public function card(string $key): array
    {
        $data = BusinessDto::fromArray($this->business->toArray());

        return [
            'key' => $key,
            'copy' => $this->copy($key),
            ...match ($key) {
                'identity' => $this->identity($data),
                'location' => $this->location($data),
                'appointments' => $this->appointments($data),
                'contact' => $this->contact($data),
                'billing' => $this->billing($data),
                'social' => $this->social(),
            },
        ];
    }

    /**
     * The links as the screen keeps them, in their public order, and the
     * networks that can be picked. A list, not a form: the phone draws rows.
     *
     * @return array<string, mixed>
     */
    private function social(): array
    {
        return [
            'words' => [
                'network' => __('client.business.social.network'),
                'network_placeholder' => __('client.business.social.network_placeholder'),
                'url' => __('client.business.social.url'),
                'url_placeholder' => __('client.business.social.url_placeholder'),
                'add' => __('client.business.social.add'),
                'remove' => __('client.business.social.remove'),
            ],
            'networks' => SocialNetwork::options(states: [true]),
            'rows' => (new SocialMedia($this->business))->links
                ->map(fn (SocialLink $link): array => ['social_network_id' => $link->social_network_id, 'url' => (string) $link->url])
                ->values()
                ->all(),
        ];
    }

    /**
     * The web form's rules for this card.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rulesFor(string $key): array
    {
        return match ($key) {
            'identity' => IdentityForm::fieldRules(),
            'location' => LocationForm::fieldRules(),
            'appointments' => AppointmentsForm::fieldRules(),
            'contact' => ContactForm::fieldRules(),
            'billing' => BillingForm::fieldRules($this->business->country_id),
            'social' => SocialForm::fieldRules(),
        };
    }

    /**
     * @return array<string, string>
     */
    public function namesFor(string $key): array
    {
        return match ($key) {
            'identity' => IdentityForm::fieldNames(),
            'location' => LocationForm::fieldNames(),
            'appointments' => AppointmentsForm::fieldNames(),
            'contact' => ContactForm::fieldNames(),
            'billing' => BillingForm::fieldNames(),
            'social' => SocialForm::fieldNames(),
        };
    }

    /**
     * What the web's form does to a value before it validates it (squishing,
     * lower-casing), and the card's own keys put where the form keeps them.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function tidy(string $key, array $input): array
    {
        return match ($key) {
            // The links are replaced as a whole list, so a body without one leaves them as they are.
            'social' => array_key_exists('social', $input) ? ['social' => $this->filledSocialRows((array) $input['social'])] : [],
            'location' => [
                'hasPremises' => $input['has_premises'] ?? null,
                'address' => DtoCast::squish($input['address'] ?? null),
                'city' => DtoCast::squish($input['city'] ?? null),
            ],
            'billing' => BillingForm::tidy($input),
            default => $input,
        };
    }

    /**
     * What the card holds today, keyed as its form keys it: the base a partial
     * PUT is laid over, so a field the phone did not send stays as it was
     * instead of being blanked (or crashing the Action that expects it).
     * A phone is the raw stored text, never the split the screen draws.
     *
     * @return array<string, mixed>
     */
    public function current(string $key): array
    {
        return collect($this->card($key)['fields'] ?? [])
            ->mapWithKeys(fn (array $field): array => [
                $field['key'] => $field['type'] === 'phone' ? $this->business->{$field['key']} : ($field['value'] ?? null),
            ])
            ->all();
    }

    /**
     * A row with neither network nor link is the blank starter, not a network:
     * dropped BEFORE validating, as the web's card does.
     *
     * @param  array<int, mixed>  $rows
     * @return list<mixed>
     */
    private function filledSocialRows(array $rows): array
    {
        return array_values(array_filter(
            $rows,
            fn (mixed $row): bool => is_array($row) && (($row['social_network_id'] ?? null) !== null || trim((string) ($row['url'] ?? '')) !== ''),
        ));
    }

    /**
     * @return array<string, string>
     */
    private function copy(string $key): array
    {
        return [
            'title' => __("client.business.{$key}.title"),
            'sub' => __("client.business.{$key}.sub", ['brand' => Company::brand()]),
            'save' => __('client.business.actions.save'),
            'unsaved' => __('client.business.unsaved_pill'),
            'leave_title' => __('client.business.unsaved.title'),
            'leave_message' => __('client.business.unsaved.message'),
            'leave_accept' => __('client.business.unsaved.accept'),
        ];
    }

    /** @return array<string, mixed> */
    private function identity(BusinessDto $data): array
    {
        return [
            'logo_url' => $data->logo_path !== null ? Storage::disk('public')->url($data->logo_path) : null,
            'fields' => [
                $this->field('name', 'text', __('client.business.identity.name'), $data->name, hint: __('client.business.identity.name_hint'), required: true, max: 255),
                $this->field('description', 'textarea', __('client.business.identity.description'), $data->description, hint: __('client.business.identity.description_hint'), max: 500),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function location(BusinessDto $data): array
    {
        return [
            'fields' => [
                $this->field('has_premises', 'choice', __('client.business.location.question'), $data->has_premises, options: [
                    ['value' => true, 'label' => __('client.business.location.yes')],
                    ['value' => false, 'label' => __('client.business.location.no')],
                ]),
                // The web folds the address away until the answer is "yes".
                $this->field('address', 'text', __('client.business.location.address'), $data->address, placeholder: __('client.business.location.address_placeholder'), max: 255, showWhen: ['key' => 'has_premises', 'value' => true]),
                $this->field('city', 'text', __('client.business.location.city'), $data->city, placeholder: __('client.business.location.city_placeholder'), max: 255, showWhen: ['key' => 'has_premises', 'value' => true]),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function appointments(BusinessDto $data): array
    {
        $agenda = new Agenda($this->business);
        $bookable = $agenda->bookableServices->count();

        return [
            // With the agenda on, the web says which piece is still missing.
            'note' => ! $data->appointments_enabled ? null : match (true) {
                ! (new Schedule($this->business))->isComplete => __('client.business.appointments.needs_hours'),
                $bookable === 0 => __('client.business.appointments.needs_services'),
                default => trans_choice('client.business.appointments.ready', $bookable, ['count' => $bookable]),
            },
            'share' => $data->appointments_enabled ? [
                'label' => __('agenda.public.link_label'),
                'hint' => __('agenda.public.link_hint'),
                'button' => __('agenda.public.link_share'),
                'url' => $this->business->bookingLink(),
            ] : null,
            'fields' => [
                $this->field('appointments_enabled', 'switch', __('client.business.appointments.enabled'), (bool) $data->appointments_enabled, onLabel: __('client.business.appointments.on'), offLabel: __('client.business.appointments.off')),
                $this->field('appointment_capacity', 'number', __('client.business.appointments.capacity'), (int) $data->appointment_capacity, hint: __('client.business.appointments.capacity_hint'), required: true, min: 1, max: 50, showWhen: ['key' => 'appointments_enabled', 'value' => true]),
                $this->field('appointment_slot_minutes', 'number', __('client.business.appointments.slot_minutes'), (int) $data->appointment_slot_minutes, hint: __('client.business.appointments.slot_minutes_hint'), required: true, min: 5, max: 480, showWhen: ['key' => 'appointments_enabled', 'value' => true]),
                $this->field('appointments_per_day', 'number', __('client.business.appointments.per_day'), $data->appointments_per_day, hint: __('client.business.appointments.per_day_hint'), min: 1, max: 500, showWhen: ['key' => 'appointments_enabled', 'value' => true]),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function contact(BusinessDto $data): array
    {
        $dial = Country::dialCode($this->business->country_id);

        return [
            'fields' => [
                $this->field('whatsapp_number', 'phone', __('wizard.fields.whatsapp_number'), Country::splitPhone($data->whatsapp_number, $dial), hint: __('wizard.fields.whatsapp_number_hint')),
                $this->field('fallback_whatsapp_number', 'phone', __('wizard.fields.fallback_whatsapp_number'), Country::splitPhone($data->fallback_whatsapp_number, $dial), hint: __('wizard.fields.fallback_whatsapp_number_hint')),
                $this->field('email', 'email', __('client.business.contact.email'), $data->email, max: 255),
                $this->field('web', 'url', __('client.business.contact.web'), $data->web, placeholder: __('client.business.contact.web_placeholder'), max: 255),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function billing(BusinessDto $data): array
    {
        return [
            'note' => __('client.business.billing.natural_hint'),
            'fields' => [
                $this->field('currency_id', 'select', __('client.business.billing.currency'), $this->business->currency_id, options: Currency::options([true])),
                $this->field('reference_currency_id', 'select', __('client.business.billing.reference'), $this->business->reference_currency_id, hint: __('client.business.billing.reference_hint'), options: Currency::options([true])),
                $this->field('tax_condition_id', 'select', __('client.business.billing.tax_condition'), $this->business->tax_condition_id, options: TaxCondition::options([true], countryId: $this->business->country_id)),
                $this->field('tax_id', 'text', __('client.business.billing.tax_id'), $data->tax_id, placeholder: __('client.business.billing.tax_id_placeholder'), max: 20),
                $this->field('billing_email', 'email', __('client.business.billing.billing_email'), (string) $this->business->billing_email, hint: __('client.business.billing.billing_email_hint'), required: true, max: 255),
            ],
        ];
    }

    /**
     * @param  list<array{value: mixed, label: string}>|null  $options
     * @param  array{key: string, value: mixed}|null  $showWhen
     * @return array<string, mixed>
     */
    private function field(string $key, string $type, string $label, mixed $value, ?string $hint = null, ?string $placeholder = null, bool $required = false, ?int $min = null, ?int $max = null, ?array $options = null, ?array $showWhen = null, ?string $onLabel = null, ?string $offLabel = null): array
    {
        return array_filter([
            'key' => $key,
            'type' => $type,
            'label' => $label,
            'value' => $value,
            'hint' => $hint,
            'placeholder' => $placeholder,
            'required' => $required,
            'min' => $min,
            'max' => $max,
            'options' => $options,
            'show_when' => $showWhen,
            'on_label' => $onLabel,
            'off_label' => $offLabel,
        ], fn (mixed $part, string $name): bool => $name === 'value' || ($part !== null && $part !== false), ARRAY_FILTER_USE_BOTH);
    }
}
