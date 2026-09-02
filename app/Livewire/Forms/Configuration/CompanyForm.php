<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Configuration;

use App\Dto\CompanyDto;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use App\Mail\NewCompany;
use App\Messaging\Channels\Email;
use App\Models\Company;
use App\Models\SocialLink;
use App\Rules\AttributeValidator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Locked;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * The company form: ONE row, forever.
 *
 * It does not extend `BaseCatalogForm`: no table to list, no id to pick from
 * a rail, no wiring. It has no Action either — in the catalog an Action is the
 * socket that makes the base generic, and here a single caller writes a single
 * row.
 */
class CompanyForm extends BaseForm
{
    /**
     * The steps of the screen.
     *
     * Each validates and saves ONLY its own columns, under the same keys the
     * front's rule bag uses. Naming them alike on both sides is what lets you
     * read what a step demands without translating.
     */
    public const STEP_MAIN = 'main';

    public const STEP_COMMERCIAL = 'commercial';

    /**
     * Fields of the main step that are NOT columns of `companies`.
     *
     * Country and province are derived from the region and live in the DTO as
     * screen state. Discarding has to put them back too, or the cascade keeps
     * showing the address that was just discarded.
     *
     * @var list<string>
     */
    private const MAIN_SCREEN_STATE = ['country_id', 'province_id'];

    /**
     * The logo columns and the upload field that feeds each one.
     *
     * A picked file becomes a path; with none picked the stored path stays. The
     * uploads are NOT columns, which is why they leave `$validated` before it
     * reaches `fill()`.
     *
     * @var array<string, string>
     */
    /**
     * What a logo may be. SVG is in because a brand mark is vector: it scales
     * into the footer and the invoice without blurring.
     *
     * @var list<string>
     */
    private const LOGO_RULES = ['nullable', 'file', 'mimes:png,webp,jpg,jpeg,svg', 'max:2048'];

    private const LOGO_UPLOADS = [
        'logo_path_light' => 'logo_light_file',
        'logo_path_dark' => 'logo_dark_file',
    ];

    /**
     * Columns of `companies` the commercial step writes.
     *
     * The separate list is needed here: the step's rules include the networks',
     * which are not columns of this table. In step one the rule keys already are
     * exactly its columns.
     *
     * @var list<string>
     */
    private const COMMERCIAL_COLUMNS = ['email', 'phone', 'web'];

    /**
     * The loaded record; `null` means nothing has been loaded yet.
     *
     * `#[Locked]` because the front never picks it: `setup()` assigns it from the
     * table's only row, or `saveMain()` right after creating it.
     */
    #[Locked]
    public ?int $recordId = null;

    /**
     * The DTO holding the form state.
     *
     * It has to be initialised before the first render or a nested `wire:model`
     * dies with "Cannot assign array to property": Livewire cannot recurse into
     * a null.
     */
    public ?CompanyDto $data = null;

    /**
     * The logos while they are still uploads, one per theme.
     *
     * Untyped on purpose: between the pick and the save Livewire holds a
     * `TemporaryUploadedFile` here, and typing the property would reject the
     * plain `UploadedFile` a test hands over. They stay out of the DTO, which
     * only ever holds the stored path.
     *
     * @var TemporaryUploadedFile|null
     */
    public $logo_light_file = null;

    /** @var TemporaryUploadedFile|null */
    public $logo_dark_file = null;

    /**
     * The company's networks, as editable rows.
     *
     * `key` identifies the ROW and goes in the `wire:key`, so removing one from
     * the middle moves the right node. `id` is the `social_links` record once the
     * row is saved, and tells dropping a blank row from deleting a real link.
     * They are not in the DTO because they are not columns of `companies`.
     *
     * @var array<int, array{key: int, id: int|null, social_network_id: int|null, url: string|null}>
     */
    public array $social = [];

    /** Next row key. Never picked by the front. */
    #[Locked]
    public int $nextSocialKey = 1;

    /**
     * Loads the issuer into the form, or leaves it blank when there is none.
     *
     * `setup()` is NOT a Livewire Form hook: the component's `mount()` calls it,
     * same as in the catalog editors.
     */
    public function setup(): void
    {
        // The table holds one row: the first one IS the company. The country is
        // not fetched — its id already lives on the province. The networks are,
        // since asking for them later is the same query made late.
        $company = Company::with(['region.province', 'socialLinks'])->first();

        if ($company === null) {
            $this->data = new CompanyDto;
            $this->social = [$this->blankSocialRow()];

            return;
        }

        $this->recordId = $company->id;
        $this->data = CompanyDto::fromArray($this->stateFrom($company));
        $this->social = $this->socialStateFrom($company);
    }

