<?php

use App\Livewire\Forms\Catalog\CatalogPhotoForm;
use App\Models\CatalogPhoto;
use App\Traits\HasNotifications;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * The photos of one product or service, inside its sheet: ONE component for
 * both screens. Picking a file saves it at once — moderation, shrink, store.
 */
new class extends Component
{
    use HasNotifications;
    use WithFileUploads;

    public CatalogPhotoForm $form;

    public function mount(string $type, int $id): void
    {
        $this->form->setup($type, $id);
    }

    /** @return Collection<int, CatalogPhoto> */
    #[Computed]
    public function photos(): Collection
    {
        return $this->form->item()->photos;
    }

    /** @return array{used: int, cap: int, itemUsed: int, perItem: int} */
    #[Computed]
    public function quota(): array
    {
        return $this->form->quota();
    }

    public function updatedFormPhoto(): void
    {
        $this->dispatchNotification($this->form->save());
        unset($this->photos, $this->quota);
    }

    public function remove(int $photoId): void
    {
        $this->dispatchNotification($this->form->remove($photoId));
        unset($this->photos, $this->quota);
    }
};
?>

<div class="cat-photos" x-data="catalogPhotos">
    <div class="cat-photos-head">
        <span class="cat-photos-title">{{ __('catalog_photos.title') }}</span>
        <span class="cat-photos-meter font-mono">
            {{ __('catalog_photos.meter_item', ['used' => $this->quota['itemUsed'], 'cap' => $this->quota['perItem']]) }}
            · {{ __('catalog_photos.meter', ['used' => number_format($this->quota['used'], 0, ',', '.'), 'cap' => number_format($this->quota['cap'], 0, ',', '.')]) }}
        </span>
    </div>
    <p class="cat-photos-hint">{{ __('catalog_photos.hint') }}</p>

    @if ($this->photos->isNotEmpty())
        <ul class="cat-photos-grid">
            @foreach ($this->photos as $photo)
                <li class="cat-photo" wire:key="photo-{{ $photo->id }}">
                    <img src="{{ $photo->thumbUrl() }}" alt="" loading="lazy" />
                    @if ($loop->first)
                        <span class="cat-photo-cover">{{ __('catalog_photos.cover') }}</span>
                    @endif
                    <x-ui.icon-button
                        icon="trash-2"
                        size="sm"
                        variant="secondary"
                        class="cat-photo-remove"
                        :label="__('catalog_photos.remove')"
                        x-on:click="confirmRemove({{ $photo->id }})"
                    />
                </li>
            @endforeach
        </ul>
    @endif

    @if ($this->quota['itemUsed'] < $this->quota['perItem'] && $this->quota['used'] < $this->quota['cap'])
        <x-catalog.form-row>
            <x-inputsform.file
                span="full"
                name="photo"
                :label="__('catalog_photos.drop')"
                :note="__('catalog_photos.note')"
                accept="image/png,image/webp,image/jpeg"
                wire:model="form.photo"
            />
        </x-catalog.form-row>
    @endif
</div>

@script
    <script>
        // A removed photo stops reaching customers: one question first.
        Alpine.data('catalogPhotos', () => ({
            async confirmRemove(id) {
                if (
                    !(await dialog.confirm({
                        title: @js(__('catalog_photos.remove_title')),
                        message: @js(__('catalog_photos.remove_message')),
                        accept: @js(__('catalog_photos.remove')),
                        type: 'danger',
                    }))
                ) {
                    return;
                }

                await this.$wire.remove(id);
            },
        }));
    </script>
@endscript
