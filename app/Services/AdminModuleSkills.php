<?php

declare(strict_types=1);

namespace App\Services;

use App\Classes\Main\ScreenTexts;
use App\Models\Company;
use App\Models\Menu;
use Illuminate\Support\Str;
use Laravel\Ai\Skills\Skill;

/**
 * What the admin's assistant knows about every screen of both panels, as SDK
 * skills: the model sees only a name and a one-line description and loads a
 * screen's texts when it needs them. The texts are the ones the screen prints.
 */
class AdminModuleSkills
{
    /** One screen's texts never need more than this; a longer one would crowd the answer out. */
    private const int MAX_CHARS = 9000;

    /** @return list<Skill> */
    public function all(): array
    {
        return [
            ...$this->panel((array) config('atendia.admin_assistant.guide'), 'admin', __('ask_admin.skills.admin')),
            ...$this->panel((array) config('atendia.owner_assistant.guide'), 'cliente', __('ask_admin.skills.client')),
        ];
    }

    /**
     * @param  array<string, array{0: string, 1: string}>  $guide
     * @return list<Skill>
     */
    private function panel(array $guide, string $prefix, string $where): array
    {
        $groups = Menu::parentLabelKeys();

        return collect($guide)
            ->map(function (array $texts, string $route) use ($prefix, $where, $groups): Skill {
                // "Negocios › Todos": a child's own label ("Todos") names nothing by itself.
                $screen = isset($groups[$texts[0]]) ? __($groups[$texts[0]]).' › '.__($texts[0]) : __($texts[0]);

                return new Skill(
                    name: $prefix.'-'.Str::slug(str_replace('.', '-', Str::after($route, 'admin.'))),
                    description: __('ask_admin.skills.description', ['screen' => $screen, 'where' => $where, 'brand' => Company::brand()]),
                    instructions: mb_substr("{$where}: {$screen}".($prefix === 'admin' ? ' · '.route($route) : '')
                        ."\nTextos de la pantalla (clave: texto; lo que empieza con dos puntos es un dato que se completa solo):\n"
                        .ScreenTexts::of($texts[1]), 0, self::MAX_CHARS),
                );
            })
            ->values()
            ->all();
    }
}