    /**
     * Adds a blank network row below the given one.
     *
     * Adding and removing cost a request: the rows are SERVER state, the only way
     * what was typed survives a save. Whatever was being written travels with
     * this same request, so nothing is lost.
     */
    public function addSocialRow(int $after): void
    {
        array_splice($this->social, $after + 1, 0, [$this->blankSocialRow()]);
    }

    /**
     * Removes a row and, when that network was already saved, DELETES it there
     * and then. It does not wait for the save button, which is why the screen
     * confirms first and why `SocialLink` leaves a trail — there is no bin to
     * pull it back from. One row always remains: deleting the last one leaves it
     * blank, or there would be nowhere to start again.
     */
    public function removeSocialRow(int $index): ?NotificationDto
    {
        $row = $this->social[$index] ?? null;

        if ($row === null) {
            return null;
        }

        $notification = $row['id'] !== null ? $this->deleteSocialLink($row['id']) : null;

        if (count($this->social) === 1) {
            $this->social = [$this->blankSocialRow()];

            return $notification;
        }

        unset($this->social[$index]);

        $this->social = array_values($this->social);

        return $notification;
    }

    /**
     * Deletes a link belonging to THIS company.
     *
     * Looked up through the relation and not by `SocialLink::find()`: `$social`
     * is public form state, so an id arriving from the front must only ever be
     * able to reach its own owner.
     */
    private function deleteSocialLink(int $id): NotificationDto
    {
        $link = Company::query()->find($this->recordId)?->socialLinks()->find($id);

        if (! $link instanceof SocialLink) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        // spatie logs the delete on its own (LogsActivity on the model).
        $link->delete();

        return $this->notificationService()->notificationFor($link, 'deleted');
    }

    /**
     * Saves the main step: identity, address, tax data, logo and footer.
     *
     * An upsert, not a create: an existing row is updated. It writes the step's
     * columns and NOTHING else — `validate()` returns only attributes that have a
     * rule, so step two's contact and networks are never overwritten.
     */
    public function saveMain(): NotificationDto
    {
        $validated = $this->validateStep(self::STEP_MAIN);

        $creating = $this->recordId === null;

        return $this->tryAction(function () use ($validated): NotificationDto {

            // The table holds ONE row: an existing one is updated even if this form
            // mounted before it was there (another tab, a double click). Without the
            // `first()` fallback a second save would create a second company.
            $company = Company::query()->find($this->recordId)
                ?? Company::query()->first()
                ?? new Company;

            $company->fill($this->columnsWithLogos($validated, $company))->save();

            $this->recordId = $company->id;

            $this->settleLogos($company);

            return $this->notificationService()->notificationFor(
                $company,
                $company->wasRecentlyCreated ? 'created' : 'updated',
            );

        }, $creating ? __('notifications.not_created') : __('notifications.not_updated'));
    }

    /**
     * Saves the commercial step: the public contact and the networks.
     *
     * It never creates the company — step one does, and step two does not even
     * open until it exists. With no record it warns instead of inventing a
     * half-made one.
     */
    public function saveCommercial(): NotificationDto
    {
        if ($this->recordId === null) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        $validated = $this->validateStep(self::STEP_COMMERCIAL);

        // The record leaves the closure by reference because the mail goes out
        // OUTSIDE it: `tryAction()` can only return the screen notification.
        $company = null;

        // If it already had an address, this save is not the one giving it one.
        $hadEmail = false;

        $notification = $this->tryAction(function () use ($validated, &$company, &$hadEmail): NotificationDto {

            $company = Company::query()->findOrFail($this->recordId);

            $hadEmail = filled($company->email);

            $company->fill(Arr::only($validated, self::COMMERCIAL_COLUMNS))->save();

            $linksChanged = $this->syncSocialLinks($company, $validated['social'] ?? []);

            // If ONLY the networks changed, the `companies` row is untouched and
            // `notificationFor()` would say "nothing changed" with the networks
            // freshly saved in plain sight.
            if ($linksChanged && ! $company->wasChanged()) {
                return new NotificationDto(
                    __('notifications.updated.female', ['entity' => __('notifications.entities.company')]),
                    NotificationType::Success,
                );
            }

            return $this->notificationService()->notificationFor($company, 'updated');

        }, __('notifications.not_updated'));

        // AFTER the row is written and OUTSIDE `tryAction()`: inside, a failed
        // send would turn a save that worked into an error toast, and a `save()`
        // that throws leaves nobody to write to.

        // Sent ONCE, when the company gets its first address. `wasChanged()` only
        // tells the truth after a successful save, and `$hadEmail` separates
        // getting an address from correcting one.
        if ($company !== null && ! $hadEmail && $company->wasChanged('email') && filled($company->email)) {
            (new Email($company, [$company->email], NewCompany::class))->send();
        }

        return $notification;
    }

