<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Agents\AskAtendia;
use App\Interfaces\Main\OwnerSkillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * How AtendIa's own panel works, read from the SAME translations the screens
 * print: a renamed field or a new hint reaches the assistant with no second
 * copy to keep in sync, and it can never describe a form that does not exist.
 */
class PanelGuide implements OwnerSkillTool
{
    private const int MAX_CHARS = 9000;

    public static function forOwner(AskAtendia $assistant): ?static
    {
        return new static;
    }

    public function description(): Stringable|string
    {
        return 'Cómo funciona el panel de Atendia: con module="all" lista los módulos con su enlace; '
            .'con la clave de un módulo devuelve los textos de su pantalla (títulos, campos, ayudas, '
            .'gráficos y botones) para explicar qué muestra, qué significa cada dato y cómo se usa.';
    }

    public function handle(Request $request): Stringable|string
    {
        $modules = (array) config('atendia.owner_assistant.guide');
        $module = (string) $request['module'];

        if (! array_key_exists($module, $modules)) {
            return "Módulos del panel (clave · nombre · enlace):\n".collect($modules)
                ->map(fn (array $texts, string $route): string => "- {$route} · ".__($texts[0]).' · '.route($route))
                ->implode("\n");
        }

        [$title, $group] = $modules[$module];

        // A regional file (es_AR) is partial: asked for a whole group it returns
        // only its overrides, so the base language is laid underneath first.
        $screen = array_replace_recursive(
            (array) trans($group, [], (string) config('app.fallback_locale')),
            (array) trans($group),
        );

        $texts = collect(Arr::dot($screen))
            ->filter(fn (mixed $text): bool => is_string($text) && $text !== '')
            ->map(fn (string $text, string $key): string => $key.': '.strip_tags($text))
            ->implode("\n");

        // How the numbers are measured goes first: it never fits on the screen, so the cut must not reach it.
        $measures = (array) __("ask.guide.{$module}");
        $measured = array_is_list($measures) || $measures === [] || ! is_string(reset($measures))
            ? ''
            : "Cómo se mide cada dato:\n- ".implode("\n- ", $measures)."\n";

        return mb_substr('Módulo '.__($title).' · '.route($module)."\n".$measured
            ."Textos de la pantalla (clave: texto; lo que empieza con dos puntos es un dato que se completa solo):\n".$texts, 0, self::MAX_CHARS);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'module' => $schema->string()
                ->description('"all" o la clave de un módulo.')
                ->enum(['all', ...array_keys((array) config('atendia.owner_assistant.guide'))])
                ->required(),
        ];
    }
}
