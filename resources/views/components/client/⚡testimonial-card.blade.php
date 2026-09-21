<?php

use App\Actions\Business\DismissTestimonialAsk;
use App\Enums\NotificationType;
use App\Livewire\Forms\Client\TestimonialForm;
use App\Traits\HasNotifications;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The milestone ask, shown once ever: when the assistant has held 100
 * conversations or a month has passed connected, the owner is asked for
 * one sentence about AtendIa — with an explicit publication consent.
 */
new class extends Component
{
    use HasNotifications;

    public TestimonialForm $form;

    #[Computed]
    public function due(): bool
    {
        return Auth::user()?->business?->testimonialPromptDue() ?? false;
    }

    public function submit(): void
    {
        $notification = $this->form->save();
        $this->dispatchNotification($notification);

        if ($notification->type !== NotificationType::Error) {
            unset($this->due);
        }
    }

    public function dismiss(): void
    {
        $business = Auth::user()?->business;

        if ($business === null) {
            return;
        }

        app(DismissTestimonialAsk::class)->handle($business);
        unset($this->due);
    }
};
?>

<div>
    @if ($this->due)
        <x-ui.card class="mb-4 p-5">
            <div class="flex items-start gap-4">
                <div class="bg-brand-soft flex size-11 flex-none items-center justify-center rounded-xl" style="color: var(--brand)">
                    <x-icon name="star" :size="22" />
                </div>
                <div class="min-w-0 flex-1">
                    <h2 class="font-display text-strong text-base">{{ __('client.testimonial.title') }}</h2>
                    <p class="text-body mt-1 text-sm">{{ __('client.testimonial.body') }}</p>

                    <div class="mt-3 flex flex-col gap-3">
                        <x-catalog.form-row>
                            <div class="f-full">
                                <x-ui.textarea
                                    name="quote"
                                    :rows="2"
                                    :label="__('client.testimonial.field_quote')"
                                    :placeholder="__('client.testimonial.quote_placeholder')"
                                    wire:model="form.quote"
                                ></x-ui.textarea>
                            </div>
                        </x-catalog.form-row>
                        <x-catalog.form-row>
                            <x-inputsform.combobox
                                span="text"
                                name="rating"
                                :label="__('client.testimonial.field_rating')"
                                :placeholder="__('client.testimonial.rating_placeholder')"
                                :options="[5 => __('client.testimonial.ratings.5'), 4 => __('client.testimonial.ratings.4'), 3 => __('client.testimonial.ratings.3'), 2 => __('client.testimonial.ratings.2'), 1 => __('client.testimonial.ratings.1')]"
                                :value="$form->rating"
                                wire:model="form.rating"
                            />
                        </x-catalog.form-row>
                        <x-ui.checkbox
                            name="consent"
                            :label="__('client.testimonial.field_consent')"
                            :description="__('client.testimonial.consent_hint')"
                            wire:model="form.consent"
                        />
                        <div class="flex flex-wrap items-center gap-2">
                            <x-ui.button variant="primary" size="sm" icon="send" class="data-loading:opacity-50" wire:click="submit">
                                {{ __('client.testimonial.send') }}
                            </x-ui.button>
                            <x-ui.button variant="danger" size="sm" wire:click="dismiss">
                                {{ __('client.testimonial.not_now') }}
                            </x-ui.button>
                        </div>
                    </div>
                </div>
            </div>
        </x-ui.card>
    @endif
</div>
