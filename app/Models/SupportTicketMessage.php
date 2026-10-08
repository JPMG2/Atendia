<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SupportDelivery;
use App\Traits\BelongsToBusiness;
use Database\Factories\SupportTicketMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of what the team said on a report: an answer to the business, or a
 * note to the rest of the team that the business never reads.
 */
#[Fillable(['support_ticket_id', 'business_id', 'user_id', 'body', 'is_internal', 'delivery'])]
class SupportTicketMessage extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<SupportTicketMessageFactory> */
    use HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
            'delivery' => SupportDelivery::class,
        ];
    }

    /** @return BelongsTo<SupportTicket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
