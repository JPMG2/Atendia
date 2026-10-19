<?php

declare(strict_types=1);

namespace App\Classes\Report;

use App\Dto\ReportDto;
use App\Enums\ReportAudience;
use App\Models\Business;
use App\Models\Company;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Who a document is from and who it is about, written once for every format:
 * Aliadito's brand and slogan from the Company row (never typed), and the
 * client's own data beneath. A field the client never filled is left out, so a
 * document never prints a row of dashes.
 */
final class ReportLetterhead
{
    public string $brand {
        get => Company::brand();
    }

    public string $tagline {
        get => (string) ($this->company?->tagline ?? '');
    }

    /** The brand mark as a data URI: dompdf reads no files off the public disk by URL. */
    public string $logo {
        get => $this->logoDataUri();
    }

    /** Footer line: the brand, its slogan and the site. */
    public string $contactLine {
        get => collect([
            $this->brand,
            $this->tagline,
            Str::of((string) ($this->company?->web ?? ''))->after('://')->before('/')->replaceStart('www.', '')->toString(),
        ])->filter()->implode(' · ');
    }

    /** "Cliente" for a business's report, "Emitido por" for the platform's own. */
    public string $subjectLabel {
        get => $this->audience === ReportAudience::Client ? __('reports.letterhead.client') : __('reports.letterhead.issuer');
    }

    public string $subjectName {
        get => $this->audience === ReportAudience::Client
            ? (string) ($this->business?->name ?? '')
            : (string) ($this->company?->legal_name ?: $this->brand);
    }

    /** @var list<array{label: string, value: string}> */
    public array $subjectLines {
        get => $this->audience === ReportAudience::Client ? $this->businessLines() : $this->companyLines();
    }

    /** "19/10/2026 · 14:32", on the clock of the business the document belongs to. */
    public string $generatedAt {
        get => $this->at->format('d/m/Y · H:i');
    }

    /** "America/Caracas (UTC-04:00)": a printed hour means little without its zone. */
    public string $timezoneLabel {
        get => $this->at->getTimezone()->getName().' (UTC'.$this->at->format('P').')';
    }

    private function __construct(
        public readonly ReportAudience $audience,
        private readonly ?Company $company,
        private readonly ?Business $business,
        private readonly CarbonImmutable $at,
    ) {}

    public static function for(ReportDto $report, ?User $user): self
    {
        return new self(
            $report->audience,
            Company::current(),
            $report->audience === ReportAudience::Client ? $user?->business : null,
            now()->inBusinessTime(),
        );
    }

    /** @return list<array{label: string, value: string}> */
    private function businessLines(): array
    {
        $business = $this->business;

        if ($business === null) {
            return [];
        }

        return $this->present([
            [__('reports.letterhead.tax_id'), (string) $business->tax_id],
            [__('reports.letterhead.address'), collect([$business->address, $business->city])->filter()->implode(', ')],
            [__('reports.letterhead.phone'), (string) $business->whatsapp_number],
            [__('reports.letterhead.plan'), __('plan.names.'.$business->plan()->code)],
        ]);
    }

    /** @return list<array{label: string, value: string}> */
    private function companyLines(): array
    {
        return $this->present([
            [__('reports.letterhead.tax_id'), (string) ($this->company?->tax_id ?? '')],
            [__('reports.letterhead.address'), (string) ($this->company?->address ?? '')],
            [__('reports.letterhead.phone'), (string) ($this->company?->phone ?? '')],
        ]);
    }

    /**
     * Keeps only the lines that have something to say.
     *
     * @param  list<array{0: string, 1: string}>  $pairs
     * @return list<array{label: string, value: string}>
     */
    private function present(array $pairs): array
    {
        return collect($pairs)
            ->filter(fn (array $pair): bool => trim($pair[1]) !== '')
            ->map(fn (array $pair): array => ['label' => $pair[0], 'value' => trim($pair[1])])
            ->values()
            ->all();
    }

    private function logoDataUri(): string
    {
        $path = $this->company?->logo_path_light;
        $svg = $path !== null && Storage::disk('public')->exists($path) && str_ends_with(strtolower($path), '.svg')
            ? Storage::disk('public')->get($path)
            : file_get_contents(public_path('assets/logo-mark-color.svg'));

        return 'data:image/svg+xml;base64,'.base64_encode((string) $svg);
    }
}
