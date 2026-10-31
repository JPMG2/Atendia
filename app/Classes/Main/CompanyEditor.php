<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Dto\DtoCast;
use App\Mail\NewCompany;
use App\Messaging\Channels\Email;
use App\Models\BusinessHour;
use App\Models\Company;
use App\Models\Country;
use App\Models\Province;
use App\Models\Region;
use App\Models\SocialLink;
use App\Models\SocialNetwork;
use App\Models\TaxCondition;
use App\Rules\AttributeValidator;
use Illuminate\Support\Arr;

/**
 * How the company's one row is validated and written, shared by the web's
 * form and the app's API so the two can never save it differently. The
 * screens keep only what is theirs: the web's logo uploads and its rows'
 * keys, the app's partial bodies.
 */
class CompanyEditor
{
    public const string STEP_MAIN = 'main';

    public const string STEP_COMMERCIAL = 'commercial';

    /** Columns of `companies` the commercial step writes; its rules also hold the networks, which are not. */
    public const array COMMERCIAL_COLUMNS = ['email', 'phone', 'web', 'payment_instructions', 'opening_schedule', 'whatsapp_greeting'];

    /**
     * The rules of ONE step. They double as the columns that step writes:
     * `validate()` returns only attributes with a rule, so a field missing
     * here would never be saved. The logos (paths and files) are the web
     * form's own: only it uploads them, and no body may name a stored path.
     *
     * @return array<string, mixed>
     */
    public static function rules(string $step): array
    {
        return match ($step) {
            self::STEP_MAIN => [
                'legal_name' => AttributeValidator::stringValid(true, '3'),
                'brand_name' => ['nullable', ...AttributeValidator::stringValid(false, '2')],
                'tagline' => ['nullable', ...AttributeValidator::stringValid(false, '3')],
                'region_id' => AttributeValidator::requireAndExists('regions', 'id', 'region_id', true),
                'address' => AttributeValidator::stringValid(true, '3'),
                'tax_condition_id' => AttributeValidator::requireAndExists('tax_conditions', 'id', 'tax_condition_id', true),
                'tax_id' => [...AttributeValidator::stringValid(true, '3'), 'max:20'],
                'text_copyright' => ['nullable', ...AttributeValidator::stringValid(false, '3')],
            ],
            self::STEP_COMMERCIAL => [
                'email' => ['nullable', 'email:rfc', 'max:255'],
                'phone' => ['nullable', ...AttributeValidator::digitValid('6', false), 'max:30'],
                'web' => ['nullable', 'url:http,https', 'max:255'],
                'payment_instructions' => ['nullable', 'string', 'max:2000', AttributeValidator::xssFree()],
                ...AttributeValidator::weekSchedule('opening_schedule'),
                'whatsapp_greeting' => ['nullable', ...AttributeValidator::stringValid(false, '3'), 'max:300'],
                'social' => ['array', 'max:20'],
                'social.*.social_network_id' => ['required', 'integer', 'distinct', 'exists:social_networks,id'],
                'social.*.url' => [...AttributeValidator::stringValid(true, '3'), 'max:255'],
            ],
            default => [],
        };
    }

    /**
     * Text as the row keeps it, whoever typed it: runs of spaces and line breaks
     * folded to one space, empties back to null. The payment instructions keep
     * their line breaks: they ARE the format (bank, holder, account). A value that
     * is not text is left for the rules to refuse.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function clean(array $payload): array
    {
        foreach (['legal_name', 'brand_name', 'tagline', 'address', 'tax_id', 'text_copyright', 'email', 'phone', 'web', 'whatsapp_greeting'] as $key) {
            if (array_key_exists($key, $payload) && ($payload[$key] === null || is_string($payload[$key]))) {
                $payload[$key] = DtoCast::squish($payload[$key]);
            }
        }

        if (array_key_exists('payment_instructions', $payload) && ($payload['payment_instructions'] === null || is_string($payload['payment_instructions']))) {
            $payload['payment_instructions'] = DtoCast::toNullableString(trim((string) $payload['payment_instructions']));
        }

        return $payload;
    }

    /** @return array<string, string> */
    public static function names(): array
    {
        return [
            'legal_name' => config('nicename.legal_name'),
            'brand_name' => config('nicename.brand_name'),
            'tagline' => config('nicename.tagline'),
            'region_id' => config('nicename.region_id'),
            'address' => config('nicename.address'),
            'tax_condition_id' => config('nicename.tax_condition_id'),
            'tax_id' => config('nicename.tax_id'),
            'logo_path_light' => config('nicename.logo_path_light'),
            'logo_path_dark' => config('nicename.logo_path_dark'),
            'logo_light_file' => config('nicename.logo_path_light'),
            'logo_dark_file' => config('nicename.logo_path_dark'),
            'text_copyright' => config('nicename.text_copyright'),
            'email' => config('nicename.email'),
            'phone' => config('nicename.phone'),
            'web' => config('nicename.web'),
            'payment_instructions' => __('company.fields.payment_instructions'),
            'opening_schedule' => __('company.fields.opening_schedule'),
            'opening_schedule.*.0' => __('company.hours.opens'),
            'opening_schedule.*.1' => __('company.hours.closes'),
            'whatsapp_greeting' => __('company.fields.whatsapp_greeting'),
            'social.*.social_network_id' => config('nicename.social_network_id'),
            'social.*.url' => config('nicename.url'),
        ];
    }

