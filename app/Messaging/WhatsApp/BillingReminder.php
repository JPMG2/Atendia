<?php

declare(strict_types=1);

namespace App\Messaging\WhatsApp;

use App\Messaging\WhatsAppMessage;
use Illuminate\Database\Eloquent\Model;

/** The same reminder as the mail, in one WhatsApp line to the owner's own phone. */
class BillingReminder extends WhatsAppMessage
{
    public function __construct(Model $model, public string $stage = 'upcoming', public int $days = 0)
    {
        parent::__construct($model);
    }

    public function text(): string
    {
        // loadMissing: the billing pass hands over businesses loaded in a batch,
        // where a lazy load is refused.
        $subscription = $this->model->loadMissing('subscription')->subscription;

        return __("billing.whatsapp.{$this->stage}", [
            'plan' => __('plan.names.'.$subscription?->plan),
            'amount' => config('atendia.billing.currency').' '.number_format((float) $subscription?->nextAmount(), 2, ',', '.'),
            'date' => $subscription?->periodEndsAt()?->format('d/m'),
            'days' => $this->days,
            'url' => route('my-payments'),
        ]);
    }
}
