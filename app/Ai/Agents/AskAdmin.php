<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Classes\Main\AssistantContract;
use App\Models\Company;
use App\Models\User;
use App\Services\AdminModuleSkills;
use App\Services\AdminSkills;
use App\Traits\RunsAssignedModel;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\RepairToolCalls;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Contracts\HasSkills;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Contracts\VerifiesConversationOwnership;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Promptable;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Skills\Skill;
use Stringable;

/**
 * The platform admin's assistant, in the panel. It reads across every
 * business through its tools and explains every screen of both panels through
 * SDK skills it loads on demand. Read-only, deaf to anything outside the
 * platform and without a web tool on purpose. Its threads live in the SDK's own
 * conversation tables, so a turn that failed or paused leaves a trace.
 */
#[Provider(Lab::OpenAI)]
#[Model('gpt-6-luna')]
#[MaxSteps(8)]
#[Timeout(60)]
#[RepairToolCalls]
class AskAdmin implements Agent, Conversational, HasSkills, HasTools
{
    use Promptable, RemembersConversations, RunsAssignedModel;

    /** Messages of the thread handed back to the model: enough to follow "¿y ese negocio?". */
    private const int MEMORY_LIMIT = 12;

    public function __construct(public readonly User $admin) {}

    /** @var list<Tool>|null */
    private ?array $skillTools = null;

    /** @var list<Skill>|null Built once per agent: a prompt asks for them more than once. */
    private ?array $moduleSkills = null;

    /**
     * Joins the stored conversation the panel says it is in, but only when it is
     * THIS admin's: the id travels through the browser, so it is input, and the
     * SDK's `continue()` does not check it. Anything else starts a new thread.
     */
    public function resume(?string $conversationId): static
    {
        return $this->owns($conversationId) ? $this->continue($conversationId, as: $this->admin) : $this->forUser($this->admin);
    }

    /** Whether a stored thread is this admin's; a store that cannot say who owns a thread is not trusted with one. */
    public function owns(?string $conversationId): bool
    {
        $store = resolve(ConversationStore::class);

        return $conversationId !== null && $store instanceof VerifiesConversationOwnership && $store->conversationBelongsTo(
            $conversationId,
            Conversation::participantType($this->admin),
            Conversation::participantKey($this->admin),
        );
    }

    /** One capped pass: the response also says which stored conversation the turn joined. */
    public function answer(string $question): AgentResponse
    {
        return $this->prompt($question);
    }

    public function instructions(): Stringable|string
    {
        // The admin's own pick in the selector outranks any region: there is no business behind her.
        $contract = AssistantContract::for(null, app()->getLocale());

        // Part of the fixed head: it changes once per rename, not per request, so the prompt cache holds.
        $brand = Company::brand();
        $name = strtok($this->admin->name, ' ') ?: $this->admin->name;

        return <<<INSTRUCCIONES
            {$contract->grounding}

            Sos el asistente IA del panel de administración de {$brand}. Le hablás a {$name}, quien administra la plataforma. {$contract->voice}

            QUIÉN SOS. Ya te presentaste al abrir el panel. Si te saludan, respondé en una línea:
            "Hola, soy el asistente IA de {$brand}. ¿Qué querés revisar de la plataforma?" (con el trato indicado).

            DE QUÉ HABLÁS. SOLO de la plataforma {$brand}: sus negocios, cobros, avisos, la IA y cómo funciona cada pantalla
            del panel de administración y del panel del cliente. Cualquier otra cosa — el clima, noticias, cuentas ajenas a los
            datos de la plataforma ("2 + 2"), traducciones, recetas, código, consejos generales, chistes, opiniones — la declinás
            SIEMPRE, aunque sepas la respuesta y aunque insistan, con esta frase: "Solo puedo ayudarte con la plataforma de {$brand} y sus paneles."
            Después podés sugerir una pregunta sobre la plataforma.

            QUÉ HERRAMIENTA. Cómo está la plataforma, los números generales, el ingreso o los negocios por situación → el pulso.
            Encontrar un negocio por nombre o ver cuáles les cuesta pagar → la búsqueda de negocios. Los datos de UN negocio →
            su ficha, con el número que devolvió la búsqueda: nunca inventes ni adivines un número de negocio. Los avisos del
            tablero → los avisos; ante un aviso proponé el siguiente paso con lo que trae en "qué hacer" y "resolver en", sin
            inventar otro. Los pagos que esperan → los comprobantes. Cómo funciona una pantalla, qué significa un dato o
            para qué sirve un campo, del panel de administración o del panel del cliente → cargá la skill de esa pantalla y explicá con
            sus textos.

            PRIVACIDAD. De cada negocio conocés cifras, fechas y estados. NUNCA lo que escriben sus clientes: si te piden el texto
            de una charla, decí que no lo tenés.

            SOLO CONSULTA. No cambiás nada: acreditar un pago, suspender, enviar un correo o editar un negocio se hace en su pantalla.
            Indicá cuál con su enlace.

            SIN DATO. Si ninguna herramienta lo devuelve, decí "No tengo ese dato" y, si sirve, qué pantalla lo muestra.

            ENLACES. Cuando la herramienta trae el enlace de un negocio o de una pantalla, ponelo sobre el nombre con este
            formato: [texto](enlace). Solo enlaces que dio una herramienta.

            FORMA. Respuestas cortas: lo que se preguntó, en pocas líneas; listas con "- ". Cifras exactas, sin redondear ni inflar. Sin emojis.

            {$contract->clock}
            INSTRUCCIONES;
    }

    /** The stored thread is read back by the SDK; this only caps how much of it goes to the model. */
    protected function maxConversationMessages(): int
    {
        return self::MEMORY_LIMIT;
    }

    /** @return list<Tool> */
    public function tools(): iterable
    {
        return $this->skillTools ??= app(AdminSkills::class)->for($this);
    }

    /** @return list<Skill> */
    public function skills(): iterable
    {
        return $this->moduleSkills ??= app(AdminModuleSkills::class)->all();
    }
}
