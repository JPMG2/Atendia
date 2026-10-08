<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Enums\SubscriptionStatus;
use App\Enums\WhatsAppLinkState;
use App\Models\Business;
use App\Models\SupportTicket;
use Illuminate\Support\Collection;

/**
 * Who is on the other side of a report, in what the person answering needs:
 * whether they pay, whether their WhatsApp works, where an answer will reach
 * them, and what else they reported. Without it a reply is written blind to
 * the fact that the customer is a month behind or has been offline all week.
 */
final class SupportCustomer
{
    public function __construct(
        private readonly Business $business,
        private readonly int $exceptTicketId,
    ) {}

    public string $name {
        get => $this->business->name;
    }

    /** The plan the business is on, or null while it has no subscription row. */
    public ?string $plan {
        get => $this->business->subscription === null ? null : ucfirst((string) $this->business->subscription->plan);
    }

    /**
     * Whether it pays, with the tone the screen paints it in.
     *
     * @var array{label: string, tone: string}
     */
    public array $payment {
        get {
            if ($this->business->isSuspended()) {
                return ['label' => __('support.admin.customer.suspended'), 'tone' => 'is-danger'];
            }

            $subscription = $this->business->subscription;

            return match ($subscription?->status) {
                SubscriptionStatus::Active => ['label' => __('support.admin.customer.payment.active'), 'tone' => 'is-brand'],
                SubscriptionStatus::Trialing => ['label' => __('support.admin.customer.payment.trialing'), 'tone' => 'is-info'],
                SubscriptionStatus::PastDue => ['label' => __('support.admin.customer.payment.past_due', ['days' => abs((int) $subscription->daysUntilPayment())]), 'tone' => 'is-warning'],
                SubscriptionStatus::Paused => ['label' => __('support.admin.customer.payment.paused'), 'tone' => 'is-danger'],
                default => ['label' => __('support.admin.customer.payment.none'), 'tone' => 'is-neutral'],
            };
        }
    }

    /**
     * Whether its assistant can answer its own customers right now.
     *
     * @var array{label: string, tone: string}
     */
    public array $whatsapp {
        get => match ($this->business->linkState()) {
            WhatsAppLinkState::Connected => ['label' => __('support.admin.customer.whatsapp.connected'), 'tone' => 'is-brand'],
            WhatsAppLinkState::Unverified => ['label' => __('support.admin.customer.whatsapp.unverified'), 'tone' => 'is-warning'],
            WhatsAppLinkState::Disconnected => ['label' => __('support.admin.customer.whatsapp.disconnected'), 'tone' => 'is-danger'],
        };
    }

    /** Whether our answer has a WhatsApp number to go to: the first thing to know before writing it. */
    public bool $hasAlertNumber {
        get => $this->business->ownerWhatsAppDigits() !== '';
    }

    /** The address a reply falls back to when there is no number. */
    public ?string $fallbackEmail {
        get => $this->business->billing_email ?: $this->business->email;
    }

    /**
     * What else this business reported, newest first.
     *
     * @var Collection<int, SupportTicket>
     */
    public Collection $others {
        get => SupportTicket::siblingsOf($this->business->id, $this->exceptTicketId);
    }
}
