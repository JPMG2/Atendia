<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Enums\PlatformWhatsAppState;
use App\Models\BusinessMention;
use App\Models\Payment;
use App\Models\PlatformEvent;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\PlatformWhatsApp;

/**
 * What needs the platform admin today, straight from the tables: the card that
 * opens the AI panel costs no question. Only what is above zero, only what the
 * admin may open: a row is a door, and a door she cannot walk through is not drawn.
 */
class AdminAttention
{
    public function __construct(private readonly User $admin) {}

    /** @var list<array{key: string, count: int, icon: string, route: string}>|null */
    public ?array $items {
        get => $this->items ??= $this->read();
    }

    /**
     * The same lines with the web address of the screen that solves each, for the pages that print the card.
     *
     * @var list<array{key: string, count: int, icon: string, url: string}>
     */
    public array $links {
        get => array_map(
            fn (array $item): array => ['key' => $item['key'], 'count' => $item['count'], 'icon' => $item['icon'], 'url' => route($item['route'])],
            $this->items,
        );
    }

    /** @return list<array{key: string, count: int, icon: string, route: string}> */
    private function read(): array
    {
        $line = $this->admin->can('integrations.view') ? PlatformWhatsApp::lastKnown()['state'] : PlatformWhatsAppState::Connected;

        $counts = [
            'receipts' => ['payments.view', 'receipt', 'admin.payments', fn (): int => Payment::pendingReviewCount()],
            'alerts' => ['alerts.view', 'siren', 'admin.alerts', fn (): int => PlatformEvent::counts()['critical']],
            'mentions' => ['mentions.manage', 'star', 'admin.mentions', fn (): int => BusinessMention::deskCounts()['pending']],
            'support' => ['support.view', 'life-buoy', 'admin.support', fn (): int => SupportTicket::queueCounts()['answer']],
            // Known to be down, not merely unverified: the board has its own sensor for a bridge that does not answer.
            'line' => ['integrations.view', 'whatsapp', 'admin.platform-whatsapp', fn (): int => in_array($line, [PlatformWhatsAppState::Connected, PlatformWhatsAppState::Unreachable], true) ? 0 : 1],
        ];

        return collect($counts)
            ->filter(fn (array $row): bool => $this->admin->can($row[0]))
            ->map(fn (array $row, string $key): array => ['key' => $key, 'count' => $row[3](), 'icon' => $row[1], 'route' => $row[2]])
            ->filter(fn (array $item): bool => $item['count'] > 0)
            ->values()
            ->all();
    }
}
