<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Models\Company;
use App\Models\SocialLink;

/**
 * The company's own data as a read-only sheet, in the words the admin's company form labels it with
 * (`company.*`): the mobile API reads from here. The web screen is a form over the same single record,
 * so it cannot read from this piece; a test ties the two together instead — every field the form
 * edits has to be on this sheet, under the label the form gives it.
 */
class CompanyProfile
{
    /** The text of a field nobody filled in: a dash, never a blank that reads as broken. */
    public const string MISSING = '—';

    public function __construct(private readonly ?Company $company = null) {}

    /** @var Company the single record; an empty one until the company was ever loaded, as the web's blank form is */
    public Company $record {
        get => $this->company ?? Company::current() ?? new Company;
    }

    /** @var array{title: string, sub: string} */
    public array $head {
        get => ['title' => __('company.title'), 'sub' => __('company.subtitle', ['brand' => Company::brand()])];
    }

    /**
     * @var list<array{key: string, title: string, desc: string, fields: list<array{key: string, label: string, value: string, mono: bool}>}>
     */
    public array $sections {
        get {
            $company = $this->record;
            $region = $company->region;
            $province = $region?->province;
            $schedule = $company->opening_schedule;

            return [
                $this->section('identity', [
                    $this->field('legal_name', $company->legal_name),
                    $this->field('brand_name', $company->brand_name),
                    $this->field('tagline', $company->tagline),
                ]),
                $this->section('address', [
                    $this->field('country', $province?->country?->name),
                    $this->field('province', $province?->name),
                    $this->field('region', $region?->name),
                    $this->field('address', $company->address),
                ]),
                $this->section('tax', [
                    $this->field('tax_condition', $company->taxCondition?->name),
                    $this->field('tax_id', $company->tax_id, mono: true),
                ]),
                $this->section('footer', [
                    $this->field('copyright', $company->text_copyright),
                ]),
                $this->section('contact', [
                    $this->field('email', $company->email),
                    $this->field('phone', $company->phone, mono: true),
                    $this->field('web', $company->web),
                    // A week nobody declared is not a closed one: the dash says "not set", the word says closed.
                    $this->field('opening_schedule', $schedule === null ? null : (new OpeningHours(OpeningHours::normalize($schedule), Company::localTimezone()))->summary),
                    $this->field('whatsapp_greeting', $company->whatsapp_greeting),
                    $this->field('payment_instructions', $company->payment_instructions),
                ]),
            ];
        }
    }

    /** @var list<array{network: string, url: string}> the links in the order the web keeps them */
    public array $social {
        get => $this->record->socialLinks
            ->map(fn (SocialLink $link): array => ['network' => (string) $link->socialNetwork?->name, 'url' => $link->url])
            ->values()
            ->all();
    }

    /** @var array{title: string, desc: string} */
    public array $socialHead {
        get => ['title' => __('company.social.title'), 'desc' => __('company.social.desc')];
    }

    /** @var array{title: string, desc: string, light: array{label: string, url: string|null}, dark: array{label: string, url: string|null}} */
    public array $logos {
        get => [
            'title' => __('company.logo.title'),
            'desc' => __('company.logo.desc'),
            'light' => ['label' => __('company.logo.light'), 'url' => $this->logoUrl($this->record->logo_path_light)],
            'dark' => ['label' => __('company.logo.dark'), 'url' => $this->logoUrl($this->record->logo_path_dark)],
        ];
    }

    /**
     * @param  list<array{key: string, label: string, value: string, mono: bool}>  $fields
     * @return array{key: string, title: string, desc: string, fields: list<array{key: string, label: string, value: string, mono: bool}>}
     */
    private function section(string $key, array $fields): array
    {
        return ['key' => $key, 'title' => __('company.'.$key.'.title'), 'desc' => __('company.'.$key.'.desc'), 'fields' => $fields];
    }

    /** @return array{key: string, label: string, value: string, mono: bool} */
    private function field(string $key, ?string $value, bool $mono = false): array
    {
        return ['key' => $key, 'label' => __('company.fields.'.$key), 'value' => filled($value) ? $value : self::MISSING, 'mono' => $mono];
    }

    /** `asset()`, not the disk's URL: behind the proxy the disk would come out http on an https page. */
    private function logoUrl(?string $path): ?string
    {
        return filled($path) ? asset('storage/'.$path) : null;
    }
}
