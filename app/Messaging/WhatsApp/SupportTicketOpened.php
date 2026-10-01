<?php

declare(strict_types=1);

namespace App\Messaging\WhatsApp;

use App\Messaging\WhatsAppMessage;
use App\Models\Menu;
use Illuminate\Support\Str;

/**
 * The same notice as the mail, short enough to read on a phone without
 * opening anything: who, what kind, where, and the first lines of the report.
 */
class SupportTicketOpened extends WhatsAppMessage
{
    public function text(): string
    {
        $screen = $this->model->screen !== null
            ? (Menu::titleFor($this->model->screen) ?? $this->model->screen)
            : __('support.no_screen');

        return __('support.whatsapp', [
            'code' => $this->model->code,
            'business' => $this->model->business?->name,
            'kind' => __('support.kinds.'.$this->model->kind->value),
            'screen' => $screen,
            'body' => Str::limit($this->model->body, 280),
        ]);
    }
}
