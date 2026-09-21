<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Client;

use App\Dto\DtoCast;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use App\Models\Testimonial;
use Illuminate\Support\Facades\Auth;

/**
 * The milestone ask: one sentence about AtendIa, an optional rating and an
 * EXPLICIT publication consent. Without the consent the word still lands —
 * as internal feedback that can never reach the landing.
 */
class TestimonialForm extends BaseForm
{
    public string $quote = '';

    public ?int $rating = null;

    public bool $consent = false;

    public function save(): NotificationDto
    {
        $business = Auth::user()?->business;

        if ($business === null) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        $validated = $this->validateServiceData();

        return $this->tryAction(function () use ($business, $validated): NotificationDto {

            Testimonial::query()->create([
                'business_id' => $business->id,
                'quote' => $validated['quote'],
                'rating' => $validated['rating'],
                'display_name' => $business->name,
                'display_role' => $business->primaryActivity()?->name,
                'consent_given_at' => $validated['consent'] ? now() : null,
            ]);

            return new NotificationDto(__('client.testimonial.thanks'), NotificationType::Success);

        }, __('notifications.not_created'));
    }

    protected function transformServiceData(): array
    {
        return [
            'quote' => DtoCast::squish($this->quote) ?? '',
            'rating' => $this->rating,
            'consent' => $this->consent,
        ];
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return [
            'quote' => ['required', 'string', 'min:10', 'max:400'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'consent' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function getValidationAttributes(): array
    {
        return [
            'quote' => __('client.testimonial.field_quote'),
            'rating' => __('client.testimonial.field_rating'),
            'consent' => __('client.testimonial.field_consent'),
        ];
    }
}
