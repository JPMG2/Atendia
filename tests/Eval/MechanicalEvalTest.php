<?php

declare(strict_types=1);

use App\Ai\Agents\ActivityIntentDesigner;
use App\Ai\Agents\BusinessBioWriter;
use App\Ai\Agents\CatalogCopywriter;
use App\Ai\Agents\DigestWriter;
use App\Ai\Agents\FaqDrafter;
use App\Ai\Agents\ProductColumnMapper;
use App\Ai\Agents\ProductNameFixer;
use App\Ai\Agents\QuestionMatcher;
use App\Ai\Agents\ReplyTranslator;
use App\Classes\Main\AiEvalResults;
use Database\Seeders\AiModelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\AiEval\EvalRun;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The mechanical battery: the nine jobs nobody reads in the moment
|--------------------------------------------------------------------------
| The conversation battery measures what a customer reads. These nine are the
| ones a cheaper model could take over, and moving them without measuring is a
| guess. Every check is objective (length, format, no invented figure, the
| right id) and none is judged by another model: a judge that shares the
| criteria of what it grades measures nothing.
|
| On demand (it spends tokens): ./vendor/bin/pest tests/Eval/MechanicalEvalTest.php
| To measure a candidate: EVAL_CONNECTION=openai-app EVAL_MODEL=<code> before it.
*/

beforeEach(function (): void {
    Http::allowStrayRequests(['https://*']);
    $this->seed(AiModelSeeder::class);

    EvalRun::target(
        ['QuestionMatcher', 'ReplyTranslator', 'ProductNameFixer', 'ProductColumnMapper', 'CatalogCopywriter', 'BusinessBioWriter', 'FaqDrafter', 'DigestWriter', 'ActivityIntentDesigner'],
        env('EVAL_CONNECTION'),
        env('EVAL_MODEL'),
        FaqDrafter::class,
    );
});

test('a mechanical task does its small job without inventing', function (string $agent, string $prompt, Closure $check): void {
    $response = (new $agent)->prompt($prompt, timeout: 90);
    $failed = collect($check($response))->first(fn (array $check): bool => ! $check[0]);

    EvalRun::finish(AiEvalResults::MECHANICAL, $failed === null, FaqDrafter::class);

    expect($failed)->toBeNull($failed === null ? '' : "{$agent} failed: {$failed[1]}\nAnswer: ".mechAnswer($response));
})->with('mechanical cases');

/** What the agent said, flat, for a failure message. */
function mechAnswer(mixed $response): string
{
    return is_object($response) && isset($response->text) && $response->text !== ''
        ? (string) $response->text
        : json_encode($response->structured ?? $response, JSON_UNESCAPED_UNICODE);
}

function mechHasEmoji(string $text): bool
{
    return preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $text) === 1;
}

/** @return list<string> Every run of digits in a text, to tell an invented figure from a copied one. */
function mechNumbers(string $text): array
{
    preg_match_all('/\d+/', $text, $found);

    return $found[0];
}

