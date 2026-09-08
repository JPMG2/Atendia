<?php

use App\Classes\Main\Client;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Rules\AttributeValidator;
use App\Services\NotificationService;
use App\Traits\HasNotifications;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Identity card of "Mi negocio": logo, name and the description the
 * assistant introduces itself with. Writes through the identity slice,
 * sending only its own fields, so the wizard's location and primary
 * activity stay untouched.
 */
new class extends Component
{
    use HasNotifications;
    use WithFileUploads;

    public string $name = '';

    public ?string $description = null;

    /** The stored path; the drop zone previews it until a new pick lands. */
    public ?string $logo_path = null;

    /**
     * The logo while it is still an upload. Untyped on purpose: Livewire
     * holds a `TemporaryUploadedFile` here, and typing the property would
     * reject the plain `UploadedFile` a test hands over.
     *
     * @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null
     */
    public $logo_file = null;

    public function mount(): void
    {
        $data = $this->client()->personalData()->data();

        $this->name = $data->name;
        $this->description = $data->description;
        $this->logo_path = $data->logo_path;
    }

    /** Rebuilt per request: a Livewire component cannot hold it in a constructor. */
    protected function client(): Client
    {
        return Client::for(Auth::user());
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => AttributeValidator::stringValid(true, '3'),
            'description' => ['nullable', 'string', 'max:500'],
            // SVG is in because a brand mark is vector: it scales without blurring.
            'logo_file' => ['nullable', 'file', 'mimes:png,webp,jpg,jpeg,svg', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'name' => config('nicename.name'),
            'description' => config('nicename.description'),
            'logo_file' => config('nicename.logo_path'),
        ];
    }

    #[Computed]
    public function logoUrl(): ?string
    {
        return $this->logo_path !== null ? Storage::disk('public')->url($this->logo_path) : null;
    }

    public function save(): void
    {
        if (Auth::user()->business === null) {
            $this->dispatchNotification(new NotificationDto(__('notifications.not_found'), NotificationType::Error));

            return;
        }

        $validated = $this->validate();

        $payload = ['name' => $validated['name'], 'description' => $validated['description']];

        // A picked file becomes the path; with none picked the column is not
        // sent at all, so the stored logo stays.
        if ($validated['logo_file'] instanceof UploadedFile) {
            $payload['logo_path'] = $this->storeLogo($validated['logo_file']);
        }

        $business = $this->client()->personalData()->saveIdentity($payload);

        $this->logo_path = $business->logo_path;
        $this->logo_file = null;

        $this->dispatchNotification(resolve(NotificationService::class)->notificationFor($business, 'updated'));
    }

    /**
     * Stores the logo and drops the one it replaces: nothing points at the
     * old file any more, and an orphan on a public disk is nobody's to find.
     */
    private function storeLogo(UploadedFile $file): string
    {
        $path = (string) $file->store('logos', 'public');

        $previous = Auth::user()->business?->logo_path;

        if ($previous !== null && $previous !== $path) {
            Storage::disk('public')->delete($previous);
        }

        return $path;
    }
};
?>

<x-ui.card class="bp-card" data-section="identidad">
    <div class="bp-card-head">
        <h2>{{ __('client.business.identity.title') }}</h2>
    </div>
    <p class="bp-card-sub">{{ __('client.business.identity.sub') }}</p>

    <div class="bp-form">
        <x-catalog.form-row>
            <x-inputsform.input span="full" :label="__('client.business.identity.name')" name="name" wire:model="name" />
        </x-catalog.form-row>
        <x-catalog.form-row>
            <x-inputsform.textarea span="full" :label="__('client.business.identity.description')" name="description"
                :hint="__('client.business.identity.description_hint')" :rows="3" maxlength="500" counter
                wire:model="description" />
        </x-catalog.form-row>
        <x-catalog.form-row>
            <x-inputsform.file span="full" name="logo_file"
                :label="__('client.business.identity.logo').' · '.__('client.business.optional')"
                :note="__('client.business.identity.logo_hint')" :preview="$this->logoUrl"
                wire:model="logo_file" />
        </x-catalog.form-row>
    </div>

    <div class="bp-card-actions">
        <x-ui.button variant="primary" size="sm" wire:click="save">{{ __('client.business.actions.save') }}</x-ui.button>
    </div>
</x-ui.card>
