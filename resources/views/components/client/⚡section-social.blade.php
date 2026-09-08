<?php

use App\Classes\Main\Client;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Models\SocialLink;
use App\Models\SocialNetwork;
use App\Rules\AttributeValidator;
use App\Services\NotificationService;
use App\Traits\HasNotifications;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Social networks card of "Mi negocio". Its own section by the owner's call
 * (2026-09-06), never a guest inside Contact. Same row contract as the
 * company's: one link per network, the position on screen is the public
 * order, and whatever leaves the screen leaves the table at save.
 */
new class extends Component
{
    use HasNotifications;

    /**
     * `key` identifies the ROW and goes in the `wire:key`, so removing one
     * from the middle moves the right node. `id` is the stored link, and
     * tells dropping a blank row from dropping a real one.
     *
     * @var array<int, array{key: int, id: int|null, social_network_id: int|null, url: string|null}>
     */
    public array $social = [];

    /** Next row key. Never picked by the front. */
    #[Locked]
    public int $nextSocialKey = 1;

    public function mount(): void
    {
        $this->social = $this->rowsFromStore();
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
            'social' => ['array', 'max:20'],
            'social.*.social_network_id' => ['required', 'integer', 'distinct', 'exists:social_networks,id'],
            'social.*.url' => [...AttributeValidator::stringValid(true, '3'), 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        // Laravel resolves `social.0.url` against the wildcard key.
        return [
            'social.*.social_network_id' => config('nicename.social_network_id'),
            'social.*.url' => config('nicename.url'),
        ];
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    #[Computed]
    public function socialOptions(): array
    {
        return SocialNetwork::options(states: [true]);
    }

    /** Adding costs a request: the rows are SERVER state, so nothing typed is lost. */
    public function addSocialRow(int $after): void
    {
        array_splice($this->social, $after + 1, 0, [$this->blankSocialRow()]);
    }

    /**
     * Removes a row from the SCREEN only: a stored link dies at save, when
     * the action reconciles the table with what the screen kept. One row
     * always remains, or there would be nowhere to start again.
     */
    public function removeSocialRow(int $index): void
    {
        unset($this->social[$index]);

        $this->social = array_values($this->social);

        if ($this->social === []) {
            $this->social = [$this->blankSocialRow()];
        }
    }

    public function save(): void
    {
        $media = $this->client()->socialMedia();

        if ($media === null) {
            $this->dispatchNotification(new NotificationDto(__('notifications.not_found'), NotificationType::Error));

            return;
        }

        // Compacting BEFORE validating keeps each error on its screen row: the
        // starter blank is not a network, but a half-filled row earns its warning.
        $this->social = $this->filledSocialRows();

        $validated = $this->validate();

        $changed = $media->save(array_map(
            fn (array $row): array => ['social_network_id' => $row['social_network_id'], 'url' => $row['url']],
            $validated['social'] ?? [],
        ));

        $this->social = $this->rowsFromStore();

        $business = Auth::user()->business;

        $this->dispatchNotification($changed
            ? resolve(NotificationService::class)->updatedRelated($business)
            : resolve(NotificationService::class)->notificationFor($business, 'updated'));
    }

    /**
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
     * The stored networks as screen rows, in their public order. A business
     * yet to be born gets the blank starter row too.
     *
     * @return array<int, array{key: int, id: int|null, social_network_id: int|null, url: string|null}>
     */
    private function rowsFromStore(): array
    {
        $rows = ($this->client()->socialMedia()?->links() ?? collect())
            ->map(fn (SocialLink $link): array => [
                'key' => $this->nextSocialKey++,
                'id' => $link->id,
                'social_network_id' => $link->social_network_id,
                'url' => $link->url,
            ])
            ->values()
            ->all();

        return $rows === [] ? [$this->blankSocialRow()] : $rows;
    }

    /**
     * @return array{key: int, id: null, social_network_id: null, url: null}
     */
    private function blankSocialRow(): array
    {
        return ['key' => $this->nextSocialKey++, 'id' => null, 'social_network_id' => null, 'url' => null];
    }
};
?>

<x-ui.card class="bp-card" data-section="redes">
    <div class="bp-card-head">
        <h2>{{ __('client.business.social.title') }}</h2>
    </div>
    <p class="bp-card-sub">{{ __('client.business.social.sub') }}</p>

    <div class="bp-form">
        <div class="config-social">
            <div class="config-social-row config-social-head">
                <span class="f-short field-label">{{ __('client.business.social.network') }}</span>
                <span class="f-long field-label">{{ __('client.business.social.url') }}</span>
                <span class="config-social-spacer" aria-hidden="true"></span>
            </div>

            {{-- The rows live on the SERVER, like the company's: a row that only
            exists in the browser cannot be saved. Remove and add share the
            line so the eye never leaves the row. --}}
            @foreach ($social as $index => $row)
                <div class="config-social-row" wire:key="social-{{ $row['key'] }}">
                    <x-inputsform.combobox span="short" :id="'bp-social-' . $index . '-network'"
                        :name="'social.' . $index . '.social_network_id'" :aria-label="__('client.business.social.network')"
                        :placeholder="__('client.business.social.network_placeholder')" :options="$this->socialOptions"
                        :value="$row['social_network_id']" wire:model="social.{{ $index }}.social_network_id" />

                    <x-inputsform.input span="long" icon="link" :id="'bp-social-' . $index . '-url'"
                        :name="'social.' . $index . '.url'" :aria-label="__('client.business.social.url')"
                        :placeholder="__('client.business.social.url_placeholder')" maxlength="255"
                        wire:model="social.{{ $index }}.url" />

                    <x-ui.icon-button icon="trash-2" variant="ghost" class="config-social-remove"
                        data-testid="social-remove" :label="__('client.business.social.remove')"
                        :disabled="count($social) === 1 && $row['id'] === null"
                        wire:click="removeSocialRow({{ $index }})" />

                    <x-ui.icon-button icon="plus" variant="ghost" class="config-social-add"
                        :label="__('client.business.social.add')" data-testid="social-add"
                        wire:click="addSocialRow({{ $index }})" />
                </div>
            @endforeach
        </div>
    </div>

    <div class="bp-card-actions">
        <x-ui.button variant="primary" size="sm" wire:click="save">{{ __('client.business.actions.save') }}</x-ui.button>
    </div>
</x-ui.card>