dataset('mechanical cases', [
    'matcher: paraphrase vs different question' => [QuestionMatcher::class,
        "Preguntas nuevas:\n1. ¿Aceptan tarjeta?\n2. ¿Puedo pagar con tarjeta?\n3. ¿Abren los sábados?\n4. ¿Abren los domingos?\n\nSugerencias existentes:\n(ninguna)",
        function ($r): array {
            $m = collect($r['matches'])->keyBy('question');

            return [
                [(int) ($m[2]['same_as'] ?? -1) === 1, 'question 2 is the same as 1'],
                [(int) ($m[4]['same_as'] ?? -1) !== 3, 'Saturday and Sunday are NOT the same question'],
            ];
        }],
    'matcher: existing suggestion' => [QuestionMatcher::class,
        "Preguntas nuevas:\n1. ¿Llevan a domicilio?\n2. ¿Cuánto cuesta el envío?\n\nSugerencias existentes:\n[7] ¿Hacen envíos a domicilio?",
        function ($r): array {
            $m = collect($r['matches'])->keyBy('question');

            return [
                [(int) ($m[1]['suggestion'] ?? -1) === 7, 'question 1 matches suggestion 7'],
                [(int) ($m[2]['suggestion'] ?? -1) === 0, 'the price of shipping is another question'],
            ];
        }],
    'matcher: near miss' => [QuestionMatcher::class,
        "Preguntas nuevas:\n1. ¿Abren los domingos?\n\nSugerencias existentes:\n[3] ¿Abren los sábados?",
        fn ($r): array => [[(int) (collect($r['matches'])->keyBy('question')[1]['suggestion'] ?? -1) === 0, 'Sunday is not Saturday']]],

    'names: one typo among good names' => [ProductNameFixer::class,
        'Review these product names: ["Eco dobler","Taladro percutor Bosch","Martillo de carpintero"]',
        function ($r): array {
            $fixed = collect($r['corrections'])->pluck('fixed', 'original');

            return [
                [str_contains(mb_strtolower((string) ($fixed['Eco dobler'] ?? '')), 'doppler'), 'Eco dobler becomes Eco doppler'],
                [! $fixed->has('Taladro percutor Bosch') && ! $fixed->has('Martillo de carpintero'), 'correct names are left alone'],
            ];
        }],
    'names: a typo in a drug' => [ProductNameFixer::class,
        'Review these product names: ["Amoxicilna 500 mg","Ibuprofeno 400 mg"]',
        function ($r): array {
            $fixed = collect($r['corrections'])->pluck('fixed', 'original');

            return [
                [str_contains(mb_strtolower((string) ($fixed['Amoxicilna 500 mg'] ?? '')), 'amoxicilina'), 'Amoxicilna becomes Amoxicilina'],
                [! $fixed->has('Ibuprofeno 400 mg'), 'Ibuprofeno is already right'],
            ];
        }],
    'names: nothing to fix' => [ProductNameFixer::class,
        'Review these product names: ["Cemento Portland x 50 kg","Pintura látex blanca 20 L"]',
        fn ($r): array => [[count($r['corrections']) === 0, 'no correction when nothing is misspelled']]],

    'columns: price, stock and code' => [ProductColumnMapper::class,
        'Classify these spreadsheet columns: [{"column":"Prescio","samples":["1500","2300","990"]},{"column":"Cant.","samples":["12","0","40"]},{"column":"Cod. interno","samples":["A-100","A-101","B-7"]}]',
        function ($r): array {
            $m = collect($r['mappings'])->keyBy('column');

            return [
                [($m['Prescio']['target'] ?? '') === 'price', 'Prescio is the price'],
                [mb_strtolower((string) ($m['Prescio']['label'] ?? '')) === 'precio', 'its label is corrected to Precio'],
                [($m['Cant.']['target'] ?? '') === 'stock', 'Cant. is the stock'],
                [($m['Cod. interno']['target'] ?? '') === 'code', 'Cod. interno is the code'],
            ];
        }],
    'columns: brand is extra, detail is description' => [ProductColumnMapper::class,
        'Classify these spreadsheet columns: [{"column":"Marca","samples":["Bosch","Stanley","Makita"]},{"column":"Detalle","samples":["Taladro percutor de 13 mm, 650 W","Martillo carpintero mango fibra","Amoladora angular 115 mm"]}]',
        function ($r): array {
            $m = collect($r['mappings'])->keyBy('column');

            return [
                [($m['Marca']['target'] ?? '') === 'extra', 'Marca is extra'],
                [($m['Detalle']['target'] ?? '') === 'description', 'Detalle is the description'],
            ];
        }],
    'columns: the name' => [ProductColumnMapper::class,
        'Classify these spreadsheet columns: [{"column":"Producto","samples":["Cemento","Arena","Ladrillo"]}]',
        fn ($r): array => [[(collect($r['mappings'])->keyBy('column')['Producto']['target'] ?? '') === 'name', 'Producto is the name']]],

    'translator: schedule and price survive' => [ReplyTranslator::class,
        "Idioma del cliente: inglés\n\nRespuesta del equipo (en español):\nSí, abrimos mañana de 07:00 a 11:00. La glicemia cuesta \$5.",
        fn ($r): array => [
            // English writes 7:00, not 07:00: the figure is what must survive, not its padding.
            [preg_match('/\b0?7:00\b/', $r['text']) === 1 && str_contains($r['text'], '11:00') && str_contains($r['text'], '$5'), 'hours and price are kept'],
            [preg_match('/\b(abrimos|mañana|cuesta|sí)\b/iu', $r['text']) === 0, 'it is English, not Spanish'],
        ]],
    'translator: an order number survives' => [ReplyTranslator::class,
        "Idioma del cliente: inglés\n\nRespuesta del equipo (en español):\nGracias por avisarnos. Tu pedido #4521 sale hoy y llega mañana.",
        fn ($r): array => [
            [str_contains($r['text'], '4521'), 'the order number is kept'],
            [preg_match('/\b(gracias|avisarnos|llega|mañana)\b/iu', $r['text']) === 0, 'it is English, not Spanish'],
        ]],
    'translator: translates only' => [ReplyTranslator::class,
        "Idioma del cliente: inglés\n\nRespuesta del equipo (en español):\nNo tenemos stock de martillos por ahora.",
        fn ($r): array => [
            [preg_match('/hammer/i', $r['text']) === 1, 'it says hammers'],
            [mb_strlen($r['text']) < 120 && ! str_contains($r['text'], '?'), 'it adds and asks nothing of its own'],
        ]],

    'copy: laboratory' => [CatalogCopywriter::class,
        "Negocio: Laboratorio Vida (rubro: Laboratorio clínico)\n\nNombres:\n- Hematología completa\n- Perfil lipídico\n- Glicemia",
        fn ($r): array => mechCopyChecks($r['items'], ['Hematología completa', 'Perfil lipídico', 'Glicemia'])],
    'copy: salon' => [CatalogCopywriter::class,
        "Negocio: Peluquería Lola (rubro: Peluquería)\n\nNombres:\n- Corte de mujer\n- Coloración\n- Alisado",
        fn ($r): array => mechCopyChecks($r['items'], ['Corte de mujer', 'Coloración', 'Alisado'])],
    'copy: hardware store' => [CatalogCopywriter::class,
        "Negocio: Ferretería El Tornillo (rubro: Ferretería)\n\nNombres:\n- Taladro percutor\n- Caja de tornillos x100",
        fn ($r): array => mechCopyChecks($r['items'], ['Taladro percutor', 'Caja de tornillos x100'])],

    'bio: laboratory' => [BusinessBioWriter::class,
        "Negocio: Laboratorio Vida\nRubro: Laboratorio clínico\nOfrece: Hematología completa, Perfil lipídico, Glicemia",
        fn ($r): array => mechBioChecks($r['description'], 'Laboratorio Vida Laboratorio clínico Hematología completa Perfil lipídico Glicemia')],
    'bio: salon' => [BusinessBioWriter::class,
        "Negocio: Peluquería Lola\nRubro: Peluquería\nOfrece: Corte de mujer, Coloración, Alisado",
        fn ($r): array => mechBioChecks($r['description'], 'Peluquería Lola Peluquería Corte de mujer Coloración Alisado')],
    'bio: only a name and a trade' => [BusinessBioWriter::class,
        "Negocio: Ferretería El Tornillo\nRubro: Ferretería",
        fn ($r): array => mechBioChecks($r['description'], 'Ferretería El Tornillo Ferretería')],

    'faq: answered by the fragments' => [FaqDrafter::class,
        "Pregunta del cliente: ¿Hasta qué hora atienden los sábados?\n\nFragmentos del conocimiento del negocio:\nAtendemos los sábados de 07:00 a 11:00.",
        // The question is "until when": the closing hour is the answer, the opening is a bonus.
        fn ($r): array => [[str_contains($r['answer'], '11:00'), 'the answer carries the closing hour']]],
    'faq: the fragments do not answer' => [FaqDrafter::class,
        "Pregunta del cliente: ¿Tienen descuento para jubilados?\n\nFragmentos del conocimiento del negocio:\nAceptamos efectivo, débito y transferencia.",
        fn ($r): array => [[trim($r['answer']) === '', 'no answer when the fragments are silent']]],
    'faq: a price nobody gave' => [FaqDrafter::class,
        "Pregunta del cliente: ¿Cuánto sale la resonancia magnética?\n\nFragmentos del conocimiento del negocio:\nHematología completa \$15. Glicemia \$5.",
        fn ($r): array => [[trim($r['answer']) === '', 'it does not invent the price']]],

    'digest: three conversations' => [DigestWriter::class,
        "Cliente 5491111: ¿Cuánto cuesta la hematología? → \$15\nCliente 5492222: ¿Atienden el sábado? → Sí, de 07:00 a 11:00\nCliente 5493333: Quiero hacer un reclamo por un cobro doble → Derivado al equipo",
        fn ($r): array => mechDigestChecks((string) $r->text)],
    'digest: five conversations' => [DigestWriter::class,
        "Cliente 1: ¿Tienen turno el martes? → Sí, a las 10:00\nCliente 2: ¿Cuánto sale el corte? → \$9.000\nCliente 3: ¿Aceptan tarjeta? → Sí\nCliente 4: Mi turno quedó mal anotado → Derivado al equipo\nCliente 5: ¿Dónde quedan? → Av. Bolívar 123",
        fn ($r): array => mechDigestChecks((string) $r->text)],

    'intents: laboratory' => [ActivityIntentDesigner::class,
        "Rubro: Laboratorio clínico (sector Salud)\n\nIntenciones universales:\n- Precios: cuánto cuesta algo\n- Horarios: cuándo abren\n- Ubicación: dónde quedan\n- Turnos: sacar o cambiar una cita",
        fn ($r): array => mechIntentChecks($r['intents'], ['Precios', 'Horarios', 'Ubicación', 'Turnos'], ['hematolog', 'glicemia'])],
    'intents: salon' => [ActivityIntentDesigner::class,
        "Rubro: Peluquería (sector Belleza)\n\nIntenciones universales:\n- Precios: cuánto cuesta algo\n- Horarios: cuándo abren\n- Ubicación: dónde quedan\n- Turnos: sacar o cambiar una cita",
        fn ($r): array => mechIntentChecks($r['intents'], ['Precios', 'Horarios', 'Ubicación', 'Turnos'], ['corte de mujer', 'alisado'])],
]);

