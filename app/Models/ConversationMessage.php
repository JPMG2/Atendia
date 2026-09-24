<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MessageAuthor;
use App\Enums\MessageDirection;
use App\Enums\MessageKind;
use App\Traits\BelongsToBusiness;
use Carbon\CarbonInterface;
use Database\Factories\ConversationMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** One turn of a thread: the customer's text or the assistant's reply. */
#[Fillable(['business_id', 'conversation_id', 'direction', 'author', 'kind', 'wa_message_id', 'body', 'prompt_tokens', 'completion_tokens', 'audio_seconds', 'knowledge_sources', 'embedding'])]
class ConversationMessage extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<ConversationMessageFactory> */
    use HasFactory;

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

    protected static function booted(): void
    {
        // The list's verdict on birth; the AI triage refines it later and must
        // not be overwritten, hence `creating` and never `saving`.
        static::creating(function (ConversationMessage $message): void {
            $message->is_enquiry = $message->direction === MessageDirection::In
                && $message->kind !== MessageKind::Note
                && self::looksLikeEnquiry((string) $message->body);
        });
    }

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
     * The customer's real questions: what statistics count and teaching offers.
     *
     * @param  Builder<ConversationMessage>  $query
     */
    public function scopeEnquiries(Builder $query): void
    {
        $query->where('direction', MessageDirection::In)->where('is_enquiry', true);
    }

    /**
     * Questions the assistant could not answer: where teaching it pays off.
     *
     * @param  Builder<ConversationMessage>  $query
     */
    public function scopeTeachable(Builder $query): void
    {
        $query->enquiries()->where('needs_teaching', true);
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
            'embedding' => 'array',
            'is_enquiry' => 'boolean',
            'needs_teaching' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
