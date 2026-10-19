<?php

use App\Actions\Business\AcceptMention;
use App\Actions\Business\ConfirmMentionFollow;
use App\Actions\Business\DismissMention;
use App\Actions\Business\ResolveMentionOffer;
use App\Actions\Business\WithdrawMention;
use App\Dto\MentionOfferDto;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Models\MentionGoal;
use App\Traits\HasNotifications;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The offer to be mentioned on the platform's networks, and where an accepted
 * one stands. It is the card of a milestone, like the testimonial: the owner
 * answers once, and the answer decides when it may come back.
 */
new class extends Component
{
    use HasNotifications;

    #[Computed]
    public function offer(): ?MentionOfferDto
    {
        $user = Auth::user();

        if ($user === null || $user->business === null || ! $user->can('manage-business')) {
            return null;
        }

        return app(ResolveMentionOffer::class)->handle($user->business);
    }

    public function accept(): void
    {
        $this->answer(fn ($business) => app(AcceptMention::class)->handle($business), 'accepted');
    }

    public function dismiss(): void
    {
        $this->answer(fn ($business) => app(DismissMention::class)->handle($business), 'dismissed');
    }

    public function confirmFollow(): void
    {
        $this->answer(fn ($business) => app(ConfirmMentionFollow::class)->handle($business), 'follow_confirmed');
    }

    public function withdraw(): void
    {
        $this->answer(fn ($business) => app(WithdrawMention::class)->handle($business), 'withdrawn');
    }

    /** Every answer is the owner's own: the business comes from the session, never from the card. */
    private function answer(callable $act, string $done): void
    {
        $this->authorize('manage-business');

        $changed = $act(Auth::user()->business) !== null;

        $this->dispatchNotification($changed
            ? new NotificationDto(__('client.mention.done.'.$done), NotificationType::Success)
            : new NotificationDto(__('client.mention.stale'), NotificationType::Error));

        unset($this->offer);
    }

    public function achievement(int $threshold): string
    {
        return MentionGoal::achievement($threshold);
    }
};
?>

<div>
    @if ($offer = $this->offer)
        <x-ui.card class="mb-4 p-5">
            <div class="flex items-start gap-4">
                <div class="bg-brand-soft flex size-11 flex-none items-center justify-center rounded-xl" style="color: var(--brand)">
                    <x-icon name="star" :size="22" />
                </div>

                <div class="min-w-0 flex-1">
                    <h2 class="font-display text-strong text-base">
                        {{ __('client.mention.'.$offer->state->value.'.title', ['achievement' => $this->achievement($offer->threshold)]) }}
                    </h2>
                    <p class="text-body mt-1 text-sm">
                        {{ __('client.mention.'.$offer->state->value.'.body', ['accounts' => $offer->accountNames]) }}
                    </p>

                    {{-- Only the accounts the platform really has: one button each. --}}
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        @if (in_array($offer->state->value, ['offer', 'pending', 'not_following'], true))
                            @foreach ($offer->accounts as $account)
                                <x-ui.button variant="secondary" size="sm" icon="external-link" :href="$account['url']" target="_blank" rel="noopener" wire:key="account-{{ $loop->index }}">
                                    {{ __('client.mention.open_profile', ['network' => $account['network']]) }}
                                </x-ui.button>
                            @endforeach
                        @endif

                        @if ($offer->state->value === 'offer')
                            <x-ui.button variant="primary" size="sm" icon="check" class="data-loading:opacity-50" wire:click="accept">
                                {{ __('client.mention.accept') }}
                            </x-ui.button>
                        @elseif ($offer->state->value === 'needs_network')
                            <x-ui.button variant="primary" size="sm" icon="plus" :href="route('my-business.redes')" wire:navigate>
                                {{ __('client.mention.add_network') }}
                            </x-ui.button>
                        @elseif ($offer->state->value === 'not_following')
                            <x-ui.button variant="primary" size="sm" icon="check" class="data-loading:opacity-50" wire:click="confirmFollow">
                                {{ __('client.mention.confirm_follow') }}
                            </x-ui.button>
                        @endif

                        @if (in_array($offer->state->value, ['offer', 'needs_network'], true))
                            <x-ui.button variant="danger" size="sm" wire:click="dismiss">
                                {{ __('client.mention.not_now') }}
                            </x-ui.button>
                        @else
                            <x-ui.button
                                variant="danger"
                                size="sm"
                                x-on:click="dialog.confirm({
                                    title: {{ \Illuminate\Support\Js::from(__('client.mention.withdraw_title')) }},
                                    message: {{ \Illuminate\Support\Js::from(__('client.mention.withdraw_message')) }},
                                    accept: {{ \Illuminate\Support\Js::from(__('client.mention.withdraw_accept')) }},
                                    type: 'warning',
                                }).then((ok) => ok && $wire.withdraw())"
                            >
                                {{ __('client.mention.withdraw') }}
                            </x-ui.button>
                        @endif
                    </div>
                </div>
            </div>
        </x-ui.card>
    @endif
</div>
