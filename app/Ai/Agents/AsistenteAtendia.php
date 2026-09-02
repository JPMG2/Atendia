<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Tools\SearchBusinessKnowledge;
use App\Models\Business;
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

#[Provider(Lab::OpenAI)]
#[Model('gpt-4.1')]
class AsistenteAtendia implements Agent, Conversational, HasTools
{
    use Promptable;

    /**
     * Nullable: prompted with no business (AtendIa's own site) the assistant
     * simply carries no knowledge tool, instead of failing to build.
     */
    public function __construct(
        public ?Business $business = null,
    ) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCCIONES'
            Sos el asistente virtual de AtendIa. Atendés a los usuarios en español,
            con un tono cercano, claro y profesional. Respondé de forma concisa y útil.

            Cuando te pregunten si el negocio ofrece, vende o hace algo — un producto,
            un servicio, un precio, una disponibilidad — buscá SIEMPRE primero en la
            base de conocimiento del negocio con la herramienta de búsqueda, y respondé
            solo con lo que devuelva. Si la búsqueda no lo confirma, decí con honestidad
            que no lo pudiste confirmar y ofrecé consultarlo con una persona del equipo.
            Nunca inventes productos, precios ni datos que la búsqueda no respalde.

            Si no sabés algo o excede lo que podés resolver, decilo con honestidad y
            ofrecé derivar con una persona del equipo.
            INSTRUCCIONES;
    }

    /**
     * Get the list of messages comprising the conversation so far.
     *
     * @return Message[]
     */
    public function messages(): iterable
    {
        return [];
    }

    /**
     * Get the tools available to the agent.
     *
     * The search tool is pinned to THIS business at build time — the model
     * never picks the tenant, so a crafted prompt cannot read another one.
     *
     * @return Tool[]
     */
    public function tools(): iterable
    {
        if ($this->business === null) {
            return [];
        }

        return [
            new SearchBusinessKnowledge($this->business->id),
        ];
    }
}
