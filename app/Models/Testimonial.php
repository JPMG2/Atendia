<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TestimonialStatus;
use App\Services\Tenant;
use App\Traits\BelongsToBusiness;
use Database\Factories\TestimonialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One owner's opinion of AtendIa, asked at a success milestone. The landing
 * only ever sees what `published()` returns: approved by the admin AND
 * explicitly consented — real words or nothing (design rule §6).
 */
#[Fillable(['business_id', 'quote', 'rating', 'display_name', 'display_role', 'consent_given_at', 'status'])]
class Testimonial extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<TestimonialFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TestimonialStatus::class,
            'consent_given_at' => 'datetime',
        ];
    }

    /**
     * What the landing may show: approved, consented, newest first. These
     * rows are deliberately PUBLIC content — the owner consented and the
     * admin approved — so the read runs tenant-free through the sanctioned
     * escape: a logged-in owner must see everyone's words, not their own.
     *
     * @return Collection<int, Testimonial>
     */
    public static function published(): Collection
    {
        return app(Tenant::class)->for(null, fn (): Collection => static::query()
            ->where('status', TestimonialStatus::Approved)
            ->whereNotNull('consent_given_at')
            ->latest('id')
            ->get());
    }

    /**
     * The admin's moderation desk: the answered rows, pending first, each
     * with its business. Dismissals are a non-answer and stay out.
     *
     * @return Collection<int, Testimonial>
     */
    public static function moderationQueue(): Collection
    {
        return static::query()
            ->with('business:id,name')
            ->whereNot('status', TestimonialStatus::Dismissed)
            ->orderByRaw("status = 'pending' desc")
            ->latest('id')
            ->get();
    }
}