    /**
     * Puts the commercial step back to the last saved state, networks included.
     */
    public function discardCommercial(): void
    {
        if ($this->data === null) {
            return;
        }

        $company = Company::with('socialLinks')->find($this->recordId);

        $saved = $company !== null ? CompanyDto::fromArray($company->toArray()) : new CompanyDto;

        foreach (self::COMMERCIAL_COLUMNS as $field) {
            $this->data->{$field} = $saved->{$field};
        }

        $this->social = $company !== null
            ? $this->socialStateFrom($company)
            : [$this->blankSocialRow()];
    }

    /**
     * Puts the main step back to the last saved state.
     *
     * With no company loaded that state is the blank form. It touches ONLY this
     * step's fields: what was typed into step two and not yet saved has no reason
     * to be lost. The assignments come from the server, so they do not fire
     * `updatedData*` and the restored region does not wipe itself.
     */
    public function discardMain(): void
    {
        if ($this->data === null) {
            return;
        }

        $company = Company::with('region.province')->find($this->recordId);

        $saved = $company !== null
            ? CompanyDto::fromArray($this->stateFrom($company))
            : new CompanyDto;

        // The step's rule keys ARE its columns, bar the uploads: one list for
        // what is validated, what is saved and what is discarded.
        $columns = array_diff(array_keys($this->rulesFor(self::STEP_MAIN)), array_values(self::LOGO_UPLOADS));

        foreach ([...$columns, ...self::MAIN_SCREEN_STATE] as $field) {
            $this->data->{$field} = $saved->{$field};
        }

        // A file picked and not yet saved goes with the discard, same as text.
        foreach (self::LOGO_UPLOADS as $upload) {
            $this->{$upload} = null;
        }
    }

    /**
     * The step's columns, with a freshly picked logo already on disk.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function columnsWithLogos(array $validated, Company $company): array
    {
        $columns = Arr::except($validated, array_values(self::LOGO_UPLOADS));

        foreach (self::LOGO_UPLOADS as $column => $upload) {
            $file = $validated[$upload] ?? null;

            if (! $file instanceof UploadedFile) {
                continue;
            }

            $columns[$column] = $this->storeLogo($file, $company->{$column});
        }

        return $columns;
    }

    /**
     * Stores a logo and drops the one it replaces.
     *
     * The old file goes as soon as the new path is written: nothing points at it
     * any more, and an orphan on a public disk is nobody's to find later.
     */
    private function storeLogo(UploadedFile $file, ?string $previous): string
    {
        $path = (string) $file->store('logos', 'public');

        if ($previous !== null && $previous !== $path) {
            Storage::disk('public')->delete($previous);
        }

        return $path;
    }

    /**
     * Leaves the form standing on the STORED logo, with no pick pending.
     *
     * The DTO takes the paths just written, so the zone shows the file on disk
     * and a second save cannot store the same upload all over again.
     */
    private function settleLogos(Company $company): void
    {
        if ($this->data === null) {
            return;
        }

        foreach (self::LOGO_UPLOADS as $column => $upload) {
            $this->data->{$column} = $company->{$column};
            $this->{$upload} = null;
        }
    }

    /**
     * Removes a logo and leaves its column empty, deleting the stored file.
     *
     * It runs THERE AND THEN, like removing a saved network, which is why the
     * screen confirms first. A pick not yet saved is simply dropped, with
     * nothing on disk to delete and nothing to say.
     */
    public function removeLogo(string $column): ?NotificationDto
    {
        if (! array_key_exists($column, self::LOGO_UPLOADS) || $this->data === null) {
            return null;
        }

        $this->{self::LOGO_UPLOADS[$column]} = null;
        $this->data->{$column} = null;

        $company = Company::query()->find($this->recordId);

        if ($company === null || $company->{$column} === null) {
            return null;
        }

        return $this->tryAction(function () use ($company, $column): NotificationDto {
            $path = $company->{$column};

            $company->fill([$column => null])->save();

            // Only once nothing points at it: deleting first would leave a
            // saved path with no file behind it if the save failed.
            Storage::disk('public')->delete($path);

            return $this->notificationService()->notificationFor($company, 'updated');
        }, __('notifications.not_updated'));
    }

