<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Client;

use App\Classes\Main\Client;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use App\Models\SocialLink;
use App\Rules\AttributeValidator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;

/**
 * Social networks card of "Mi negocio". Its own section by the owner's call
 * (2026-09-06), never a guest inside Contact. Same row contract as the
 * company's: one link per network, the position on screen is the public
 * order, and whatever leaves the screen leaves the table at save.
 */
class SocialForm extends BaseForm
{
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

    /** Called from the component's `mount()`, not a Form hook. */
    public function setup(): void
    {
        $this->social = $this->rowsFromStore();
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

    public function save(): NotificationDto
    {
        $media = $this->client()->socialMedia;

        if ($media === null) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        // Compacting BEFORE validating keeps each error on its screen row: the
        // starter blank is not a network, but a half-filled row earns its warning.
        $this->social = $this->filledSocialRows();

        $validated = $this->validateServiceData();

        return $this->tryAction(function () use ($media, $validated): NotificationDto {

            $changed = $media->save(array_map(
                fn (array $row): array => ['social_network_id' => $row['social_network_id'], 'url' => $row['url']],
                $validated['social'] ?? [],
            ));

            $this->social = $this->rowsFromStore();

            $business = Auth::user()->business;

            return $changed
                ? $this->notificationService()->updatedRelated($business)
                : $this->notificationService()->notificationFor($business, 'updated');

        }, __('notifications.not_updated'));
    }

    /** Rebuilt per request: a Livewire form cannot hold it in a constructor. */
    private function client(): Client
    {
        return Client::for(Auth::user());
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
        $rows = ($this->client()->socialMedia?->links ?? collect())
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

    protected function transformServiceData(): array
    {
        return ['social' => $this->social];
    }

    protected function getValidationRules(?int $excludeId = null): array
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
    protected function getValidationAttributes(): array
    {
        return [
            'social.*.social_network_id' => config('nicename.social_network_id'),
            'social.*.url' => config('nicename.url'),
        ];
    }
}
