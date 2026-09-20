<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\Models\Business;
use App\Models\KnowledgeDocument;
use App\Models\SocialLink;

/**
 * Publishes the business's own card — hours, address, contact, networks —
 * into its knowledge base. Born from a live miss: asked "¿qué ofrecen
 * hoy?", the assistant knew the offer but nothing about "today", because
 * the profile never reached its memory. One document, replaced on change.
 */
class SyncProfileKnowledge
{
    private const string TITLE = 'Información del negocio';

    public function handle(Business $business): void
    {
        KnowledgeDocument::query()->updateOrCreate(
            ['business_id' => $business->id, 'source_type' => 'profile', 'title' => self::TITLE],
            ['content' => $this->content($business)],
        );
    }

    private function content(Business $business): string
    {
        $lines = ['Negocio: '.$business->name];

        if ($business->description !== null) {
            $lines[] = 'Descripción: '.$business->description;
        }

        if (($activity = $business->primaryActivity()) !== null) {
            $lines[] = 'Rubro: '.$activity->name;
        }

        $lines = [...$lines, ...$this->premisesLines($business), ...$this->hourLines($business), ...$this->contactLines($business)];

        return implode("\n", $lines);
    }

    /** @return list<string> */
    private function premisesLines(Business $business): array
    {
        $lines = [];

        if ($business->has_premises === false) {
            $lines[] = 'Atención: sin local, se atiende a distancia o a domicilio.';
        }

        $address = implode(', ', array_filter([$business->address, $business->city]));

        if ($address !== '') {
            $lines[] = 'Dirección: '.$address;
        }

        return $lines;
    }

    /**
     * One line per day with hours, shift by shift: "Lunes: 09:00 a 13:00 y
     * 17:00 a 20:00". Days with no row are simply not offered.
     *
     * @return list<string>
     */
    private function hourLines(Business $business): array
    {
        $lines = $business->scheduleLines();

        return $lines === [] ? [] : ['Horarios de atención:', ...$lines];
    }

    /** @return list<string> */
    private function contactLines(Business $business): array
    {
        $lines = array_values(array_filter([
            $business->whatsapp_number !== null ? 'WhatsApp: '.$business->whatsapp_number : null,
            $business->email !== null ? 'Correo: '.$business->email : null,
            $business->web !== null ? 'Sitio web: '.$business->web : null,
        ]));

        $networks = $business->socialLinks()
            ->with('socialNetwork')
            ->get()
            ->map(fn (SocialLink $link): string => ($link->socialNetwork?->name ?? 'Red').': '.$link->url)
            ->all();

        return [...$lines, ...$networks];
    }
}
