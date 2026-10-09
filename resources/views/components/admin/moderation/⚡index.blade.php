<?php

use App\Actions\Moderation\LiftSuspension;
use App\Actions\Moderation\ReviewModerationFlag;
use App\Dto\NotificationDto;
use App\Enums\ModerationSeverity;
use App\Enums\NotificationType;
use App\Models\ModerationFlag;
use App\Traits\HasNotifications;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The moderation desk: what the content filter caught, newest first. A
 * refused file waits for a look; a suspension waits for the admin's call.
 */
new class extends Component
{
    use HasNotifications;

    /** @return Collection<int, ModerationFlag> */
    #[Computed]
    public function queue(): Collection
    {
        return ModerationFlag::reviewQueue();
    }

    /** @return Collection<int, ModerationFlag> */
    #[Computed]
    public function recent(): Collection
    {
        return ModerationFlag::recentlyReviewed();
    }

    public function review(int $id): void
    {
        app(ReviewModerationFlag::class)->handle($id, Auth::user());

        $this->dispatchNotification(new NotificationDto(__('moderation.admin.reviewed'), NotificationType::Success));
        unset($this->queue, $this->recent);
    }

    public function lift(int $businessId): void
    {
        $business = app(LiftSuspension::class)->handle($businessId, Auth::user());

        $this->dispatchNotification(new NotificationDto(__('moderation.admin.lifted', ['business' => $business->name]), NotificationType::Success));
        unset($this->queue, $this->recent);
    }

    public function render(): View
    {
        return $this->view()->title(__('moderation.admin.title'));
    }
};
?>

<div>
    <div class="page-head">
        <div>
            <h1 class="page-head-title">{{ __('moderation.admin.title') }}</h1>
            <p class="page-head-sub">{{ __('moderation.admin.sub') }}</p>
        </div>
    </div>

    <x-ui.card class="bp-card" x-data="adminModeration">
        <div class="bp-card-head"><h2>{{ __('moderation.admin.queue_title') }}</h2></div>

        @if ($this->queue->isEmpty())
            <p class="text-muted text-sm">{{ __('moderation.admin.queue_empty') }}</p>
        @else
            <div class="pay-table-wrap">
                <table class="pay-table" data-sortable>
                    <thead>
                        <tr>
                            <th>{{ __('moderation.admin.date') }}</th>
                            <th>{{ __('moderation.admin.business') }}</th>
                            <th>{{ __('moderation.admin.source') }}</th>
                            <th>{{ __('moderation.admin.category') }}</th>
                            <th>{{ __('moderation.admin.score') }}</th>
                            <th>{{ __('moderation.admin.result') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->queue as $flag)
                            <tr wire:key="flag-{{ $flag->id }}">
                                {{-- data-label: stacked below 991px the header row is gone,
                                so each cell has to say what it is. --}}
                                <td class="font-mono" data-label="{{ __('moderation.admin.date') }}">{{ $flag->created_at->format('d/m/Y H:i') }}</td>
                                <td class="is-key" data-label="{{ __('moderation.admin.business') }}">
                                    {{ $flag->business?->name }}
                                    @if ($flag->business?->isSuspended())
                                        <span class="status-tag is-danger">{{ __('moderation.admin.suspended') }}</span>
                                    @endif
                                    @if (filled($flag->business?->appeal_message))
                                        <p class="text-muted mt-1 max-w-[320px] text-sm">
                                            <b>{{ __('moderation.appeal.admin_label') }}:</b> {{ $flag->business->appeal_message }}</p>
                                    @endif
                                </td>
                                <td data-label="{{ __('moderation.admin.source') }}">{{ __('moderation.sources.'.$flag->source) }}</td>
                                <td class="font-mono" data-label="{{ __('moderation.admin.category') }}">{{ $flag->category }}</td>
                                <td class="font-mono" data-label="{{ __('moderation.admin.score') }}">{{ number_format((float) $flag->score, 2, ',', '.') }}</td>
                                <td data-label="{{ __('moderation.admin.result') }}">
                                    <span @class([
                                        'status-tag',
                                        'is-danger' => $flag->severity === ModerationSeverity::Severe,
                                    ])>{{ __('moderation.severity.'.$flag->severity->value) }}</span>
                                </td>
                                <td>
                                    <div class="flex gap-2">
                                        @if ($flag->business?->isSuspended())
                                            <x-ui.button
                                                variant="primary"
                                                size="sm"
                                                x-on:click="confirmLift({{ $flag->business_id }}, {{ Illuminate\Support\Js::from($flag->business->name) }})"
                                            >
                                                {{ __('moderation.admin.lift') }}</x-ui.button>
                                        @endif
                                        <x-ui.button variant="secondary" size="sm" wire:click="review({{ $flag->id }})">
                                            {{ __('moderation.admin.review') }}</x-ui.button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>

    <x-ui.card class="bp-card mt-3">
        <div class="bp-card-head"><h2>{{ __('moderation.admin.recent_title') }}</h2></div>
        @if ($this->recent->isEmpty())
            <p class="text-muted text-sm">{{ __('moderation.admin.recent_empty') }}</p>
        @else
            <div class="pay-table-wrap">
                <table class="pay-table" data-sortable>
                    <tbody>
                        @foreach ($this->recent as $flag)
                            <tr wire:key="reviewed-{{ $flag->id }}">
                                <td class="font-mono">{{ $flag->reviewed_at->format('d/m/Y') }}</td>
                                <td>{{ $flag->business?->name }}</td>
                                <td>{{ __('moderation.sources.'.$flag->source) }}</td>
                                <td>{{ __('moderation.severity.'.$flag->severity->value) }}</td>
                                <td class="text-muted">{{ __('moderation.admin.reviewed_by', ['name' => $flag->reviewer?->name ?? '—']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>
</div>

@script
    <script>
        // Lifting wakes an assistant that was silenced for a policy breach: one question first.
        Alpine.data('adminModeration', () => ({
            async confirmLift(id, business) {
                if (
                    !(await dialog.confirm({
                        title: @js(__('moderation.admin.lift_title')).replace(':business', business),
                        message: @js(__('moderation.admin.lift_message')),
                        accept: @js(__('moderation.admin.lift')),
                        type: 'warning',
                    }))
                ) {
                    return;
                }

                await this.$wire.lift(id);
            },
        }));
    </script>
@endscript
