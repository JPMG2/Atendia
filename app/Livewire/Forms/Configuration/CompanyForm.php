<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Configuration;

use App\Classes\Main\CompanyEditor;
use App\Dto\CompanyDto;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use App\Models\Company;
use App\Models\SocialLink;
use App\Models\SocialNetwork;
use App\Rules\AttributeValidator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
    private const array MAIN_SCREEN_STATE = ['country_id', 'province_id'];

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
    private const array LOGO_RULES = ['nullable', 'file', 'mimes:png,webp,jpg,jpeg,svg', 'max:2048'];

    private const array LOGO_UPLOADS = [
        'logo_path_light' => 'logo_light_file',
        'logo_path_dark' => 'logo_dark_file',
    ];

    /**
     * The loaded record; `null` means nothing has been loaded yet.
     *
     * `#[Locked]` because the front never picks it: `setup()` assigns it from the
     * table's only row, or `saveMain()` right after creating it.
     */
    #[Locked]
    public ?int $recordId = null;

    /** The row's version when this screen read it (`CompanyEditor::versionOf`). */
    #[Locked]
    public ?string $version = null;

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
    public $logo_light_file;

    /** @var TemporaryUploadedFile|null */
    public $logo_dark_file;

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
        $company = Company::forScreen();

        if ($company === null) {
            $this->data = new CompanyDto;
            $this->social = [$this->blankSocialRow()];

            return;
        }

        $this->recordId = $company->id;
        $this->version = CompanyEditor::versionOf($company);
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
     * Picks the network of a pasted link on a row that has none yet.
     *
     * A choice already made is never overwritten, and a network another row
     * holds is left alone: the table keeps one link per network. The row gets
     * a fresh key because the combobox only reads its value when it is built.
     */
    public function detectSocialNetwork(int $index): void
    {
        $row = $this->social[$index] ?? null;

        if ($row === null || $row['social_network_id'] !== null || trim((string) $row['url']) === '') {
            return;
        }

        $networkId = SocialNetwork::idForUrl((string) $row['url']);

        if ($networkId === null || in_array($networkId, array_column($this->social, 'social_network_id'), true)) {
            return;
        }

        $this->social[$index]['social_network_id'] = $networkId;
        $this->social[$index]['key'] = $this->nextSocialKey++;
    }

    /** Moves a row to a new position: the order on screen is the order in the footer. */
    public function moveSocialRow(int $from, int $to): void
    {
        if (! array_key_exists($from, $this->social)) {
            return;
        }

        $row = $this->social[$from];
        unset($this->social[$from]);

        $rows = array_values($this->social);
        array_splice($rows, max(0, $to), 0, [$row]);

        $this->social = $rows;
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

        if ($row['id'] !== null && CompanyEditor::changedSince($this->version)) {
            return $this->changedMeanwhile();
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
        // spatie logs the delete on its own (LogsActivity on the model).
        $link = app(CompanyEditor::class)->removeSocialLink($id);
        $this->version = CompanyEditor::versionOf(Company::forEditing());

        return $link === null
            ? new NotificationDto(__('notifications.not_found'), NotificationType::Error)
            : $this->notificationService()->notificationFor($link, 'deleted');
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

        if (CompanyEditor::changedSince($this->version)) {
            return $this->changedMeanwhile();
        }

        $creating = $this->recordId === null;

        return $this->tryAction(function () use ($validated): NotificationDto {

            // Taken before the save: the logos the row points at now, to drop the ones it stops pointing at.
            $before = Arr::only(Company::forEditing()?->getAttributes() ?? [], array_keys(self::LOGO_UPLOADS));

            $company = app(CompanyEditor::class)->saveMain($this->columnsWithLogos($validated));
            $this->dropReplacedLogos($before, $company);

            $this->recordId = $company->id;

            $this->settleLogos($company);
            $this->version = CompanyEditor::versionOf(Company::forEditing());

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

        if (CompanyEditor::changedSince($this->version)) {
            return $this->changedMeanwhile();
        }

        return $this->tryAction(function () use ($validated): NotificationDto {

            $company = Company::forEditing() ?? throw (new ModelNotFoundException)->setModel(Company::class, [$this->recordId]);

            // The screen always sends its rows: an empty list is "no networks", not "leave them".
            $linksChanged = app(CompanyEditor::class)->saveCommercial($company, ['social' => [], ...$validated]);
            $this->version = CompanyEditor::versionOf(Company::forEditing());

            // If ONLY the networks changed, the `companies` row is untouched and
            // `notificationFor()` would say "nothing changed" with the networks
            // freshly saved in plain sight.
            if ($linksChanged && ! $company->wasChanged()) {
                return $this->notificationService()->updatedRelated($company);
            }

            return $this->notificationService()->notificationFor($company, 'updated');

        }, __('notifications.not_updated'));
    }

    /**
     * Puts the commercial step back to the last saved state, networks included.
     */
    public function discardCommercial(): void
    {
        if ($this->data === null) {
            return;
        }

        $company = $this->loadedCompany();

        $saved = $company !== null ? CompanyDto::fromArray($company->toArray()) : new CompanyDto;

        foreach (CompanyEditor::COMMERCIAL_COLUMNS as $field) {
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

        $company = $this->loadedCompany();

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

    /** Another tab or the app saved first: nothing is written, the screen asks for a reload. */
    private function changedMeanwhile(): NotificationDto
    {
        return new NotificationDto(__('company.changed_meanwhile'), NotificationType::Warning);
    }

    /**
     * The row this screen loaded. Null while the screen was opened before any
     * company existed, even if another tab or the app created one since: a
     * row this screen never showed must not be discarded into or stripped.
     */
    private function loadedCompany(): ?Company
    {
        return $this->recordId === null ? null : Company::forScreen();
    }

    /**
     * The step's columns, with a freshly picked logo already on disk.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function columnsWithLogos(array $validated): array
    {
        $columns = Arr::except($validated, array_values(self::LOGO_UPLOADS));

        foreach (self::LOGO_UPLOADS as $column => $upload) {
            $file = $validated[$upload] ?? null;

            if (! $file instanceof UploadedFile) {
                continue;
            }

            $columns[$column] = (string) $file->store('logos', 'public');
        }

        return $columns;
    }

    /**
     * Drops the logos the saved row no longer points at. Only after the save:
     * a save that fails must leave the company on a file that still exists.
     *
     * @param  array<string, mixed>  $before
     */
    private function dropReplacedLogos(array $before, Company $company): void
    {
        foreach ($before as $column => $previous) {
            if (is_string($previous) && $previous !== $company->{$column}) {
                Storage::disk('public')->delete($previous);
            }
        }
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

        $company = $this->loadedCompany();

        if ($company === null || $company->{$column} === null) {
            return null;
        }

        if (CompanyEditor::changedSince($this->version)) {
            return $this->changedMeanwhile();
        }

        return $this->tryAction(function () use ($company, $column): NotificationDto {
            $path = $company->{$column};

            $company->fill([$column => null])->save();
            $this->version = CompanyEditor::versionOf($company);

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
        return CompanyEditor::names();
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
        // The shared rules, plus the logos only this screen uploads.
        return $step === self::STEP_MAIN
            ? [
                ...CompanyEditor::rules(CompanyEditor::STEP_MAIN),
                'logo_path_light' => ['nullable', ...AttributeValidator::stringValid(false, '1')],
                'logo_path_dark' => ['nullable', ...AttributeValidator::stringValid(false, '1')],
                'logo_light_file' => self::LOGO_RULES,
                'logo_dark_file' => self::LOGO_RULES,
            ]
            : CompanyEditor::rules($step);
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
