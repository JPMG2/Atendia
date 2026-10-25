<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Agents\AskAdmin;
use App\Interfaces\Main\AdminSkillTool;
use App\Models\Business;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/** Finds businesses by name or by situation: the same list and the same filter as the businesses screen. */
class AdminFindBusiness implements AdminSkillTool
{
    private const int LIMIT = 8;

    /** The cuts the businesses screen offers; anything else is ignored rather than guessed. */
    private const array SITUATIONS = ['problem', 'trialing', 'active', 'past_due', 'paused', 'canceling', 'ended', 'suspended', 'none', 'removed'];

    /** The key of the screen it reads: asking the assistant never opens a door the panel keeps shut. */
    public static function forAdmin(AskAdmin $assistant): ?static
    {
        return $assistant->admin->can('businesses.view') ? new static : null;
    }

    public function description(): Stringable|string
    {
        return 'Busca negocios de la plataforma por nombre y/o por situación (en prueba, activo, en gracia, pausado, '
            .'se da de baja, terminado, suspendido, sin plan, eliminado, o "problem" para los que les cuesta pagar). '
            .'Devuelve su número, plan, situación y el enlace a su ficha. Con el número se pide la ficha completa.';
    }

    public function handle(Request $request): Stringable|string
    {
        $search = trim((string) $request->string('search'));
        $situation = (string) $request->string('situation');
        $situation = in_array($situation, self::SITUATIONS, true) ? $situation : '';

        $matching = Business::directoryMatching($search, $situation);

        if ($matching === 0) {
            return 'No hay ningún negocio que coincida'.($search === '' ? '' : " con «{$search}»").($situation === '' ? '' : ' en esa situación').'.';
        }

        $lines = Business::directory($search, $situation, self::LIMIT)->map(fn (Business $business): string => implode(' · ', array_filter([
            "#{$business->id} {$business->name}",
            $business->subscription === null ? null : __('plan.names.'.$business->plan()->code),
            __('admin.businesses.states.'.$business->situation()),
            $business->email,
            route('admin.businesses', ['negocio' => $business->id]),
        ])));

        return "Coinciden {$matching} negocios".($matching > self::LIMIT ? ' (se muestran '.self::LIMIT.')' : '').":\n- ".$lines->implode("\n- ");
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Parte del nombre del negocio. Vacío para no filtrar por nombre.'),
            'situation' => $schema->string()->description('Una de: '.implode(', ', self::SITUATIONS).'. Vacío para no filtrar.'),
        ];
    }
}
