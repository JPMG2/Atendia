<?php

declare(strict_types=1);

namespace App\Classes\Report;

use App\Dto\ReportDto;
use App\Interfaces\Main\Report;
use App\Models\Company;
use App\Models\User;

/** Atendia's own company sheet (admin): the fields that head an invoice, one per row. */
class CompanyReport implements Report
{
    public function authorize(User $user): bool
    {
        return $user->can('access-admin-panel');
    }

    public ReportDto $document {
        get {
            $company = Company::query()->first();

            $rows = collect(['legal_name', 'tax_id', 'address', 'email', 'phone', 'web'])
                ->map(fn (string $field): array => [__('company.fields.'.$field), (string) ($company?->{$field} ?? '—')])
                ->values()
                ->all();

            return new ReportDto(
                title: __('reports.company.title'),
                filename: __('reports.company.filename'),
                columns: [__('reports.company.field'), __('reports.company.value')],
                rows: $rows,
                subtitle: $company?->legal_name,
            );
        }
    }
}
