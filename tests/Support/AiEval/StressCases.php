<?php

declare(strict_types=1);

namespace Tests\Support\AiEval;

use App\Jobs\EmbedCatalog;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\KnowledgeDocument;
use App\Models\Product;
use App\Services\Knowledge\KnowledgeEmbedder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Laravel\Ai\Files\Image;
use Laravel\Ai\Responses\AgentResponse;

/**
 * The stress battery: the same hardware store as the main battery, loaded
 * with the information a real month piles up (a long thread, a big catalog,
 * a long policy, a wall of text). Every answer is known because it was
 * seeded, so each check is plain PHP: no judge, which is what made the
 * earlier batteries expensive.
 */
final class StressCases
{
    /** The owner's ceiling for the whole run, in USD. */
    public const float CAP_USD = 0.50;

    public const string REPORT = 'logs/ai-stress.md';

    /** @var list<string> */
    public const array CASES = ['thread', 'catalog', 'document', 'three', 'wall', 'stale', 'photo'];

    private static float $spent = 0.0;

    /**
     * @return array{0: Business, 1: Conversation, 2: string} business, thread, what the customer writes now
     */
    public static function seed(string $case): array
    {
        $business = EvalBusinesses::build('hardware');
        $conversation = Conversation::factory()->create(['business_id' => $business->id]);

        $question = match ($case) {
            'thread' => self::seedThread($business, $conversation),
            'catalog' => self::seedCatalog($business),
            'document' => self::seedDocument($business),
            'three' => 'Hola, ¿a qué hora cierran los sábados? ¿Cuánto sale el taladro Bosch? ¿Y dónde están ubicados?',
            'wall' => self::wall(),
            'stale' => self::seedStale($business, $conversation),
            'photo' => "📷 Foto (adjunto)\n(El cliente mandó solo la foto, sin texto.)",
        };

        return [$business, $conversation, $question];
    }

    /**
     * What the customer attaches: only the photo case sends anything, a box with
     * its name printed on it, the way customers photograph a shelf.
     *
     * @return list<Image>
     */
    public static function attachments(string $case): array
    {
        if ($case !== 'photo') {
            return [];
        }

        $label = imagecreatetruecolor(260, 60);
        imagefill($label, 0, 0, (int) imagecolorallocate($label, 0, 70, 160));
        imagestring($label, 5, 16, 22, 'TALADRO PERCUTOR BOSCH', (int) imagecolorallocate($label, 255, 255, 255));
        $photo = imagecreatetruecolor(780, 180);
        imagecopyresized($photo, $label, 0, 0, 0, 0, 780, 180, 260, 60);

        ob_start();
        imagejpeg($photo);

        return [Image::fromBase64(base64_encode((string) ob_get_clean()), 'image/jpeg')];
    }

    /**
     * @return array{0: bool, 1: string} passed, and why not
     */
    public static function verdict(string $case, string $answer): array
    {
        $checks = match ($case) {
            'thread' => [[self::figureIn($answer, 48213), 'forgot the order number said at the start of the thread']],
            'catalog' => [[self::figureIn($answer, 23750), 'missed the price of the 250th product']],
            'document' => [[self::figureIn($answer, 17), 'missed the clause buried in the middle of the policy']],
            'three' => [
                [self::mentionsAny($answer, ['13:00', '13 h', '13h', '13 hs', '1 pm', '1 de la tarde']), 'missed Saturday closing time'],
                [self::figureIn($answer, 89000), 'missed the drill price'],
                [self::mentionsAny($answer, ['san martin']) && self::figureIn($answer, 4500), 'missed the address'],
            ],
            'wall' => [[self::figureIn($answer, 4500), 'lost the question at the end of the wall of text']],
            'photo' => [[self::figureIn($answer, 89000), 'did not find the drill in the photo, or described the photo back']],
            'stale' => [
                [self::figureIn($answer, 4500), 'did not give the live price'],
                [! self::figureIn($answer, 3900), 'repeated the old price from the thread'],
            ],
        };

        $failed = collect($checks)->first(fn (array $check): bool => ! $check[0]);

        return [$failed === null, $failed[1] ?? ''];
    }

