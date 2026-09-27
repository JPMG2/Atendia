<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Agents\AskAtendia;
use App\Classes\Main\Team;
use App\Interfaces\Main\OwnerSkillTool;
use App\Models\Business;
use App\Models\Department;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/** The "Equipo" screen in one read: who is available, what waits where, who holds what. */
class OwnerTeamStatus implements OwnerSkillTool
{
    public function __construct(private readonly Business $business) {}

    public static function forOwner(AskAtendia $assistant): ?static
    {
        return new static($assistant->business);
    }

    public function description(): Stringable|string
    {
        return 'El equipo del negocio ahora: cada persona (disponible o ausente y cuántas charlas '
            .'tiene tomadas), cada departamento (quién atiende y cuántas charlas esperan) e '
            .'invitaciones pendientes. Usala para "¿quién está?", "¿qué espera en Pagos?" o "¿quién tiene más?".';
    }

    public function handle(Request $request): Stringable|string
    {
        $team = new Team($this->business);

        $people = $team->members->map(fn (User $member): string => '- '.$member->name
            .' ('.($member->isAgent() ? 'agente' : 'titular').', '.($member->is_available ? 'disponible' : 'ausente')
            .", {$member->open_threads} charlas tomadas".($member->whatsapp === null ? ', sin WhatsApp para avisos' : '').')');

        $lines = ['Personas:', ...$people->all()];

        if ($team->hasDepartments) {
            $rooms = $team->departments->map(fn (Department $department): string => "- {$department->name}: "
                .($department->users->isEmpty() ? 'sin personas (avisa a la titular)' : $department->users->pluck('name')->implode(', '))
                .", {$department->waiting} charlas esperando");

            $lines = [...$lines, 'Departamentos:', ...($rooms->isEmpty() ? ['- No hay departamentos cargados.'] : $rooms->all())];
        }

        $pending = $team->invitations->count();

        return implode("\n", [...$lines, "Invitaciones pendientes: {$pending}.", 'Pantalla: '.route('team')]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