/**
 * @param  list<array{name: string, description: string}>  $items
 * @param  list<string>  $names
 * @return list<array{0: bool, 1: string}>
 */
function mechCopyChecks(array $items, array $names): array
{
    $items = collect($items);

    return [
        [$items->count() === count($names) && $items->pluck('name')->sort()->values()->all() === collect($names)->sort()->values()->all(), 'one description per name, the name kept exactly'],
        [$items->every(fn (array $item): bool => $item['description'] !== '' && mb_strlen($item['description']) <= 200), 'each one under 200 characters'],
        [$items->every(fn (array $item): bool => ! mechHasEmoji($item['description']) && ! str_contains($item['description'], '$')), 'no emoji and no price'],
        [$items->every(fn (array $item): bool => preg_match('/\b\d+\s*(minutos|min|horas|días|años|meses)\b|garant/iu', $item['description']) === 0), 'no invented duration or guarantee'],
    ];
}

/** @return list<array{0: bool, 1: string}> */
function mechBioChecks(string $bio, string $given): array
{
    $sentences = preg_match_all('/[.!?](\s|$)/u', $bio);

    return [
        [$bio !== '' && mb_strlen($bio) <= 320, 'under 320 characters'],
        [! mechHasEmoji($bio) && ! str_contains($bio, '$'), 'no emoji and no price'],
        [array_diff(mechNumbers($bio), mechNumbers($given)) === [], 'no figure that was not given'],
        [$sentences >= 2 && $sentences <= 3, 'two or three sentences'],
    ];
}

