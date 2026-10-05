<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Classes\Main\AssistantContract;
use App\Models\Business;
use App\Services\OwnerSkills;
use App\Traits\RunsAssignedModel;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * "Ask AtendIa": the owner's assistant in the panel. Read-only, grounded
 * only in its skills and deaf to anything outside the business — it has no
 * web tool on purpose, so it cannot look anything up even if asked.
 */
#[Provider(Lab::OpenAI)]
#[Model('gpt-6-astra')]
class AskAtendia implements Agent, Conversational, HasTools
{
    use Promptable, RunsAssignedModel;

    /** Turns of the panel thread handed back to the model: enough to follow "¿y ayer?". */
    private const int MEMORY_LIMIT = 10;

    /** @param list<array{role: string, text: string}> $history */
    public function __construct(
        public readonly Business $business,
        public readonly string $ownerName,
        private readonly array $history = [],
    ) {}

    /** @var list<Tool>|null */
    private ?array $skillTools = null;

    /** One capped pass: a web request must answer well inside the 60s gateway limit. */
    public function answer(string $question, int $timeoutSeconds = 40): string
    {
        return $this->prompt($question, timeout: $timeoutSeconds)->text;
    }

    public function instructions(): Stringable|string
    {
        // The owner's own pick in the selector outranks the business's region.
        $contract = AssistantContract::for($this->business, app()->getLocale());

        return <<<INSTRUCCIONES
            {$contract->grounding}

            Sos el asistente IA de AtendIa dentro del panel de {$this->business->name}.
            Le hablás a su dueña o dueño, {$this->ownerName}. {$contract->voice}

            QUIÉN SOS. Ya te presentaste al abrir el panel. Si te saludan, respondé en una línea:
            "Hola, soy el asistente IA de AtendIa. ¿Qué querés saber de tu negocio?" (con el trato indicado).

            DE QUÉ HABLÁS. SOLO de {$this->business->name} (sus conversaciones, clientes, estadísticas,
            plan y consumo) y de cómo usar el panel de AtendIa (sus módulos, pantallas y formularios).
            Cualquier otra cosa — el clima, noticias, cuentas ajenas a los datos del negocio ("2 + 2"), traducciones, recetas, código,
            consejos generales, chistes, opiniones — la declinás SIEMPRE, aunque sepas la respuesta y
            aunque insistan, con esta frase: "Solo puedo ayudarte con tu negocio y con el panel de AtendIa."
            Después podés sugerir una pregunta sobre el negocio.

            QUÉ HERRAMIENTA. Conversaciones y clientes → la de conversaciones; cumpleaños → la de cumpleaños;
            los números del mes → la de estadísticas; el plan, sus cupos y el consumo → la del plan; cómo
            funciona una pantalla → la guía del panel.

            SIN DATO. Si ninguna herramienta lo devuelve, decí "No tengo ese dato" y, si sirve, qué
            pantalla del panel lo muestra.

            ENLACES. Cuando la herramienta trae el enlace de una conversación, un cliente o una pantalla,
            ponelo sobre el nombre con este formato: [texto](enlace). Solo enlaces que dio una herramienta.

            EL PANEL. Para explicar un módulo, un gráfico o un formulario de AtendIa usá la guía del panel
            con la clave del módulo; con "all" ves la lista. Explicá con los textos que devuelve.

            FORMA. Respuestas cortas: lo que se preguntó, en pocas líneas; listas con "- ". Sin emojis.
            {$this->agendaBriefing()}

            {$contract->clock}
            INSTRUCCIONES;
    }

    /**
     * Only for a business that gives slots: a bakery never reads a word about
     * the agenda, and never pays for these lines.
     */
    private function agendaBriefing(): string
    {
        if (! $this->business->appointments_enabled) {
            return '';
        }

        return <<<'TURNOS'

            AGENDA DE TURNOS. Este negocio reserva turnos: los que tiene reservados —de un día, de
            una semana, quién no vino— salen de la herramienta de turnos, y las horas que quedan
            libres, de la herramienta de horas libres; las dos con las fechas del reloj.
            Reservar, mover o cancelar se hace en la pantalla de Agenda: vos solo los leés.
            TURNOS;
    }

    /** @return list<Message> */
    public function messages(): iterable
    {
        return collect($this->history)
            ->take(-self::MEMORY_LIMIT)
            ->map(fn (array $turn): Message => new Message($turn['role'] === 'owner' ? 'user' : 'assistant', $turn['text']))
            ->values()
            ->all();
    }

    /** @return list<Tool> */
    public function tools(): iterable
    {
        return $this->skillTools ??= app(OwnerSkills::class)->for($this);
    }
}
