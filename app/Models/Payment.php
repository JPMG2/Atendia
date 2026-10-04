<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Traits\BelongsToBusiness;
use Carbon\Carbon;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/** One payment of a business to Atendia: a money record, never deleted. */
#[Fillable(['business_id', 'subscription_id', 'plan', 'billing_cycle', 'amount', 'currency', 'method', 'reference', 'receipt_path'])]
class Payment extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'period_starts_at' => 'datetime',
            'period_ends_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Receipts waiting for the admin, oldest first: first paid, first verified.
     *
     * @param  Builder<Payment>  $query
     */
    public function scopeAwaitingReview(Builder $query): void
    {
        $query->where('status', PaymentStatus::Pending)->oldest();
    }

    /** How many receipts are waiting for the admin right now. */
    public static function pendingReviewCount(): int
    {
        return self::query()->awaitingReview()->count();
    }

    /**
     * Whole days the oldest waiting receipt has been waiting. A count says how
     * much there is; this says how long someone has been without an answer,
     * which is what turns a number into an urgency. Null when nothing waits.
     */
    public static function oldestPendingAgeInDays(): ?int
    {
        $oldest = self::query()->awaitingReview()->value('created_at');

        return $oldest === null ? null : (int) Carbon::parse($oldest)->diffInDays(now());
    }

    /** Money actually credited in the window: what came in, not what was promised. */
    public static function collectedInTheLastDays(int $days): float
    {
        return (float) self::query()
            ->where('status', PaymentStatus::Paid)
            ->where('paid_at', '>=', now()->subDays($days))
            ->sum('amount');
    }

    /**
     * Waiting receipts that pay for a plan the business is not on yet: a plan
     * change nobody has credited. They are inside `pendingReviewCount()` too —
     * this says how many of those move someone up or down a plan.
     */
    public static function planChangesAwaitingReview(): int
    {
        return self::query()
            ->awaitingReview()
            ->whereHas('subscription', fn (Builder $query): Builder => $query->whereColumn('subscriptions.plan', '!=', 'payments.plan'))
            ->count();
    }

    /**
     * The admin's review desk: receipts waiting, oldest first, with their business.
     *
     * @return Collection<int, Payment>
     */
    public static function reviewQueue(?int $limit = null): Collection
    {
        return self::query()->awaitingReview()->with('business')->when($limit, fn (Builder $query): Builder => $query->limit($limit))->get();
    }

    /**
     * The latest reviewed payments, for the admin's glance.
     *
     * @return Collection<int, Payment>
     */
    public static function recentlyReviewed(int $limit = 20): Collection
    {
        return self::query()->where('status', '!=', PaymentStatus::Pending)->with('business')->latest('updated_at')->limit($limit)->get();
    }

    /** "USD 79,00": money reads the same on every screen and mail. */
    public function formattedAmount(): string
    {
        return $this->currency.' '.number_format((float) $this->amount, 2, ',', '.');
    }
}
