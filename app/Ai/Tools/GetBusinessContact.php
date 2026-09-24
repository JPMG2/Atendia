<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Agents\AsistenteAtendia;
use App\Interfaces\Main\AssistantSkillTool;
use App\Models\Business;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/** Where the business is and how to reach it, straight from its profile. */
class GetBusinessContact implements AssistantSkillTool
{
    public function __construct(private readonly Business $business) {}

    public static function forAssistant(AsistenteAtendia $assistant): ?static
    {
        return $assistant->business !== null ? new static($assistant->business) : null;
    }

    public function description(): Stringable|string
    {
        return 'Devuelve la dirección del negocio, si atiende en un local o a distancia, '
            .'y sus datos de contacto (WhatsApp, correo, web y redes). Usala para '
            .'consultas de ubicación, cómo llegar o cómo contactarlos.';
    }

    public function handle(Request $request): Stringable|string
    {
        $lines = [...$this->business->premisesLines(), ...$this->business->contactLines()];

        return $lines === [] ? 'El negocio no cargó su dirección ni sus datos de contacto.' : implode("\n", $lines);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
