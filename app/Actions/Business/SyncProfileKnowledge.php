<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\Models\Business;
use App\Models\BusinessClosure;
use App\Models\KnowledgeDocument;

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

        $lines = [...$lines, ...$business->premisesLines(), ...$this->hourLines($business), ...$business->contactLines()];

        return implode("\n", $lines);
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

        if ($lines === []) {
            return [];
        }

        // The days she marked closed ride along: the weekly grid alone makes
        // a holiday look like any other Tuesday.
        $closed = $business->closures()->upcoming()->get()
            ->map(fn (BusinessClosure $closure): string => $closure->label()
                .($closure->isFullDay() ? ': no abre' : ': abre '.$closure->hoursLabel())
                .($closure->reason !== null ? ' ('.$closure->reason.')' : ''))
            ->all();

        return [
            'Horarios de atención:',
            ...$lines,
            ...($closed === [] ? [] : ['Días distintos: '.implode(' · ', $closed)]),
        ];
    }
}