    /**
     * Writes the main step. The table holds ONE row: an existing one is
     * updated, so a second save can never create a second company.
     *
     * @param  array<string, mixed>  $columns  Validated, with any logo already turned into its path.
     */
    public function saveMain(array $columns): Company
    {
        $company = Company::forEditing() ?? new Company;
        $company->fill($columns)->save();

        return $company;
    }

    /**
     * Writes the commercial step and its networks; says whether the networks
     * changed, which tells "saved" from "nothing changed". The welcome mail
     * goes ONCE in the company's life, stamped in `welcomed_at` with the same
     * save: a new address later never sends it again.
     *
     * @param  array<string, mixed>  $validated
     */
    public function saveCommercial(Company $company, array $validated): bool
    {
        $company->fill(Arr::only($validated, self::COMMERCIAL_COLUMNS));
        $welcome = $company->welcomed_at === null && filled($company->email);

        if ($welcome) {
            $company->forceFill(['welcomed_at' => now()]);
        }

        $company->save();

        $linksChanged = array_key_exists('social', $validated) && $this->syncSocialLinks($company, $validated['social']);

        if ($welcome) {
            (new Email($company, [$company->email], NewCompany::class))->send();
        }

        return $linksChanged;
    }

    /**
     * The options each list of the form offers, chained the way the web's are:
     * provinces and tax conditions follow the country, regions the province.
     *
     * @return array{countries: list<array{value: int, label: string}>, provinces: list<array{value: int, label: string}>, regions: list<array{value: int, label: string}>, taxConditions: list<array{value: int, label: string}>}
     */
    public static function places(?int $countryId, ?int $provinceId): array
    {
        return [
            'countries' => array_values(Country::options(states: [true], label: fn (Country $country): string => $country->name)),
            'provinces' => $countryId === null ? [] : array_values(Province::options(states: [true], label: fn (Province $province): string => $province->name, countryId: $countryId)),
            'regions' => $provinceId === null ? [] : array_values(Region::options(states: [true], provinceId: $provinceId)),
            'taxConditions' => $countryId === null ? [] : array_values(TaxCondition::options(states: [true], countryId: $countryId)),
        ];
    }

    /**
     * One step as the phone's form draws it: the web's words, today's values
     * and, for the main step, the chained lists; for the commercial one, the
     * week and the networks. Logos are not here: only the web uploads them.
     *
     * @return array<string, mixed>
     */
    public function sheet(string $step): array
    {
        $company = Company::forScreen();
        $copy = [
            'title' => __("company.steps.{$step}.label"),
            'sub' => __("company.steps.{$step}.desc"),
            'save' => __('company.save'),
            'unsaved' => __('client.business.unsaved_pill'),
            'leave_title' => __('company.discard_confirm.title'),
            'leave_message' => __('company.discard_confirm.message'),
            'leave_accept' => __('company.discard_confirm.accept'),
            'reload' => __('company.reload'),
        ];

        // The footer as a visitor reads it, under the form as on the web: only once there is a row to draw it from.
        $preview = $company === null ? null : (new CompanyFooterPreview)->toArray();

        $head = ['key' => $step, 'copy' => $copy, 'version' => self::versionOf($company), 'preview' => $preview];

        return $step === self::STEP_MAIN
            ? [...$head, ...$this->mainSheet($company)]
            : [...$head, 'exists' => $company !== null, ...$this->commercialSheet($company)];
    }

