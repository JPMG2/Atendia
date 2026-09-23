<?php

declare(strict_types=1);

namespace App\Messaging\WhatsApp;

use App\Messaging\WhatsAppMessage;
use Illuminate\Database\Eloquent\Model;

/** The six-digit code on WhatsApp, for the login gate and for proving the number. */
class LoginCode extends WhatsAppMessage
{
    public function __construct(Model $model, public string $code = '')
    {
        parent::__construct($model);
    }

    public function text(): string
    {
        return __('security.whatsapp_code', ['code' => $this->code]);
    }
}
