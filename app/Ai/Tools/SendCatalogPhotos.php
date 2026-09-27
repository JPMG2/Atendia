<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Agents\AsistenteAtendia;
use App\Enums\MessageAuthor;
use App\Enums\MessageDirection;
use App\Interfaces\Main\AssistantSkillTool;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Product;
use App\Models\Service;
use App\Services\Catalog\CatalogMatcher;
use App\Services\EvolutionApi;
use App\Services\Tenant;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

/**
 * "Can I see the blue dress?": sends that item's catalog photos to the
 * customer on WhatsApp, capped per reply so a chat never floods. The model
 * gets one line back: what went out, or why nothing did.
 */
class SendCatalogPhotos implements AssistantSkillTool
{
    public function __construct(private readonly Business $business, private readonly Conversation $conversation) {}

    public static function forAssistant(AsistenteAtendia $assistant): ?static
    {
        return $assistant->business !== null && $assistant->conversation !== null
            ? new static($assistant->business, $assistant->conversation)
            : null;
    }

    public function description(): Stringable|string
    {
        return 'Envía por WhatsApp al cliente las fotos de UN producto o servicio del catálogo. Usala cuando '
            .'el cliente pida ver fotos, imágenes o cómo es algo. Pasá en item SOLO el nombre ("vestido azul"). '
            .'No describas las fotos después: el cliente ya las ve.';
    }

    public function handle(Request $request): Stringable|string
    {
        $item = trim((string) ($request['item'] ?? ''));

        if ($item === '') {
            return 'Falta el nombre del producto o servicio.';
        }

        if (! $this->business->canMessageCustomers()) {
            return 'Ahora no se pueden enviar fotos por WhatsApp.';
        }

        return app(Tenant::class)->for($this->business->id, fn (): string => $this->send($item));
    }

    private function send(string $item): string
    {
        $hit = app(CatalogMatcher::class)->matches($item, 3)->first();

        if ($hit === null) {
            return "No hay nada parecido a \"{$item}\" en el catálogo del negocio.";
        }

        /** @var Product|Service $match */
        $match = $hit['model']::query()->findOrFail($hit['id']);
        $photos = $match->photos()->limit((int) config('atendia.catalog.photos_per_reply'))->get();

        if ($photos->isEmpty()) {
            return "{$match->name} no tiene fotos cargadas en el catálogo.";
        }

        $evolution = app(EvolutionApi::class);
        $sent = 0;

        foreach ($photos as $index => $photo) {
            try {
                $evolution->sendImage((string) $this->business->whatsapp_instance, $this->conversation->contact_phone, $photo->contents(), $index === 0 ? $match->name : '');
                $sent++;
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        if ($sent === 0) {
            return 'No se pudieron enviar las fotos: WhatsApp no respondió.';
        }

        // The thread is the owner's inbox and the assistant's memory: both must see what went out.
        $this->conversation->messages()->create([
            'direction' => MessageDirection::Out,
            'author' => MessageAuthor::Assistant,
            'body' => __('catalog_photos.thread_line', ['count' => $sent, 'name' => $match->name]),
        ]);

        return "Envié {$sent} foto(s) de {$match->name}.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'item' => $schema->string()->description('Nombre del producto o servicio del que el cliente quiere fotos.')->required(),
        ];
    }
}
