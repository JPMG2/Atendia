<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\Enums\TestimonialStatus;
use App\Models\Business;
use App\Models\Testimonial;

/**
 * "Ahora no" is an answer too: the dismissal is stored so the milestone
 * card never nags the same owner twice.
 */
class DismissTestimonialAsk
{
    public function handle(Business $business): void
    {
        Testimonial::query()->firstOrCreate(
            ['business_id' => $business->id],
            ['display_name' => $business->name, 'status' => TestimonialStatus::Dismissed],
        );
    }
}
