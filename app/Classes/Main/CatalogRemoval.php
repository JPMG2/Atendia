<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Actions\Catalog\RemoveCatalogRecord;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Interfaces\Catalog\Removable;
use App\Services\NotificationService;
use Illuminate\Database\Eloquent\Model;

/**
 * Removing a catalog row, the web editor's and the phone's: what the delete
 * button asks (decided here, so the screen only draws it), the delete itself
 * and the "switch it off" way out of a row somebody still holds.
 */
final class CatalogRemoval
{
    /** @param  object  $blank  The master's model, as the editor holds it; only a removable model deletes. */
    public function __construct(private readonly object $blank) {}

    /**
     * What the delete button asks the person: a record somebody still holds is
     * explained instead of confirmed, and the words name WHO holds it.
     *
     * @return array{status: string, title: string, message: string, accept: string, deactivate?: string, dismiss?: string}
     */
    public function check(int $id): array
    {
        if (! $this->removable()) {
            return $this->answer(RemoveCatalogRecord::MISSING, __('catalog.remove.unsupported'));
        }

        $inspection = app(RemoveCatalogRecord::class)->inspect($this->blank, $id);

        if ($inspection['status'] === RemoveCatalogRecord::MISSING) {
            return $this->answer(RemoveCatalogRecord::MISSING, __('catalog.remove.missing'));
        }

        $name = $this->nameOf($inspection['record']);

        if ($inspection['status'] === RemoveCatalogRecord::IN_USE) {
            $answer = $this->answer(
                RemoveCatalogRecord::IN_USE,
                __('catalog.remove.in_use', ['name' => $name, 'uses' => $this->words($inspection['usage'])]),
                __('catalog.remove.blocked_title'),
            );

            // The way out the message suggests, one tap away: only offered
            // while the record is on, since "switch it off" is the whole point.
            if ($inspection['record']?->getAttribute('is_active') === true) {
                $answer['deactivate'] = __('catalog.remove.deactivate');
                $answer['dismiss'] = __('catalog.remove.understood');
            }

            return $answer;
        }

        $entity = mb_strtolower(app(NotificationService::class)->entityName($this->blank));
        $message = __('catalog.remove.confirm', ['name' => $name]);

        if ($inspection['owns'] !== []) {
            $message .= ' '.__('catalog.remove.confirm_owns', ['owns' => $this->words($inspection['owns'])]);
        }

        return $this->answer(
            RemoveCatalogRecord::REMOVED,
            $message,
            __('catalog.remove.confirm_title', ['entity' => $entity]),
            __('catalog.remove.accept', ['entity' => $entity]),
        );
    }

    /**
     * Deletes after the person confirmed; says what happened in the words the
     * screen shows. A row somebody took meanwhile is refused, never forced.
     *
     * @return array{result: string, notification: NotificationDto}
     */
    public function remove(int $id): array
    {
        if (! $this->removable()) {
            return ['result' => RemoveCatalogRecord::MISSING, 'notification' => new NotificationDto(__('catalog.remove.unsupported'), NotificationType::Error)];
        }

        $result = app(RemoveCatalogRecord::class)->handle($this->blank, $id);

        return ['result' => $result, 'notification' => match ($result) {
            RemoveCatalogRecord::REMOVED => app(NotificationService::class)->deleted($this->blank),
            RemoveCatalogRecord::IN_USE => new NotificationDto(__('catalog.remove.blocked_title'), NotificationType::Warning),
            default => new NotificationDto(__('notifications.not_found'), NotificationType::Error),
        }];
    }

    /** Switches a held record off from the "in use" answer; null when there was nothing on to switch. */
    public function deactivate(int $id): ?NotificationDto
    {
        if (! $this->removable()) {
            return null;
        }

        $record = $this->blank->newQuery()->find($id);

        if ($record === null || $record->getAttribute('is_active') !== true) {
            return null;
        }

        $record->forceFill(['is_active' => false])->save();

        return new NotificationDto(__('catalog.remove.deactivated', ['name' => $this->nameOf($record)]), NotificationType::Success);
    }

    /** @phpstan-assert-if-true Model&Removable $this->blank */
    private function removable(): bool
    {
        return $this->blank instanceof Model && $this->blank instanceof Removable;
    }

    /** @return array{status: string, title: string, message: string, accept: string} */
    private function answer(string $status, string $message, string $title = '', string $accept = ''): array
    {
        return ['status' => $status, 'title' => $title, 'message' => $message, 'accept' => $accept];
    }

    /** The name the dialog quotes: whatever identifies the row to a person. */
    private function nameOf(?Model $record): string
    {
        foreach (['name', 'label', 'subject', 'code', 'step'] as $column) {
            $value = $record?->getAttribute($column);

            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return app(NotificationService::class)->entityName($this->blank);
    }

    /**
     * "14 negocios y 3 países", from counts keyed by their noun.
     *
     * @param  array<string, int>  $counts
     */
    private function words(array $counts): string
    {
        return collect($counts)
            ->map(fn (int $count, string $noun): string => trans_choice('catalog.usage.'.$noun, $count, ['count' => $count]))
            ->values()
            ->join(', ', __('catalog.remove.and'));
    }
}
