<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\DeleteCatalogPhoto;
use App\Actions\Catalog\StoreCatalogPhoto;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use App\Models\Business;
use App\Models\Product;
use App\Models\Service;
use App\Rules\AttributeValidator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;

/**
 * The photos of ONE product or service. The quota goes first, so a full
 * catalog never spends a moderation call; past it what is there stays.
 */
class CatalogPhotoForm extends BaseForm
{
    public string $itemType = 'product';

    public int $itemId = 0;

    public ?UploadedFile $photo = null;

    public function setup(string $itemType, int $itemId): void
    {
        $this->itemType = $itemType === 'service' ? 'service' : 'product';
        $this->itemId = $itemId;
        $this->reset('photo');
    }

    /** Tenant-scoped: another business's id finds nothing. */
    public function item(): Product|Service
    {
        return $this->itemType === 'service'
            ? Service::query()->findOrFail($this->itemId)
            : Product::query()->findOrFail($this->itemId);
    }

    /** @return array{used: int, cap: int, itemUsed: int, perItem: int} */
    public function quota(): array
    {
        $business = $this->business();
        $plan = $business->plan();

        return [
            'used' => $business->catalogPhotosCount(),
            'cap' => $plan->catalogPhotos,
            'itemUsed' => $this->item()->photos()->count(),
            'perItem' => $plan->photosPerItem,
        ];
    }

    public function save(): NotificationDto
    {
        $quota = $this->quota();

        if ($quota['used'] >= $quota['cap']) {
            $this->reset('photo');

            return new NotificationDto(__('catalog_photos.full_plan', ['cap' => $quota['cap']]), NotificationType::Warning);
        }

        if ($quota['itemUsed'] >= $quota['perItem']) {
            $this->reset('photo');

            return new NotificationDto(__('catalog_photos.full_item', ['cap' => $quota['perItem']]), NotificationType::Warning);
        }

        $validated = $this->validateServiceData();

        return $this->tryAction(function () use ($validated): NotificationDto {
            app(StoreCatalogPhoto::class)->handle($this->item(), $validated['photo']);
            $this->reset('photo');

            return new NotificationDto(__('catalog_photos.added'), NotificationType::Success);
        }, __('notifications.not_updated'));
    }

    public function remove(int $photoId): NotificationDto
    {
        $photo = $this->item()->photos()->find($photoId);

        if ($photo === null) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        app(DeleteCatalogPhoto::class)->handle($photo);

        return new NotificationDto(__('catalog_photos.removed'), NotificationType::Success);
    }

    private function business(): Business
    {
        return Auth::user()->business;
    }

    protected function transformServiceData(): array
    {
        return ['photo' => $this->photo];
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return ['photo' => AttributeValidator::imageUpload('catalog_photo', true, 10240)];
    }

    /** @return array<string, string> */
    protected function getValidationAttributes(): array
    {
        return ['photo' => __('catalog_photos.field')];
    }
}
