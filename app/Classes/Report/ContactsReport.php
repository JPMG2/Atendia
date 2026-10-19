<?php

declare(strict_types=1);

namespace App\Classes\Report;

use App\Enums\ReportAudience;
use App\Models\PlatformContact;
use App\Models\User;
use Illuminate\Support\Collection;

/** The people the platform reaches, the ones who talk to more than one business first. */
class ContactsReport extends ListReport
{
    public string $title {
        get => __('reports/admin.contacts.title');
    }

    public string $filename {
        get => __('reports/admin.contacts.filename');
    }

    public array $columns {
        get => [
            __('reports/admin.contacts.phone'),
            __('reports/admin.contacts.name'),
            __('reports/admin.contacts.businesses'),
            __('reports/admin.contacts.conversations'),
            __('reports/admin.contacts.country'),
            __('reports/admin.contacts.last_seen'),
        ];
    }

    public ReportAudience $audience {
        get => ReportAudience::Platform;
    }

    public int $total {
        get => PlatformContact::reach()['people'];
    }

    public function authorize(User $user): bool
    {
        return $user->can('contacts.view');
    }

    protected function rows(ReportScope $scope): array
    {
        return $this->people()
            ->map(fn (PlatformContact $person): array => [
                $person->phone,
                (string) $person->name,
                (int) $person->businesses_count,
                (int) $person->conversations_count,
                (string) $person->country_code,
                (string) $person->last_activity_at?->format('d/m/Y H:i'),
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

    /** @return Collection<int, PlatformContact> */
    private function people(): Collection
    {
        return PlatformContact::radar(limit: PHP_INT_MAX);
    }
}
