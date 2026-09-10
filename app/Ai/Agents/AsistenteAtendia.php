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
use Laravel\Ai\Responses\AgentResponse;
use Stringable;

#[Provider(Lab::OpenAI)]
#[Model('gpt-6-astra')]
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
     * Answer a customer question, guaranteeing the knowledge search ran.
     *
     * The model sometimes skips the tool on the first pass (seen live on
     * 2026-09-05): when it answers a business question without searching,
     * one firm re-ask grounds the reply instead of trusting improvisation.
     */
    public function answer(string $question): AgentResponse
    {
        $response = $this->prompt($question);

        if ($this->business === null || $response->toolCalls->isNotEmpty()) {
            return $response;
        }

        return $this->prompt(
            'Recordatorio del sistema: antes de responder, usá la herramienta de búsqueda '
            .'en la base de conocimiento del negocio si la consulta puede referirse a algo '
            .'que el negocio ofrece (productos, servicios, precios, disponibilidad). '
            ."Si la consulta no habla del negocio, respondé normalmente.\n\n"
            .'Consulta del cliente: '.$question,
        );
    }

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCCIONES'
            Sos el asistente virtual de AtendIa. Tu idioma base es el español, con un
            tono cercano, claro y profesional. Respondé de forma concisa y útil.

            Respondé SIEMPRE en el idioma en que te escribe el cliente: si te escriben
            en inglés, portugués o cualquier otro idioma, contestá en ese mismo idioma.
            Si el idioma del mensaje no es claro, respondé en español.

            Cuando te pregunten si el negocio ofrece, vende o hace algo — un producto,
            un servicio, un precio, una disponibilidad — buscá SIEMPRE primero en la
            base de conocimiento del negocio con la herramienta de búsqueda, y respondé
            solo con lo que devuelva. Si la búsqueda no lo confirma, decí con honestidad
            que no lo pudiste confirmar y ofrecé consultarlo con una persona del equipo.
            Nunca inventes productos, precios ni datos que la búsqueda no respalde.

            Si no sabés algo o excede lo que podés resolver, decilo con honestidad y
            ofrecé derivar con una persona del equipo. Todo resumen o mensaje de
            derivación dirigido al equipo del negocio va SIEMPRE en español, sin
            importar el idioma del cliente: el equipo atiende en español.
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
