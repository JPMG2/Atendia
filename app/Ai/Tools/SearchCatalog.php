<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Actions\Business\SyncOfferKnowledge;
use App\Ai\Agents\AsistenteAtendia;
use App\Interfaces\Main\AssistantSkillTool;
use App\Models\Business;
use App\Models\Product;
use App\Models\Service;
use App\Services\Knowledge\KnowledgeEmbedder;
use App\Services\Tenant;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The catalog by meaning: "el tiroideo" finds "Perfil tiroideo" with its
 * price, duration and preparation — a few lines where the knowledge search
 * sent whole chunks. Empty item lists what the business offers.
 */
class SearchCatalog implements AssistantSkillTool
{
    private const int MATCHES = 3;

    private const int LISTED = 30;

    public function __construct(private readonly Business $business) {}

    public static function forAssistant(AsistenteAtendia $assistant): ?static
    {
        return $assistant->business !== null ? new static($assistant->business) : null;
    }

    public function description(): Stringable|string
    {
        return 'Busca en el catálogo del negocio un servicio o producto por su nombre y '
            .'devuelve precio, duración, preparación, stock y detalles. Pasá en item SOLO '
            .'el nombre de lo que busca el cliente ("perfil tiroideo", "corte de pelo"). '
            .'Con item vacío devuelve la lista de lo que ofrece el negocio.';
    }

    public function handle(Request $request): Stringable|string
    {
        $item = trim((string) ($request['item'] ?? ''));

        return app(Tenant::class)->for($this->business->id, fn (): string => $item === '' ? $this->offer() : $this->search($item));
    }

    private function search(string $item): string
    {
        $vector = app(KnowledgeEmbedder::class)->embedOne($item);
        $floor = (float) config('atendia.assistant.catalog_search_similarity');
        $describer = app(SyncOfferKnowledge::class);

        $matches = collect([
            ...array_map(fn (array $hit): array => [...$hit, 'model' => Service::class], Service::closestMany($vector, self::MATCHES)),
            ...array_map(fn (array $hit): array => [...$hit, 'model' => Product::class], Product::closestMany($vector, self::MATCHES)),
        ])
            ->filter(fn (array $hit): bool => $hit['similarity'] >= $floor)
            ->sortByDesc('similarity')
            ->take(self::MATCHES);

        if ($matches->isEmpty()) {
            return "No hay nada parecido a \"{$item}\" en el catálogo del negocio.";
        }

        return $matches
            ->map(fn (array $hit): string => $hit['model'] === Service::class
                ? $describer->describeService(Service::query()->with('category')->findOrFail($hit['id']))
                : $describer->describeProduct(Product::query()->findOrFail($hit['id'])))
            ->implode("\n");
    }

    private function offer(): string
    {
        $services = Service::query()->where('is_active', true)->orderBy('name')->limit(self::LISTED)->pluck('name');
        $products = Product::query()->where('is_active', true)->orderBy('name')->limit(self::LISTED)->pluck('name');

        if ($services->isEmpty() && $products->isEmpty()) {
            return 'El negocio no cargó servicios ni productos.';
        }

        return collect([
            $services->isNotEmpty() ? 'Servicios: '.$services->implode(', ') : null,
            $products->isNotEmpty() ? 'Productos: '.$products->implode(', ') : null,
        ])->filter()->implode("\n");
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'item' => $schema->string()->required(),
        ];
    }
}