    /** Whether a figure is written in the text, in any usual Spanish format ("89.000", "89000,00", "$ 89.000"). */
    public static function figureIn(string $text, int $value): bool
    {
        $plain = (string) preg_replace('/(?<=\d)[.,](?=\d{3}(?!\d))/', '', $text);

        return preg_match('/(?<![\d.,])'.$value.'(?:[.,]\d{1,2})?(?!\d)/', $plain) === 1;
    }

    /** @param  list<string>  $needles */
    public static function mentionsAny(string $text, array $needles): bool
    {
        $plain = mb_strtolower(Str::ascii($text));

        return collect($needles)->contains(fn (string $needle): bool => str_contains($plain, mb_strtolower($needle)));
    }

    /**
     * USD per million tokens [fresh input, cached input, output], from the admin
     * catalog (verified 2026-10-08). Not read from the table: the battery's
     * candidate row is created unpriced, and a ceiling that reads zero guards nothing.
     *
     * @var array<string, array{0: float, 1: float, 2: float}>
     */
    private const array RATES = [
        'gpt-6-luna' => [0.10, 0.01, 0.50],
        'gpt-6-sol' => [2.0, 0.20, 10.0],
        'gpt-6.1-sol' => [2.0, 0.10, 10.0],
        'gpt-6-astra' => [10.0, 1.0, 50.0],
    ];

    /** Counts this call against the ceiling; a model not listed is charged as the dearest one. */
    public static function charge(AgentResponse $response, string $model): float
    {
        [$fresh, $cachedRate, $output] = self::RATES[$model] ?? self::RATES['gpt-6-astra'];
        $cached = (int) ($response->usage->cacheReadInputTokens ?? 0);

        $cost = (($response->usage->inputTokens - $cached) * $fresh + $cached * $cachedRate + $response->usage->outputTokens * $output) / 1_000_000;

        return self::$spent += $cost;
    }

    public static function spent(): float
    {
        return self::$spent;
    }

    public static function reset(): void
    {
        self::$spent = 0.0;
    }

    public static function overCap(): bool
    {
        return self::$spent >= self::CAP_USD;
    }

    public static function report(string $line): void
    {
        $path = storage_path('app/'.self::REPORT);
        File::ensureDirectoryExists(dirname($path));
        File::append($path, $line."\n");
    }

    /** The order number sits in the OLDEST turn the assistant remembers (12), under ten turns of other talk. */
    private static function seedThread(Business $business, Conversation $conversation): string
    {
        $turns = [
            ['in', 'Hola, soy Ramiro. Mi número de pedido es el 48213, lo hice el martes.'],
            ['out', '¡Hola Ramiro! Te ayudo con lo que necesites.'],
            ['in', '¿Hasta qué hora abren hoy?'],
            ['out', 'De lunes a viernes atendemos de 08:00 a 18:00.'],
            ['in', '¿Tienen cinta métrica de 5 m?'],
            ['out', 'Sí, la cinta métrica de 5 m sale $6.000 y hay stock.'],
            ['in', 'Perfecto. ¿Y martillo de carpintero?'],
            ['out', 'Por ahora el martillo de carpintero no tiene stock.'],
            ['in', '¿Aceptan transferencia?'],
            ['out', 'Sí, aceptamos efectivo, débito y transferencia.'],
            ['in', 'Genial, gracias por la info.'],
            ['out', '¡De nada! Cualquier cosa me avisás.'],
        ];

        foreach ($turns as [$direction, $body]) {
            $factory = ConversationMessage::factory()->for($conversation);
            ($direction === 'out' ? $factory->out() : $factory)->create(['business_id' => $business->id, 'body' => $body]);
        }

        return '¿Me confirmás cuál era el número de mi pedido?';
    }

