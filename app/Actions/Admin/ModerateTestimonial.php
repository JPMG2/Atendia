<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\TestimonialStatus;
use App\Models\Testimonial;

/**
 * The admin's hand on an owner's word. Approval is refused without the
 * owner's publication consent: the landing can never show a quote its
 * author did not sign off — whatever button the screen offered.
 */
class ModerateTestimonial
{
    public function handle(int $id, TestimonialStatus $status): ?Testimonial
    {
        $testimonial = Testimonial::query()->find($id);

        if ($testimonial === null) {
            return null;
        }

        if ($status === TestimonialStatus::Approved && $testimonial->consent_given_at === null) {
            return null;
        }

        $testimonial->update(['status' => $status]);

        return $testimonial;
    }
}
