<?php

declare(strict_types=1);

namespace App\Classes\Report;

use App\Classes\Main\Client;
use App\Classes\Main\Inbox;
use App\Enums\ReportAudience;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * The "Conversaciones" list as a file: one line per thread with who, where it
 * stands, when it last moved and how it starts. The messages themselves stay in
 * the panel. The same search and dates as the screen, or all when asked.
 */
class ConversationsReport extends ListReport
{
    public string $title {
        get => __('reports/conversations.title');
    }

    public string $filename {
        get => __('reports/conversations.filename');
    }

    public array $columns {
        get => [
            __('reports/conversations.name'),
            __('reports/conversations.phone'),
            __('reports/conversations.status'),
            __('reports/conversations.last_message'),
            __('reports/conversations.files'),
            __('reports/conversations.preview'),
        ];
    }

    public ReportAudience $audience {
        get => ReportAudience::Client;
    }

    public int $total {
        get => $this->inbox()?->threads->count() ?? 0;
    }

    public function authorize(User $user): bool
    {
        return $user->can('access-client-app') && $user->business !== null;
    }

    protected function rows(ReportScope $scope): array
    {
        return $this->threads($scope)
            ->map(fn (Conversation $thread): array => [
                (string) ($thread->contact_name ?? __('reports/conversations.anonymous')),
                $thread->contact_phone,
                __('reports/conversations.statuses.'.$thread->status->value),
                (string) $thread->last_message_at?->inBusinessTime()->format('d/m/Y H:i'),
                (int) $thread->getAttribute('attachments_count'),
                Str::limit(trim((string) $thread->latestMessage?->body), 140),
            ])
            ->all();
    }

    protected function shown(ReportScope $scope): int
    {
        return $this->threads($scope)->count();
    }

    protected function filters(ReportScope $scope): array
    {
        return collect([
            ['label' => __('reports/conversations.filter_search'), 'value' => $scope->value('buscar') === '' ? '' : '«'.$scope->value('buscar').'»'],
            ['label' => __('reports/conversations.filter_dates'), 'value' => $scope->dateLabel('fechas')],
        ])->filter(fn (array $filter): bool => $filter['value'] !== '')->values()->all();
    }

    /** @return Collection<int, Conversation> */
    private function threads(ReportScope $scope): Collection
    {
        return $this->inbox()?->filtered($scope->value('buscar'), $scope->value('fechas'))[0] ?? new Collection;
    }

    private function inbox(): ?Inbox
    {
        return Client::for(auth()->user())->inbox;
    }
}