/** @return list<array{0: bool, 1: string}> */
function mechDigestChecks(string $digest): array
{
    $lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', $digest)), fn (string $line): bool => $line !== ''));

    return [
        [count($lines) >= 3 && count($lines) <= 5, 'three to five bullets'],
        [collect($lines)->every(fn (string $line): bool => str_starts_with($line, '•')), 'every line is a bullet, no preamble or farewell'],
        [! mechHasEmoji($digest), 'no emoji'],
    ];
}

/**
 * @param  list<array{name: string, description: string}>  $intents
 * @param  list<string>  $universal
 * @param  list<string>  $specific  Services that are not reasons to ask.
 * @return list<array{0: bool, 1: string}>
 */
function mechIntentChecks(array $intents, array $universal, array $specific): array
{
    $intents = collect($intents);

    return [
        [$intents->count() <= 8, 'at most eight'],
        [$intents->every(fn (array $intent): bool => str_word_count($intent['name'], 0, 'áéíóúñÁÉÍÓÚÑ') >= 2 && str_word_count($intent['name'], 0, 'áéíóúñÁÉÍÓÚÑ') <= 5 && trim($intent['description']) !== ''), 'a name of two to five words and a description'],
        [$intents->every(fn (array $intent): bool => ! in_array(mb_strtolower($intent['name']), array_map('mb_strtolower', $universal), true)), 'never a universal one again'],
        [$intents->every(fn (array $intent): bool => collect($specific)->every(fn (string $service): bool => ! str_contains(mb_strtolower($intent['name']), $service))), 'a reason to ask, not a service'],
    ];
}
