<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Agents\AskAtendia;
use App\Enums\ConversationStatus;
use App\Enums\MessageDirection;
use App\Enums\QuestionResolution;
use App\Interfaces\Main\OwnerSkillTool;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\ConversationQuestion;
use App\Traits\ReadsDateRange;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The owner's "what happened on WhatsApp" in a period: the counts the
 * statistics screen uses plus the threads themselves, each with the link
 * that opens it — so the answer is a door, not just a number.
 */
class OwnerConversations implements OwnerSkillTool
{
    use ReadsDateRange;

    private const int LISTED = 10;

    private const int QUESTIONS = 12;

    public function __construct(private readonly Business $business) {}

    public static function forOwner(AskAtendia $assistant): ?static
    {
        return new static($assistant->business);
    }

    public function description(): Stringable|string
    {
        return 'Conversaciones de WhatsApp del negocio en un período: cuántas tuvieron mensajes '
            .'de clientes, mensajes recibidos, contactos nuevos, cuántas esperan hoy a una persona '
            .'del equipo, las últimas conversaciones con su estado y enlace, y QUÉ consultaron los '
            .'clientes (sus preguntas y quién las respondió). Con only_waiting en true lista solo '
            .'las que esperan al equipo (las que quedaron sin resolver).';
    }

    public function handle(Request $request): Stringable|string
    {
        $range = $this->dateRange($request, $this->business);

        if (is_string($range)) {
            return $range;
        }

        [$from, $to] = $range;
        $window = [$from->utc(), $to->utc()];

        $inbound = ConversationMessage::query()
            ->where('business_id', $this->business->id)
            ->where('direction', MessageDirection::In)
            ->whereBetween('created_at', $window);

        $threadIds = (clone $inbound)->distinct()->pluck('conversation_id');
        $waiting = $this->threads()->where('status', ConversationStatus::Team);

        $lines = [
            $this->periodLabel($from, $to).': '.$threadIds->count().' conversaciones con mensajes de clientes, '
                .(clone $inbound)->count().' mensajes recibidos, '
                .$this->threads()->whereBetween('created_at', $window)->count().' contactos nuevos.',
            'Esperando a una persona del equipo en este momento: '.(clone $waiting)->count().'.',
        ];

        $listed = (bool) $request['only_waiting']
            ? $waiting->orderByDesc('escalated_at')
            : $this->threads()->whereIn('id', $threadIds)->orderByDesc('last_message_at');

        $threads = $listed->with('customer')->limit(self::LISTED)->get();

        if ($threads->isNotEmpty()) {
            $lines[] = (bool) $request['only_waiting'] ? 'Las que esperan al equipo:' : 'Últimas conversaciones del período:';

            foreach ($threads as $thread) {
                $lines[] = '- '.$this->line($thread);
            }
        }

        return implode("\n", [...$lines, ...$this->questions($window)]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->dateRangeSchema($schema),
            'only_waiting' => $schema->boolean()->description('true = solo las que esperan a una persona del equipo.')->required(),
        ];
    }

    /** @return Builder<Conversation> */
    private function threads(): Builder
    {
        return Conversation::query()->where('business_id', $this->business->id);
    }

    /**
     * What customers actually asked. Questions are read once a thread goes
     * quiet, so the freshest hours may still be missing — said, not hidden.
     *
     * @param  array{0: mixed, 1: mixed}  $window
     * @return list<string>
     */
    private function questions(array $window): array
    {
        $questions = ConversationQuestion::query()
            ->where('business_id', $this->business->id)
            ->whereBetween('asked_at', $window)
            ->latest('asked_at')
            ->limit(self::QUESTIONS)
            ->get(['question', 'resolved_by']);

        $note = 'Las preguntas se leen cuando la conversación queda quieta unas '.config('atendia.analysis.idle_hours').' horas: las más recientes pueden no estar todavía.';

        if ($questions->isEmpty()) {
            return ['No hay preguntas de clientes registradas en el período. '.$note];
        }

        return [
            'Lo que consultaron los clientes (más recientes primero):',
            ...$questions->map(fn (ConversationQuestion $question): string => '- "'.$question->question.'" · '.match ($question->resolved_by) {
                QuestionResolution::Assistant => 'la respondió el asistente',
                QuestionResolution::Team => 'la respondió el equipo',
                default => 'quedó sin respuesta',
            })->all(),
            $note,
        ];
    }

    private function line(Conversation $thread): string
    {
        $name = $thread->customer?->displayName() ?? $thread->contact_name ?? $thread->contact_phone;
        $last = $thread->last_message_at?->setTimezone($this->business->localTimezone())->format('d/m/Y H:i') ?? 'sin mensajes';
        $state = match ($thread->status) {
            ConversationStatus::Open => 'la atiende el asistente',
            ConversationStatus::Team => 'espera a una persona del equipo',
            ConversationStatus::Customer => 'espera respuesta del cliente',
            ConversationStatus::Resolved => 'resuelta',
            default => 'sin estado',
        };

        return "{$name} · último mensaje {$last} · {$state} · ".route('conversations', ['hilo' => $thread->id]);
    }
}
