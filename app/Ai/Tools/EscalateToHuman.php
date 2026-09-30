<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Agents\AsistenteAtendia;
use App\Enums\ConversationStatus;
use App\Interfaces\Main\AssistantSkillTool;
use App\Messaging\Channels\Panel;
use App\Messaging\Panel\HandedToTeam;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Department;
use App\Services\EvolutionApi;
use App\Services\OwnerPings;
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

        $this->conversation->update([
            'status' => ConversationStatus::Team,
            'escalated_at' => now(),
            'handoff_reminded_at' => null,
            'department_id' => $department?->id,
        ]);

        // The panel reads the same news as the pings, and keeps it: a WhatsApp
        // alert sent at 3am is gone by morning, the bell row is still there.
        (new Panel($this->conversation, [], HandedToTeam::class))->send();

        // Best effort: a failed ping must never break the escalation itself.
        rescue(fn () => $this->alertTeam($reason, $department));

        $answer = 'Equipo avisado. Ahora despedite con cortesía en el idioma del cliente: '
            .'una persona del equipo le escribe a la brevedad. No sigas resolviendo esta consulta.';

        // Out of the room's hours the promise changes: say WHEN, from its schedule, never "right away".
        if ($department !== null && ! $department->isOpenAt(now($this->business->localTimezone()))) {
            $answer .= " El departamento {$department->name} está fuera de horario ("
                .($department->scheduleLabel() ?? 'el horario del negocio')
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

    private function alertTeam(string $reason, ?Department $department): void
    {
        if ($this->business->whatsapp_instance === null
            || $this->business->isOwnerWhatsApp((string) $this->conversation->contact_phone)) {
            return;
        }

        $members = $department?->reachableMembers() ?? new Collection;
        $details = [
            'name' => $this->conversation->contact_name ?? $this->conversation->contact_phone,
            'phone' => $this->conversation->contact_phone,
            'reason' => $reason !== '' ? $reason : __('assistant.handoff.no_reason'),
            // The alert names ONE customer and says "atiéndelo": the link opens
            // that thread, not an inbox where the name has to be found again.
            'url' => route('conversations', ['hilo' => $this->conversation->id]),
        ];

        // Nobody available in the room: the owner, as before departments existed.
        if ($members->isEmpty()) {
            $this->ping($this->business->ownerWhatsAppDigits(), __('assistant.handoff.owner_alert', $details));

            return;
        }

        foreach ($members as $member) {
            $this->ping($member->whatsappDigits(), __('assistant.handoff.department_alert', [...$details, 'department' => $department->name]));
        }
    }

    private function ping(string $number, string $text): void
    {
        if ($number === '') {
            return;
        }

        $pingId = app(EvolutionApi::class)->sendText($this->business->whatsapp_instance, $number, $text);

        app(OwnerPings::class)->remember($this->business->whatsapp_instance, $pingId, (int) $this->conversation->id);
    }
}
