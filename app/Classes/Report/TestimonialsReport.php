<?php

declare(strict_types=1);

namespace App\Classes\Report;

use App\Enums\ReportAudience;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Support\Collection;

/** What the owners said about the platform, with whether they agreed to publish it and where it stands. */
class TestimonialsReport extends ListReport
{
    public string $title {
        get => __('reports/admin.testimonials.title');
    }

    public string $filename {
        get => __('reports/admin.testimonials.filename');
    }

    public array $columns {
        get => [
            __('reports/admin.testimonials.date'),
            __('reports/admin.testimonials.business'),
            __('reports/admin.testimonials.role'),
            __('reports/admin.testimonials.quote'),
            __('reports/admin.testimonials.rating'),
            __('reports/admin.testimonials.consent'),
            __('reports/admin.testimonials.status'),
        ];
    }

    public ReportAudience $audience {
        get => ReportAudience::Platform;
    }

    public int $total {
        get => $this->rowsOf()->count();
    }

    public function authorize(User $user): bool
    {
        return $user->can('testimonials.moderate');
    }

    protected function rows(ReportScope $scope): array
    {
        return $this->rowsOf()
            ->map(fn (Testimonial $testimonial): array => [
                $testimonial->created_at->format('d/m/Y'),
                $testimonial->display_name,
                (string) $testimonial->display_role,
                (string) $testimonial->quote,
                $testimonial->rating !== null ? (int) $testimonial->rating : '',
                $testimonial->consent_given_at !== null ? __('reports/admin.yes') : __('reports/admin.no'),
                __('reports/admin.testimonials.statuses.'.$testimonial->status->value),
            ])
            ->all();
    }

    protected function shown(ReportScope $scope): int
    {
        return $this->total;
    }

    protected function filters(ReportScope $scope): array
    {
        return [];
    }

    /** @return Collection<int, Testimonial> */
    private function rowsOf(): Collection
    {
        return Testimonial::moderationQueue();
    }
}
