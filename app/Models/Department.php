<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToBusiness;
use Carbon\CarbonInterface;
use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A room of the team (Ventas, Pagos…): the assistant routes a handoff here
 * by reading `routing_hint`, and only its people get the ping.
 */
#[Fillable(['business_id', 'name', 'routing_hint', 'hours', 'sort_order'])]
class Department extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<DepartmentFactory> */
    use HasFactory;

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /** @return HasMany<Conversation, $this> */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /**
     * Who gets the handoff ping right now: members marked available with a
     * number to reach. Empty means the ping falls back to the owner.
     *
     * @return Collection<int, User>
     */
    public function reachableMembers(): Collection
    {
        return $this->users()->where('is_available', true)->whereNotNull('whatsapp')->get();
    }

    /** Null hours means "the business's": this room keeps the shop's schedule. */
    public function usesBusinessHours(): bool
    {
        return $this->hours === null;
    }

    public function isOpenAt(CarbonInterface $at): bool
    {
        if ($this->usesBusinessHours()) {
            return $this->business->isOpenNow();
        }

        $shift = $this->hours[(string) $at->format('w')] ?? null;

        return is_array($shift) && $at->format('H:i') >= $shift[0] && $at->format('H:i') <= $shift[1];
    }

    /**
     * The week in one short line, equal consecutive days folded:
     * "lun a vie · 09:00–18:00, sáb · 08:00–13:00". Null on business hours.
     */
    public function scheduleLabel(): ?string
    {
        if ($this->usesBusinessHours()) {
            return null;
        }

        $names = collect(range(0, 6))->mapWithKeys(fn (int $day): array => [
            $day => mb_strtolower(now()->startOfWeek()->addDays(($day + 6) % 7)->locale(app()->getLocale())->isoFormat('ddd'), 'UTF-8'),
        ]);
        $runs = [];

        // Monday first, the way people read a week.
        foreach ([1, 2, 3, 4, 5, 6, 0] as $day) {
            $shift = $this->hours[(string) $day] ?? null;
            $key = is_array($shift) ? $shift[0].'–'.$shift[1] : null;
            $last = array_key_last($runs);

            if ($key !== null && $last !== null && $runs[$last]['key'] === $key && $runs[$last]['open']) {
                $runs[$last]['to'] = $names[$day];

                continue;
            }

            $runs[] = ['key' => $key, 'open' => $key !== null, 'from' => $names[$day], 'to' => null];
        }

        return collect($runs)
            ->filter(fn (array $run): bool => $run['open'])
            ->map(fn (array $run): string => rtrim(str_replace('.', '', $run['from']).($run['to'] !== null ? ' a '.str_replace('.', '', $run['to']) : '')).' · '.$run['key'])
            ->implode(', ');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['hours' => 'array'];
    }
}
