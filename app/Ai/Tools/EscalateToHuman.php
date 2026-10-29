<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Actions\Business\RaiseHandoff;
use App\Ai\Agents\AsistenteAtendia;
use App\Interfaces\Main\AssistantSkillTool;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Department;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The assistant's raised hand: THIS thread goes to the team and the
 * assistant falls silent in it. With departments, the model names one and
 * only its available people are pinged; nobody there means the owner.
 * Pinned at construction — the model never chooses which thread.
 */
class EscalateToHuman implements AssistantSkillTool
{
    public function __construct(
        private readonly Business $business,
        private readonly Conversation $conversation,
    ) {}

    public static function forAssistant(AsistenteAtendia $assistant): ?static
    {
        return $assistant->business !== null && $assistant->conversation !== null ? new static($assistant->business, $assistant->conversation) : null;
    }

    public function description(): Stringable|string
    {
        return 'Deriva ESTA conversación a una persona del equipo del negocio. Usala '
            .'solo cuando tus instrucciones de derivación lo indiquen. Recibe "reason": '
            .'el motivo en UNA frase, SIEMPRE en español'
            .($this->departments()->isNotEmpty() ? ', y "department": el departamento que corresponde, de la lista de tus instrucciones.' : '.');
    }

    public function handle(Request $request): Stringable|string
    {
        $reason = trim((string) $request['reason']);
        $department = $this->matchDepartment((string) ($request['department'] ?? ''));

        app(RaiseHandoff::class)->handle($this->business, $this->conversation, $reason, $department);

        $answer = 'Equipo avisado. Ahora despedite con cortesía en el idioma del cliente: '
            .'una persona del equipo le escribe a la brevedad. No sigas resolviendo esta consulta.';

        // Out of the room's hours the promise changes: say WHEN, from its schedule, never "right away".
        if ($department !== null && ! $department->isOpenAt(now($this->business->localTimezone()))) {
            $hours = $department->scheduleLabel();

            // A room closed every day has no hour to promise: saying one would be an invention.
            $answer .= $hours === ''
                ? " El departamento {$department->name} no atiende ningún día: decile que el equipo le responde apenas pueda, sin prometer día ni hora."
                : " El departamento {$department->name} está fuera de horario ("
                    .($hours ?? 'el horario del negocio')
                    .'): decile cuándo le van a responder según ese horario y tu reloj, sin inventar.';
        }

        return $answer;
    }

    public function schema(JsonSchema $schema): array
    {
        $names = $this->departments()->pluck('name')->all();

        return array_filter([
            'reason' => $schema->string()->required(),
            'department' => $names === [] ? null : $schema->string()->enum($names)->description('El departamento al que le toca, según "Deriva aquí cuando".'),
        ]);
    }

    /** @return Collection<int, Department> */
    private function departments(): Collection
    {
        return $this->business->plan()->hasDepartments ? $this->business->departments : new Collection;
    }

    /** The model's pick, matched loosely (case, accents) against THIS business's rooms only. */
    private function matchDepartment(string $name): ?Department
    {
        $wanted = Str::lower(Str::ascii(trim($name)));

        return $wanted === '' ? null : $this->departments()->first(
            fn (Department $department): bool => Str::lower(Str::ascii($department->name)) === $wanted,
        );
    }
}