    /**
     * The row's version as an editor opened it. A save carrying an older one
     * writes nothing: two editors (two tabs, the web and the phone) never
     * silently overwrite each other. Null while there is no company yet.
     */
    public static function versionOf(?Company $company): ?string
    {
        return $company?->updated_at?->format('Y-m-d H:i:s');
    }

    /** Whether the row moved since the editor read `$version`. */
    public static function changedSince(?string $version): bool
    {
        return self::versionOf(Company::forEditing()) !== $version;
    }

    /**
     * What a body may change in a step, laid over what the step holds today:
     * a field the phone did not send stays as it is.
     *
     * @return array<string, mixed>
     */
    public static function current(string $step): array
    {
        $company = Company::forScreen();

        return $step === self::STEP_MAIN
            ? collect(array_keys(self::rules(self::STEP_MAIN)))->mapWithKeys(fn (string $key): array => [$key => $company?->{$key}])->all()
            : [
                ...collect(self::COMMERCIAL_COLUMNS)->mapWithKeys(fn (string $key): array => [$key => $company?->{$key}])->all(),
                'opening_schedule' => OpeningHours::toStored(OpeningHours::formState(is_array($company?->opening_schedule) ? $company->opening_schedule : null)) ?: null,
            ];
    }

    /** @return array<string, mixed> */
    private function mainSheet(?Company $company): array
    {
        $countryId = $company?->region?->province?->country_id;
        $provinceId = $company?->region?->province_id;
        $places = self::places($countryId, $provinceId);

        return ['fields' => [
            $this->field('legal_name', 'text', __('company.fields.legal_name'), $company?->legal_name, placeholder: __('company.fields.legal_name_placeholder'), required: true),
            $this->field('brand_name', 'text', __('company.fields.brand_name'), $company?->brand_name, hint: __('company.fields.brand_name_hint')),
            $this->field('tagline', 'text', __('company.fields.tagline'), $company?->tagline, hint: __('company.fields.tagline_hint'), placeholder: __('company.fields.tagline_placeholder')),
            $this->field('country_id', 'select', __('company.fields.country'), $countryId, placeholder: __('company.fields.country_placeholder'), options: $places['countries']),
            $this->field('province_id', 'select', __('company.fields.province'), $provinceId, placeholder: __('company.fields.province_placeholder'), options: $places['provinces']),
            $this->field('region_id', 'select', __('company.fields.region'), $company?->region_id, placeholder: __('company.fields.region_placeholder'), required: true, options: $places['regions']),
            $this->field('address', 'text', __('company.fields.address'), $company?->address, placeholder: __('company.fields.address_placeholder'), required: true),
            $this->field('tax_condition_id', 'select', __('company.fields.tax_condition'), $company?->tax_condition_id, placeholder: __('company.fields.tax_condition_placeholder'), required: true, options: $places['taxConditions']),
            $this->field('tax_id', 'text', __('company.fields.tax_id'), $company?->tax_id, hint: __('company.fields.tax_id_hint'), required: true, max: 20, mono: true),
            $this->field('text_copyright', 'text', __('company.fields.copyright'), $company?->text_copyright, placeholder: __('company.fields.copyright_placeholder')),
        ]];
    }

