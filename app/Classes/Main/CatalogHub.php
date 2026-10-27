<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Classes\Report\CatalogReport;
use App\Interfaces\Catalog\HasColumns;
use App\Models\CatalogForm;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The catalogs hub for the phone: the masters the web's rail lists to this person (same query, same
 * permission cut, same order and groups), reduced to those the app can already read. A master is
 * readable once its model says its own columns, so that interface is the only switch to flip.
 */
class CatalogHub
{
    public function __construct(private readonly ?User $user) {}

    /** What a master is asked by: its component without the leading "catalog.". */
    public static function slugOf(CatalogForm $form): string
    {
        return Str::after($form->component, 'catalog.');
    }

    public static function isReadable(string $slug): bool
    {
        $model = CatalogReport::MASTERS[$slug] ?? null;

        return $model !== null && is_subclass_of($model, HasColumns::class);
    }

    /** @var Collection<int, CatalogForm> */
    public Collection $forms {
        get => $this->forms ??= CatalogForm::visibleTo($this->user)
            ->filter(fn (CatalogForm $form): bool => self::isReadable(self::slugOf($form)))
            ->values();
    }

    /** @var list<array{label: string, masters: list<array{slug: string, title: string, icon: string}>}> */
    public array $groups {
        get => $this->forms
            ->groupBy('group')
            ->map(fn (Collection $forms, string $label): array => [
                'label' => $label,
                'masters' => $forms->map(fn (CatalogForm $form): array => [
                    'slug' => self::slugOf($form),
                    'title' => $form->title,
                    'icon' => $form->icon ?? 'library',
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }
}
