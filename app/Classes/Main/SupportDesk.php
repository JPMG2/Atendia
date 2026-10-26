<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Models\Menu;
use App\Models\SupportTicketKind;
use Illuminate\Support\Collection;

/**
 * What the report-to-support sheet offers a phone: the web widget's own words, the kinds she
 * switched on in her own order, and the screens a person can point at.
 */
class SupportDesk
{
    /**
     * @var array<string, mixed>
     */
    public array $copy {
        get => [
            'title' => __('support.title'),
            'sub' => __('support.sub'),
            'body' => __('support.fields.body'),
            'bodyHint' => __('support.body_hint'),
            'kindLegend' => __('support.kind_legend'),
            'screen' => __('support.fields.screen'),
            'screenPlaceholder' => __('support.screen_placeholder'),
            'send' => __('support.send'),
            'cancel' => __('support.cancel'),
            'thanksTitle' => __('support.thanks_title'),
            'close' => __('support.thanks_close'),
        ];
    }

    /**
     * @var list<array{value: string, label: string, icon: string}>
     */
    public array $kinds {
        get => SupportTicketKind::offered()
            ->map(fn (SupportTicketKind $kind): array => ['value' => $kind->code, 'label' => $kind->name, 'icon' => $kind->icon])
            ->values()
            ->all();
    }

    /**
     * The screens the app has, named as its menu names them: the same list the web's combobox draws, cut to
     * what a phone can show. The value is the menu's route name, as the web stores it.
     *
     * @var list<array{value: string, label: string}>
     */
    public array $screens {
        get => $this->flatten(Menu::forApp())
            ->filter(fn (Menu $item): bool => $item->route_name !== null)
            ->unique('route_name')
            ->map(fn (Menu $item): array => ['value' => (string) $item->route_name, 'label' => (string) $item->label])
            ->sortBy('label')
            ->values()
            ->all();
    }

    /** The kind a new report starts on: the first one offered. */
    public string $defaultKind {
        get => SupportTicketKind::defaultCode();
    }

    /**
     * @param  Collection<int, Menu>  $items
     * @return Collection<int, Menu>
     */
    private function flatten(Collection $items): Collection
    {
        return $items->flatMap(fn (Menu $item): Collection => collect([$item])->merge($this->flatten($item->childrenRecursive)));
    }
}