    /** @return array<string, mixed> */
    private function commercialSheet(?Company $company): array
    {
        $names = BusinessHour::dayNames();
        $week = OpeningHours::formState(is_array($company?->opening_schedule) ? $company->opening_schedule : null);

        return [
            'fields' => [
                $this->field('email', 'email', __('company.fields.email'), $company?->email, placeholder: __('company.fields.email_placeholder')),
                $this->field('phone', 'text', __('company.fields.phone'), $company?->phone, hint: __('company.fields.phone_hint'), placeholder: __('company.fields.phone_placeholder'), max: 30, mono: true),
                $this->field('web', 'url', __('company.fields.web'), $company?->web, placeholder: __('company.fields.web_placeholder')),
                $this->field('whatsapp_greeting', 'text', __('company.fields.whatsapp_greeting'), $company?->whatsapp_greeting, hint: __('company.fields.whatsapp_greeting_hint'), placeholder: __('company.fields.whatsapp_greeting_placeholder'), max: 300),
                $this->field('payment_instructions', 'textarea', __('company.fields.payment_instructions'), $company?->payment_instructions, hint: __('company.fields.payment_instructions_hint'), max: 2000),
            ],
            'week' => [
                'label' => __('company.fields.opening_schedule'),
                'hint' => __('company.fields.opening_schedule_hint'),
                'words' => [
                    'closed' => __('company.hours.closed'),
                    'opens' => __('company.hours.opens'),
                    'closes' => __('company.hours.closes'),
                    'apply_weekdays' => __('company.hours.apply_weekdays'),
                    'apply_saturday' => __('company.hours.apply_saturday'),
                    'placeholder' => __('company.hours.time_placeholder'),
                    'overnight' => __('company.hours.overnight_hint'),
                ],
                'days' => collect($week)->map(fn (array $item, int $day): array => ['day' => $day, 'label' => $names[$day], ...$item])->values()->all(),
            ],
            'social' => [
                'words' => [
                    'title' => __('company.social.title'),
                    'desc' => __('company.social.desc'),
                    'network' => __('company.social.network'),
                    'network_placeholder' => __('company.social.network_placeholder'),
                    'url' => __('company.social.url'),
                    'url_placeholder' => __('company.social.url_placeholder'),
                    'add' => __('company.social.add'),
                    'remove' => __('company.social.remove'),
                    'remove_confirm' => __('company.social.remove_confirm'),
                ],
                'networks' => array_values(SocialNetwork::options(states: [true])),
                // The web's box stops typing here, as its rule refuses longer.
                'url_max' => 255,
                'rows' => ($company?->socialLinks ?? collect())->map(fn (SocialLink $link): array => ['id' => $link->id, 'social_network_id' => $link->social_network_id, 'url' => $link->url])->values()->all(),
            ],
        ];
    }

    /**
     * One field in the shape the phone's card form already draws.
     *
     * @param  list<array{value: int, label: string}>|null  $options
     * @return array<string, mixed>
     */
    private function field(string $key, string $type, string $label, mixed $value, ?string $hint = null, ?string $placeholder = null, bool $required = false, ?int $max = null, ?array $options = null, bool $mono = false): array
    {
        return array_filter([
            'key' => $key,
            'type' => $type,
            'label' => $label,
            'value' => $value,
            'hint' => $hint,
            'placeholder' => $placeholder,
            'required' => $required,
            'max' => $max,
            'options' => $options,
            'mono' => $mono,
        ], fn (mixed $part, string $name): bool => $name === 'value' || ($part !== null && $part !== false), ARRAY_FILTER_USE_BOTH);
    }

    /** Deletes a network of THIS company: an id from outside reaches its own owner only. */
    public function removeSocialLink(int $id): ?SocialLink
    {
        $company = Company::forEditing();
        $link = $company?->socialLinks()->find($id);

        if (! $link instanceof SocialLink) {
            return null;
        }

        $link->delete();
        Company::query()->whereKey($company->id)->update(['updated_at' => now()]);

        return $link;
    }

    /**
     * Leaves the stored networks exactly as the screen left them, keyed by the
     * network so reordering or fixing a link edits the row instead of
     * duplicating it; whatever left the screen leaves the table.
     *
     * @param  array<int, array{social_network_id: int, url: string}>  $rows
     */
    private function syncSocialLinks(Company $company, array $rows): bool
    {
        $changed = false;
        $kept = [];

        foreach (array_values($rows) as $index => $row) {
            $link = $company->socialLinks()->updateOrCreate(
                ['social_network_id' => $row['social_network_id']],
                ['url' => $row['url'], 'sort_order' => $index],
            );

            $changed = $changed || $link->wasRecentlyCreated || $link->wasChanged();
            $kept[] = $link->id;
        }

        $removed = $company->socialLinks()->whereNotIn('id', $kept)->delete();

        // The networks live in another table: the row's version moves with them,
        // or an editor holding the old list would never be told.
        if ($changed || $removed > 0) {
            Company::query()->whereKey($company->id)->update(['updated_at' => now()]);

            return true;
        }

        return false;
    }
}
