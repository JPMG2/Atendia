<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Enums\HandoffSignal;
use Illuminate\Support\Str;

/**
 * Reads the two signals that cannot wait for a model: the customer asks for
 * a person, or describes a life-threatening emergency.
 *
 * It errs toward silence: a phrase negated just before it ("no quiero hablar
 * con una persona") or placed in the past never fires, and the model, which
 * still has these rules in its briefing, decides as it always did.
 */
final class HandoffSignals
{
    private const PERSON = '(?:persona|humano|humana|agente|operador|operadora|asesor|asesora|representante|ejecutivo|ejecutiva|ser humano|alguien real|human|person|agent|representative|operator|pessoa|atendente)';

    /** Words that cancel a phrase when they sit in its own clause, right before it. */
    private const NEGATORS = ['no', 'ni', 'sin', 'nunca', 'jamas', 'tampoco', 'not', 'never', 'without', 'dont', 'nao', 'sem'];

    /** A reference to the past inside the clause turns an emergency into a story. */
    private const PAST = '/\b(?:el ano pasado|el mes pasado|hace \d+ (?:anos|meses)|cuando era|when i was|years ago)\b/';

    public static function detect(string $text): ?HandoffSignal
    {
        $clauses = self::clauses($text);

        foreach ($clauses as $clause) {
            if (self::matches($clause, self::emergencyPatterns(), pastGuard: true)) {
                return HandoffSignal::Emergency;
            }
        }

        foreach ($clauses as $clause) {
            if (self::matches($clause, self::humanRequestPatterns())) {
                return HandoffSignal::HumanRequested;
            }
        }

        return null;
    }

    /**
     * Lowercase, accents and punctuation gone, split at the marks that end a
     * thought: a negation never reaches across a comma or a question.
     *
     * @return list<string>
     */
    private static function clauses(string $text): array
    {
        $plain = Str::lower(Str::ascii($text));
        $plain = str_replace(["'", '’'], '', $plain);

        return collect(preg_split('/[.,;:!?¡¿\n]+/', $plain) ?: [])
            ->map(fn (string $clause): string => trim((string) preg_replace('/[^a-z0-9 ]+/', ' ', $clause)))
            ->map(fn (string $clause): string => trim((string) preg_replace('/\s+/', ' ', $clause)))
            ->filter()
            ->values()
            ->all();
    }

    /** @param  list<string>  $patterns */
    private static function matches(string $clause, array $patterns, bool $pastGuard = false): bool
    {
        if ($pastGuard && preg_match(self::PAST, $clause) === 1) {
            return false;
        }

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $clause, $found, PREG_OFFSET_CAPTURE) === 0) {
                continue;
            }

            foreach ($found[0] as [$phrase, $offset]) {
                if (! self::negatedBefore($clause, (int) $offset)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** The two words before a phrase: a negator there cancels it. */
    private static function negatedBefore(string $clause, int $offset): bool
    {
        $before = preg_split('/ /', trim(substr($clause, 0, $offset))) ?: [];

        return array_intersect(array_slice($before, -2), self::NEGATORS) !== [];
    }

    /** @return list<string> */
    private static function humanRequestPatterns(): array
    {
        $person = self::PERSON;

        return [
            // "quiero hablar con una persona", "pasame con un asesor", "talk to a human"
            "/\\b(?:hablar|comunicar(?:me)?|comunicarme|pasar(?:me)?|pasame|pasenme|conectar(?:me)?|conectame|derivar(?:me)?|derivame|transferir(?:me)?|contactar(?:me)?|talk|speak|chat|connect|transfer|falar|conversar)\\b(?: \\w+){0,3} (?:con|a|to|with|com) (?:un|una|el|la|a|an|the|um|uma)?(?: real)? ?{$person}\\b/",
            "/\\bque me (?:atienda|conteste|responda|llame) (?:un|una|el|la)? ?{$person}\\b/",
            '/\\b(?:quiero|necesito|prefiero|want|need) (?:a |an |un |una )?(?:real )?(?:persona real|humano|human|real person|agente real)\\b/',
            // "no quiero hablar con un bot": the only negation that IS the request
            '/\bno quiero (?:hablar|seguir|chatear) con (?:un |una )?(?:bot|robot|chatbot|maquina|ia|inteligencia artificial|asistente virtual)\b/',
        ];
    }

    /** @return list<string> */
    private static function emergencyPatterns(): array
    {
        return [
            '/\bdolor (?:muy |bien |super )?(?:fuerte |intenso )?(?:en|de)l? (?:el )?pecho\b/',
            '/\bchest pain\b/',
            '/\bno (?:puedo|logro|consigo) respirar\b/',
            '/\bme falta (?:el )?(?:aire|aliento)\b/',
            '/\b(?:cant|cannot|can not) breathe\b/',
            '/\b(?:infarto|ataque (?:al corazon|cardiaco)|heart attack|derrame cerebral|convulsion(?:es)?|sobredosis|overdose|envenenamiento|intoxicacion|hemorragia)\b/',
            '/\b(?:esta|estoy|quedo|se) (?:desmayad[oa]|inconsciente|desmayo)\b/',
            '/\bsangrado (?:abundante|fuerte|que no para)\b/',
            '/\bme (?:estoy )?(?:muriendo|desangrando|ahogando)\b/',
            '/\b(?:me quiero|quiero) (?:matar|morir|suicidar)(?:me)?\b/',
            '/\b(?:suicidio|suicidarme|quitarme la vida|terminar con mi vida)\b/',
            '/\bnecesito (?:una )?ambulancia\b/',
        ];
    }
}
