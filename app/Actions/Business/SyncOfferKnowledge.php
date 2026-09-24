<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\Jobs\EmbedCatalog;
use App\Models\Business;
use App\Models\KnowledgeDocument;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceType;

/**
 * Publishes the tenant's own offer into its knowledge base, so the
 * assistant answers hand-typed services and products — attribute values
 * included — the same way it answers an imported sheet. One document per
 * side, replaced on change; the observer's content hash keeps re-indexing
 * down to real edits.
 */
class SyncOfferKnowledge
{
    private const string SERVICES_TITLE = 'Servicios del negocio';

    private const string PRODUCTS_TITLE = 'Productos del negocio';

    public function handle(Business $business, string $side): void
    {
        // Every catalog change passes here, so the name vectors follow it too.
        EmbedCatalog::dispatch((int) $business->id);

        [$sourceType, $title, $content] = $side === 'products'
            ? ['products', self::PRODUCTS_TITLE, $this->productsContent($business)]
            : ['services', self::SERVICES_TITLE, $this->servicesContent($business)];

        if ($content === '') {
            // An emptied offer must leave the assistant's memory too.
            $business->knowledgeDocuments()->where('source_type', $sourceType)->delete();

            return;
        }

        KnowledgeDocument::query()->updateOrCreate(
            ['business_id' => $business->id, 'source_type' => $sourceType, 'title' => $title],
            ['content' => $content],
        );
    }

    /** Paused services are not offered: they stay out of the answers. */
    private function servicesContent(Business $business): string
    {
        return $business->services()
            ->where('is_active', true)
            ->with(['category', 'serviceType'])
            ->orderBy('id')
            ->get()
            ->map(fn (Service $service): string => $this->describeService($service))
            ->implode("\n");
    }

    /** Out of stock stays LISTED — the assistant says so instead of denying it. */
    private function productsContent(Business $business): string
    {
        return $business->products()
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->map(fn (Product $product): string => $this->describeProduct($product))
            ->implode("\n");
    }

    /** One service as the assistant reads it, price rules and curated fields included. */
    public function describeService(Service $service): string
    {
        $parts = ['Servicio: '.$service->name];

        if ($service->category !== null) {
            $parts[] = 'Categoría: '.$service->category->name;
        }

        $parts[] = match (true) {
            $service->price_type === 'free' => 'Precio: gratis',
            $service->price_type === 'talk' => 'Precio: a convenir',
            $service->price !== null && $service->price_type === 'from' => 'Precio: desde $ '.$this->amount($service->price),
            $service->price !== null => 'Precio: $ '.$this->amount($service->price),
            default => 'Precio: consultar',
        };

        if ($service->deposit !== null) {
            $parts[] = 'Adelanto para reservar: $ '.$this->amount($service->deposit);
        }

        if ($service->duration_minutes !== null) {
            $parts[] = 'Duración: '.$service->duration_minutes.' minutos';
        }

        if ($service->description !== null) {
            $parts[] = 'Descripción: '.$service->description;
        }

        if ($service->prep_note !== null) {
            $parts[] = 'Preparación previa: '.$service->prep_note;
        }

        return implode(' · ', [...$parts, ...$this->attributeParts($service->service_type_id, $service->attribute_values ?? [])]);
    }

    /** One product as the assistant reads it; out of stock says so instead of vanishing. */
    public function describeProduct(Product $product): string
    {
        $parts = ['Producto: '.$product->name];

        if ($product->code !== null) {
            $parts[] = 'Código: '.$product->code;
        }

        if ($product->price !== null) {
            $parts[] = 'Precio: $ '.$this->amount($product->price);
        }

        if ($product->stock !== null) {
            $parts[] = 'Cantidad disponible: '.$this->amount($product->stock);
        }

        if (! $product->in_stock) {
            $parts[] = 'Disponibilidad: sin stock por ahora';
        }

        if ($product->description !== null) {
            $parts[] = 'Descripción: '.$product->description;
        }

        return implode(' · ', [...$parts, ...$this->attributeParts($product->service_type_id, $product->attribute_values ?? [])]);
    }

    /**
     * The curated fields, told with THIS type's labels: "Comensales: 4",
     * "Requiere ayuno: sí". Keyed lookups only — no value, no line.
     *
     * @param  array<int|string, mixed>  $values
     * @return list<string>
     */
    private function attributeParts(?int $typeId, array $values): array
    {
        if ($typeId === null || $values === []) {
            return [];
        }

        $parts = [];

        foreach (ServiceType::attributeSetFor($typeId) as $attribute) {
            $value = $values[$attribute['id']] ?? $values[(string) $attribute['id']] ?? null;

            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            $formatted = match (true) {
                is_bool($value) => $value ? 'sí' : 'no',
                is_array($value) => implode(', ', $value),
                default => (string) $value.($attribute['unit'] !== null ? ' '.$attribute['unit'] : ''),
            };

            $parts[] = $attribute['label'].': '.$formatted;
        }

        return $parts;
    }

    /** The decimal cast prints "16500.00"; nobody says the zeros out loud. */
    private function amount(string $value): string
    {
        return rtrim(rtrim($value, '0'), '.');
    }
}
