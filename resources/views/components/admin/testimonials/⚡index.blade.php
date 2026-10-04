<?php

use App\Actions\Admin\ModerateTestimonial;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Enums\TestimonialStatus;
use App\Models\Testimonial;
use App\Traits\HasNotifications;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The moderation desk: every owner's word about AtendIa, pending first.
 * Nothing reaches the landing without the two signatures — the owner's
 * consent and the admin's approval.
 */
new class extends Component
{
    use HasNotifications;

    /** @return Collection<int, Testimonial> */
    #[Computed]
    public function rows(): Collection
    {
        return Testimonial::moderationQueue();
    }

    public function approve(int $id): void
    {
        $moderated = app(ModerateTestimonial::class)->handle($id, TestimonialStatus::Approved);

        $this->dispatchNotification($moderated === null
            ? new NotificationDto(__('testimonials.no_consent'), NotificationType::Error)
            : new NotificationDto(__('testimonials.approved'), NotificationType::Success));

        unset($this->rows);
    }

    public function reject(int $id): void
    {
        app(ModerateTestimonial::class)->handle($id, TestimonialStatus::Rejected);
        $this->dispatchNotification(new NotificationDto(__('testimonials.rejected'), NotificationType::Success));

        unset($this->rows);
    }

    /** The tab title comes from translations; a PHP attribute cannot call __(). */
    public function render(): View
    {
        return $this->view()->title(__('testimonials.title'));
    }
};
?>

<div>
    <div class="page-head">
        <div>
            <h1 class="page-head-title">{{ __('testimonials.title') }}</h1>
            <p class="page-head-sub">{{ __('testimonials.sub') }}</p>
        </div>
    </div>

    <x-ui.card class="p-5">
        @if ($this->rows->isEmpty())
            <div class="flex items-start gap-4">
                <div class="bg-brand-soft flex size-11 flex-none items-center justify-center rounded-xl" style="color: var(--brand)">
                    <x-icon name="star" :size="22" />
                </div>
                <div class="min-w-0">
                    <h2 class="text-strong text-sm font-semibold">{{ __('testimonials.empty_title') }}</h2>
                    <p class="text-body mt-1 text-sm">{{ __('testimonials.empty_body') }}</p>
                </div>
            </div>
        @else
            <ul class="divide-y divide-[color:var(--border-subtle)]">
                @foreach ($this->rows as $row)
                    <li wire:key="testimonial-{{ $row->id }}" class="flex flex-wrap items-start gap-x-4 gap-y-2 px-2 py-3">
                        <span class="min-w-0 flex-1">
                            <span class="flex flex-wrap items-center gap-2">
                                <span class="text-strong text-sm font-semibold">{{ $row->display_name }}</span>
                                @if ($row->display_role !== null)
                                    <span class="text-muted text-xs">{{ $row->display_role }}</span>
                                @endif
                                @if ($row->rating !== null)
                                    <span class="flex gap-0.5" style="color: var(--accent)">
                                        @for ($i = 0; $i < $row->rating; $i++)
                                            <x-icon name="star" :size="12" />
                                        @endfor
                                    </span>
                                @endif
                                @if ($row->status === TestimonialStatus::Approved)
                                    <x-ui.badge variant="brand">{{ __('testimonials.status_approved') }}</x-ui.badge>
                                @elseif ($row->status === TestimonialStatus::Rejected)
                                    <x-ui.badge variant="neutral">{{ __('testimonials.status_rejected') }}</x-ui.badge>
                                @endif
                                @if ($row->consent_given_at === null)
                                    <x-ui.badge variant="neutral">{{ __('testimonials.no_consent_chip') }}</x-ui.badge>
                                @endif
                            </span>
                            <span class="text-body mt-1 block text-sm">{{ $row->quote }}</span>
                            <span class="text-subtle mt-0.5 block font-mono text-xs">{{ $row->created_at?->format('d/m/Y') }}</span>
                        </span>
                        <span class="flex flex-none items-center gap-2 self-center">
                            @if ($row->status !== TestimonialStatus::Approved && $row->consent_given_at !== null)
                                <x-ui.button variant="primary" size="sm" icon="check" wire:click="approve({{ $row->id }})">
                                    {{ __('testimonials.approve') }}
                                </x-ui.button>
                            @endif
                            @if ($row->status !== TestimonialStatus::Rejected)
                                <x-ui.button variant="danger" size="sm" wire:click="reject({{ $row->id }})">
                                    {{ __('testimonials.reject') }}
                                </x-ui.button>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>
</div>