    /**
     * Changing the country invalidates everything hanging off it.
     *
     * Otherwise the old province survives: it vanishes from the list, already
     * narrowed to the new country, but stays in the DTO — and what would be saved
     * is a region of another country. Only front-driven changes fire the hook, so
     * loading an existing company does not wipe itself.
     */
    public function updatedDataCountryId(): void
    {
        if ($this->data === null) {
            return;
        }

        $this->data->province_id = null;
        $this->data->region_id = null;
    }

    /**
     * Changing the province invalidates the region, and only the region: the
     * country stays valid because the new province hangs off it.
     */
    public function updatedDataProvinceId(): void
    {
        if ($this->data === null) {
            return;
        }

        $this->data->region_id = null;
    }

    /**
     * `BaseForm`'s contract: the rules of the WHOLE form.
     *
     * Saving does not use it — each step validates with its own
     * ({@see self::validateStep()}). Judging the open step by the other one's
     * rules would paint the error in a hidden panel, which reads as a button
     * that does nothing.
     *
     * @return array<string, mixed>
     */
    protected function getValidationRules(?int $excludeId = null): array
    {
        return [
            ...$this->rulesFor(self::STEP_MAIN),
            ...$this->rulesFor(self::STEP_COMMERCIAL),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function getValidationAttributes(): array
    {
        return [
            'legal_name' => config('nicename.legal_name'),
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
            // Laravel resolves `social.0.url` against the wildcard key.
            'social.*.social_network_id' => config('nicename.social_network_id'),
            'social.*.url' => config('nicename.url'),
        ];
    }

    protected function transformServiceData(): array
    {
        return [
            ...$this->data?->toPayload() ?? [],
            'logo_light_file' => $this->logo_light_file,
            'logo_dark_file' => $this->logo_dark_file,
            'social' => $this->filledSocialRows(),
        ];
    }

    /**
     * The network rows that were actually filled in.
     *
     * The screen always shows a blank row to start from; that row is not a
     * mistake, it simply is not a network. A half-filled one DOES go through —
     * that deserves a warning, not a silent drop.
     *
     * @return array<int, array{key: int, id: int|null, social_network_id: int|null, url: string|null}>
     */
    private function filledSocialRows(): array
    {
        return array_values(array_filter(
            $this->social,
            fn (array $row): bool => $row['social_network_id'] !== null || trim((string) $row['url']) !== '',
        ));
    }

    /**
     * Leaves the stored networks exactly as the screen left them.
     *
     * Returns whether anything changed, which is what picks the toast's message.
     *
     * @param  array<int, array{social_network_id: int, url: string}>  $rows
     */
    private function syncSocialLinks(Company $company, array $rows): bool
    {
        $changed = false;
        $kept = [];

        foreach (array_values($rows) as $index => $row) {
            // The network is the key — it is what the table holds unique per owner —
            // so reordering or fixing a link edits the row instead of duplicating it.
            $link = $company->socialLinks()->updateOrCreate(
                ['social_network_id' => $row['social_network_id']],
                ['url' => $row['url'], 'sort_order' => $index],
            );

            $changed = $changed || $link->wasRecentlyCreated || $link->wasChanged();
            $kept[] = $link->id;
        }

        // Whatever left the screen leaves the table: a removed row cannot sit in
        // the database waiting for the next render.
        $removed = $company->socialLinks()->whereNotIn('id', $kept)->delete();

        return $changed || $removed > 0;
    }

    /**
     * The stored networks as screen rows, in their order.
     *
     * @return array<int, array{key: int, id: int|null, social_network_id: int|null, url: string|null}>
     */
    private function socialStateFrom(Company $company): array
    {
        $rows = $company->socialLinks
            ->map(fn (SocialLink $link): array => [
                'key' => $this->nextSocialKey++,
                'id' => $link->id,
                'social_network_id' => $link->social_network_id,
                'url' => $link->url,
            ])
            ->all();

        // With none stored the blank row stays: the section is never shown empty,
        // or there would be nowhere to write the first one.
        return $rows === [] ? [$this->blankSocialRow()] : $rows;
    }

    /**
     * @return array{key: int, id: null, social_network_id: null, url: null}
     */
    private function blankSocialRow(): array
    {
        return ['key' => $this->nextSocialKey++, 'id' => null, 'social_network_id' => null, 'url' => null];
    }

    /**
     * The rules of ONE step.
     *
     * It doubles as the list of columns that step writes: `validate()` returns
     * only attributes that have a rule, so a field missing from here would never
     * be saved — lost in silence, which is worse than an error.
     *
     * @return array<string, mixed>
     */
    private function rulesFor(string $step): array
    {
        return match ($step) {

            self::STEP_MAIN => [

                // The name the invoice is issued under. No `unique`: the table
                // holds one row, there is nobody to clash with.
                'legal_name' => AttributeValidator::stringValid(true, '3'),

                // Optional, as the column is nullable: without it a company with
                // no tagline would bounce off the `min` in stringValid().
                'tagline' => ['nullable', ...AttributeValidator::stringValid(false, '3')],

                // The table stores ONLY the region; the country and province on
                // screen are derived from it and are not columns.
                'region_id' => AttributeValidator::requireAndExists('regions', 'id', 'region_id', true),

                'address' => AttributeValidator::stringValid(true, '3'),

                'tax_condition_id' => AttributeValidator::requireAndExists('tax_conditions', 'id', 'tax_condition_id', true),

                // The column is varchar(20) and the input caps at 20: without
                // this the max:255 in stringValid let through a number that
                // then blew up in Postgres.
                'tax_id' => [...AttributeValidator::stringValid(true, '3'), 'max:20'],

                // The columns hold the PATH, which the form never types: it is
                // written from the file below. They keep their rule because what
                // has none is never saved.
                'logo_path_light' => ['nullable', ...AttributeValidator::stringValid(false, '1')],
                'logo_path_dark' => ['nullable', ...AttributeValidator::stringValid(false, '1')],

                // Optional: saving the rest of the step cannot demand a logo.
                'logo_light_file' => self::LOGO_RULES,
                'logo_dark_file' => self::LOGO_RULES,

                'text_copyright' => ['nullable', ...AttributeValidator::stringValid(false, '3')],
            ],

            self::STEP_COMMERCIAL => [

                // All three are optional, as the columns are nullable: a company
                // may publish no phone at all.
                'email' => ['nullable', 'email:rfc', 'max:255'],

                // The column is varchar(30); digitValid lets through the
                // separators people actually type.
                'phone' => ['nullable', ...AttributeValidator::digitValid('6', false), 'max:30'],

                // webValid() is NOT used: it adds `active_url`, which resolves DNS
                // on every save — a company's site cannot have to be online for
                // its details to be saved.
                'web' => ['nullable', 'url:http,https', 'max:255'],

                'social' => ['array', 'max:20'],

                // `distinct` is the on-screen half of the table's unique per owner:
                // without it two rows on the same network would be a database
                // crash caught by tryAction, not a field error.
                'social.*.social_network_id' => ['required', 'integer', 'distinct', 'exists:social_networks,id'],

                // The field is called "link or handle" and that is what it takes:
                // demanding a full URL would bounce the handle the screen itself
                // invites people to type.
                'social.*.url' => [...AttributeValidator::stringValid(true, '3'), 'max:255'],
            ],

            default => [],
        };
    }

    /**
     * Validates the payload against ONE step's rules and returns only those keys.
     *
     * @return array<string, mixed>
     */
    private function validateStep(string $step): array
    {
        return Validator::make(
            $this->transformServiceData(),
            $this->rulesFor($step),
            [],
            $this->getValidationAttributes(),
        )->validate();
    }

    /**
     * The record plus the country and province, which are not its columns.
     *
     * The table stores only the region; the address is shown from broad to
     * narrow, so the two comboboxes above it are derived by walking up the chain.
     *
     * @return array<string, mixed>
     */
    private function stateFrom(Company $company): array
    {
        // Already loaded in `setup()`: going through `region()->...` would query
        // them again and throw the eager load away.
        $region = $company->region;

        return [
            ...$company->toArray(),
            'province_id' => $region?->province_id,
            'country_id' => $region?->province?->country_id,
        ];
    }
}
