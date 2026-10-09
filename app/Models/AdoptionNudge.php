<?php

declare(strict_types=1);

namespace App\Models;

use App\Dto\AdoptionRowDto;
use App\Enums\AdoptionStep;
use App\Interfaces\Catalog\DataTable;
use App\Traits\TracksUserActions;
use Database\Factories\AdoptionNudgeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

#[Fillable(['step', 'subject', 'body', 'is_active'])]
class AdoptionNudge extends Model implements DataTable
{
    /** @use HasFactory<AdoptionNudgeFactory> */
    use HasFactory;

    // A master row is never deleted: the audit trail may point at it.
    use SoftDeletes;
    use TracksUserActions;

    /** A mailto: link is read by a mail client, which truncates long ones. */
    public const BODY_MAX = 900;

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'step' => AdoptionStep::class,
            'is_active' => 'boolean',
        ];
    }

    public static function normalizeSubject(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    /** Line breaks are the writer's paragraphs; only the outer blanks go. */
    public static function normalizeBody(string $value): string
    {
        return trim($value);
    }

    protected function subject(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => self::normalizeSubject($value));
    }

    protected function body(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => self::normalizeBody($value));
    }

    /**
     * The steps a message can be written for: every one where an account can
     * still be stuck. The last one has nobody to write to.
     *
     * @return array<string, string> value => label
     */
    public static function stepOptions(): array
    {
        return collect(AdoptionStep::cases())
            ->reject(fn (AdoptionStep $step): bool => $step === AdoptionStep::AssistantAnswered)
            ->mapWithKeys(fn (AdoptionStep $step): array => [$step->value => $step->label()])
            ->all();
    }

    /**
     * @return Collection<int, array{id: int, step: string, stepLabel: string, subject: string, body: string, active: bool}>
     */
    public function catalogRows(): Collection
    {
        return $this->newQuery()
            ->get()
            ->sortBy(fn (self $nudge): int => $nudge->step->position())
            ->map(fn (self $nudge): array => [
                'id' => $nudge->id,
                'step' => $nudge->step->value,
                'stepLabel' => $nudge->step->label(),
                'subject' => $nudge->subject,
                'body' => $nudge->body,
                'active' => $nudge->is_active,
            ])
            ->values();
    }

    /**
     * The link that opens a mail to the owner of an account stuck where the row
     * is, with the message she wrote for that step. Null when nothing is written
     * or it is switched off: a button that opens an empty mail is worse than none.
     */
    public static function mailtoFor(AdoptionRowDto $row): ?string
    {
        return self::mailtosFor([$row])[$row->email] ?? null;
    }

    /**
     * The same link for a whole list, in one query: a screen of N accounts must
     * not cost N lookups for N copies of five messages.
     *
     * @param  iterable<int, AdoptionRowDto>  $rows
     * @return array<string, string> email => link
     */
    public static function mailtosFor(iterable $rows): array
    {
        $byStep = self::query()->where('is_active', true)->get()->keyBy(fn (self $nudge): string => $nudge->step->value);
        $links = [];

        foreach ($rows as $row) {
            $nudge = $byStep->get($row->step->value);

            if ($nudge === null) {
                continue;
            }

            $words = ['{nombre}' => $row->owner, '{negocio}' => $row->business ?? ''];

            $links[$row->email] = 'mailto:'.rawurlencode($row->email)
                .'?subject='.rawurlencode(strtr($nudge->subject, $words))
                .'&body='.rawurlencode(strtr($nudge->body, $words));
        }

        return $links;
    }
}
