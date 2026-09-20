<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Who wrote an outgoing message: the assistant, or a human from the panel.
 * The screen tags human bubbles and the metrics will split them apart.
 */
enum MessageAuthor: string
{
    case Assistant = 'assistant';
    case Human = 'human';
}