    /** 300 products with look-alike names; the one asked for is the 250th, with a word no other has. */
    private static function seedCatalog(Business $business): string
    {
        $categories = ['Llave inglesa', 'Destornillador Phillips', 'Alicate universal', 'Serrucho de mano', 'Pinza pico de loro', 'Broca para concreto', 'Cinta aisladora', 'Nivel de burbuja'];
        $brands = ['Stanley', 'Truper', 'Bahco', 'Gamma', 'Black+Decker'];

        for ($i = 1; $i <= 300; $i++) {
            $target = $i === 250;

            Product::factory()->create([
                'business_id' => $business->id,
                'name' => $target
                    ? 'Llave francesa reforzada 12 pulgadas Stanley'
                    : $categories[$i % 8].' '.(4 + intdiv($i, 8)).' '.($i % 2 === 0 ? 'mm' : 'pulgadas').' '.$brands[$i % 5],
                'price' => $target ? 23750 : 1000 + (($i * 137) % 90) * 500,
                'stock' => 10,
            ]);
        }

        (new EmbedCatalog($business->id))->handle(app(KnowledgeEmbedder::class));

        return '¿Cuánto sale la llave francesa reforzada de 12 pulgadas Stanley?';
    }

    /** About 15 thousand characters of policy, with the one clause the customer needs in the middle. */
    private static function seedDocument(Business $business): string
    {
        // Out of the way: its "cambios en 30 días" would make the question ambiguous.
        KnowledgeDocument::query()->where('business_id', $business->id)->where('title', 'Garantía y cambios')->delete();

        $fillers = [
            'Los productos eléctricos cuentan con la garantía de fábrica indicada en su caja, y para hacerla valer se presenta el ticket de compra.',
            'Los pedidos de materiales por volumen se reservan con una seña y se entregan en el orden en que fueron confirmados.',
            'El retiro en el local se hace con el documento de quien compró o con una autorización escrita para un tercero.',
            'Si un producto llega con daño de transporte, hay que avisarnos el mismo día adjuntando fotos del embalaje.',
            'Los precios publicados incluyen impuestos y pueden actualizarse sin aviso previo según la lista del proveedor.',
            'Las herramientas manuales no tienen cambio por desgaste normal ni por uso distinto del indicado por el fabricante.',
            'El horario de entrega a domicilio se coordina con el cliente y puede variar según la zona y el tamaño del pedido.',
            'Los productos en liquidación se venden en el estado en que se encuentran y no admiten garantía adicional.',
        ];

        $paragraphs = [];

        for ($i = 0; $i < 56; $i++) {
            // Letters, not numbers: a stray "17" in a heading would be a second right answer.
            $paragraphs[] = 'Punto '.chr(65 + $i % 26).chr(97 + intdiv($i, 26)).'. '.$fillers[$i % 8].' Se aplica de la misma forma en todas las sucursales del negocio.';
        }

        array_splice($paragraphs, 28, 0, 'Punto especial. Las devoluciones por arrepentimiento de compra se aceptan dentro de los 17 días corridos desde la compra, con el producto sin uso y el ticket.');

        KnowledgeDocument::factory()->create([
            'business_id' => $business->id,
            'title' => 'Política de devoluciones y condiciones de venta',
            'content' => implode("\n\n", $paragraphs),
        ]);

        return 'Compré algo y me arrepentí, ¿en cuántos días puedo devolverlo?';
    }

    /** The assistant once said $3.900 for the box of screws; the catalog now says $4.500, and the catalog rules. */
    private static function seedStale(Business $business, Conversation $conversation): string
    {
        ConversationMessage::factory()->for($conversation)->create(['business_id' => $business->id, 'body' => '¿Cuánto sale la caja de tornillos x100?']);
        ConversationMessage::factory()->for($conversation)->out()->create(['business_id' => $business->id, 'body' => 'La caja de tornillos x100 sale $3.900.']);
        ConversationMessage::factory()->for($conversation)->create(['business_id' => $business->id, 'body' => 'Gracias, lo pienso y vuelvo.']);

        return 'Vuelvo: ¿cuánto era la caja de tornillos x100?';
    }

    /** A customer pasting a whole renovation story, with the real question in its last line (~3,900 characters). */
    private static function wall(): string
    {
        $story = 'Estoy arreglando la casa de mis padres, que tiene más de treinta años, y la verdad es que cada pared que abro me muestra otro problema: primero fue la humedad en el baño, después el revoque del pasillo, y ahora resulta que hay que cambiar toda la instalación de la cocina porque los caños están oxidados. ';

        return trim(str_repeat($story, 11))."\n\nEntonces, para colgar los estantes necesito tornillos: ¿cuánto sale la caja de tornillos x100?";
    }
}
