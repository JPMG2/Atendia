<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MessageAuthor;
use App\Enums\MessageDirection;
use App\Enums\MessageKind;
use App\Traits\BelongsToBusiness;
use App\Traits\SearchesText;
use Carbon\CarbonInterface;
use Database\Factories\ConversationMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** One turn of a thread: the customer's text or the assistant's reply. */
#[Fillable(['business_id', 'conversation_id', 'direction', 'author', 'kind', 'wa_message_id', 'body', 'media', 'media_text', 'audio_seconds', 'knowledge_sources'])]
class ConversationMessage extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<ConversationMessageFactory> */
    use HasFactory;

    use SearchesText;

    /**
     * Words that carry no question on their own: acknowledgements, greetings,
     * laughs. A message made ONLY of these is not an enquiry (2026-09-23: a
     * lone "sí" ranked next to "¿abren hoy?" in the owner's statistics).
     */
    private const array SMALL_TALK = [
        'si', 'no', 'ok', 'oka', 'okey', 'okay', 'dale', 'bueno', 'buenisimo', 'listo', 'perfecto', 'genial',
        'excelente', 'joya', 'barbaro', 'claro', 'obvio', 'vale', 'entendido', 'entiendo', 'de', 'una', 'nada',
        'gracias', 'muchas', 'mil', 'muchisimas', 'igualmente', 'bien', 'muy', 're', 'tambien', 'ah', 'oh',
        'hola', 'buenas', 'buen', 'buenos', 'dia', 'dias', 'tardes', 'noches', 'chau', 'adios', 'saludos',
        'nos', 'vemos', 'hasta', 'luego', 'pronto', 'yes', 'thanks', 'thank', 'you', 'hi', 'hello', 'bye',
        'sim', 'nao', 'obrigado', 'obrigada', 'y', 'e', 'a', 'que', 'q', 'mm', 'mmm', 'hmm',
    ];

    /** True unless the text is empty, only emoji/punctuation, laughter or small talk. */
    public static function looksLikeEnquiry(string $text): bool
    {
        $normalized = Str::lower(Str::ascii($text));
        $words = preg_split('/[^a-z0-9]+/', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $words = array_filter($words, fn (string $word): bool => preg_match('/^(j[aeij]+|h[ae]h[aeh]*|lo+l)$/', $word) !== 1);

        return array_diff($words, self::SMALL_TALK) !== [];
    }

    /**
     * The month's traffic per business: the volume half of the usage meter.
     *
     * @return Collection<int, object{business_id: int, threads: int, messages: int, audio_seconds: int}>
     */
    public static function monthlyVolume(CarbonInterface $month): Collection
    {
        return self::query()
            ->selectRaw('business_id, count(distinct conversation_id) as threads, count(*) as messages, coalesce(sum(audio_seconds), 0) as audio_seconds')
            ->where('kind', MessageKind::Message)
            ->whereBetween('created_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->groupBy('business_id')
            ->get()
            ->map(fn (self $row): object => (object) [
                'business_id' => (int) $row->business_id,
                'threads' => (int) $row->getAttribute('threads'),
                'messages' => (int) $row->getAttribute('messages'),
                'audio_seconds' => (int) $row->audio_seconds,
            ])
            ->toBase();
    }

    /**
     * The label an attachment reads as in the body (panel, memory, analysis).
     *
     * @param  array<string, mixed>  $item
     */
    public static function mediaLine(array $item): string
    {
        return match ($item['kind'] ?? null) {
            'image' => __('assistant.media.image'),
            'location' => ($item['label'] ?? '') !== '' ? __('assistant.media.location', ['label' => $item['label']]) : __('assistant.media.location_unnamed'),
            default => __('assistant.media.document', ['name' => $item['name'] ?? __('assistant.media.document_unnamed')]),
        };
    }

    /**
     * A 3×3 block of OpenStreetMap tiles around a point, with the offset that
     * centers the point in a box: a static map that loads like nine small
     * images, where the embeddable map needs WebGL and a whole page per bubble.
     *
     * @return array{tiles: list<array{url: string, left: int, top: int}>, left: int, top: int}
     */
    public static function mapTiles(float $lat, float $lng, int $boxWidth, int $boxHeight, int $zoom = 16): array
    {
        $scale = 2 ** $zoom;
        $x = ($lng + 180) / 360 * $scale;
        $y = (1 - log(tan(deg2rad($lat)) + 1 / cos(deg2rad($lat))) / M_PI) / 2 * $scale;
        [$tileX, $tileY] = [(int) floor($x), (int) floor($y)];

        $tiles = [];

        foreach ([-1, 0, 1] as $row) {
            foreach ([-1, 0, 1] as $column) {
                $tiles[] = [
                    'url' => "https://tile.openstreetmap.org/{$zoom}/".(($tileX + $column + $scale) % $scale).'/'.($tileY + $row).'.png',
                    'left' => ($column + 1) * 256,
                    'top' => ($row + 1) * 256,
                ];
            }
        }

        return [
            'tiles' => $tiles,
            'left' => (int) round($boxWidth / 2 - (256 + ($x - $tileX) * 256)),
            'top' => (int) round($boxHeight / 2 - (256 + ($y - $tileY) * 256)),
        ];
    }

    /** The words or the PDFs of this message mention the needle, accent-free. */
    public function matches(string $needle): bool
    {
        $folded = Str::ascii(mb_strtolower($needle));

        return str_contains(Str::ascii(mb_strtolower($this->body)), $folded)
            || str_contains(Str::ascii(mb_strtolower((string) $this->media_text)), $folded);
    }

    /**
     * The stretch of a PDF around the first hit, so the owner sees WHY the
     * document matched without opening it. Null when the hit is not in a PDF.
     */
    public function mediaSnippet(string $needle, int $radius = 60): ?string
    {
        $text = preg_replace('/\s+/u', ' ', (string) $this->media_text) ?? '';
        $at = mb_stripos(Str::ascii($text), Str::ascii(trim($needle)));

        if (trim($needle) === '' || $at === false) {
            return null;
        }

        $start = max(0, $at - $radius);

        return ($start > 0 ? '…' : '')
            .trim(mb_substr($text, $start, mb_strlen($needle) + $radius * 2))
            .($start + mb_strlen($needle) + $radius * 2 < mb_strlen($text) ? '…' : '');
    }

    /** The body without its attachment labels: the bubble draws those as previews. */
    public function textBesideMedia(): string
    {
        $lines = explode("\n", $this->body);

        foreach ($this->media ?? [] as $item) {
            $at = array_search(self::mediaLine($item), $lines, true);

            if ($at !== false) {
                unset($lines[$at]);
            }
        }

        return trim(implode("\n", $lines));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => MessageDirection::class,
            'author' => MessageAuthor::class,
            'kind' => MessageKind::class,
            'knowledge_sources' => 'array',
            'media' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Messages of this tenant whose body matches, newest first and one per
     * thread: the palette lists conversations, not lines.
     *
     * @return EloquentCollection<int, static>
     */
    public static function matching(string $term, int $limit): EloquentCollection
    {
        return static::query()
            ->select(['id', 'conversation_id', 'body', 'created_at'])
            ->whereNotNull('conversation_id')
            ->where('kind', MessageKind::Message)
            ->whereTextMatches(['body'], $term)
            ->with('conversation:id,contact_name,contact_phone')
            ->orderByDesc('created_at')
            ->limit($limit * 4)
            ->get()
            ->unique('conversation_id')
            ->take($limit);
    }
}
