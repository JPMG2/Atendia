<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Who answered a customer's question in the end. Measured per question, not
 * per thread: three answered out of four is not a failed conversation.
 */
enum QuestionResolution: string
{
    case Assistant = 'assistant';
    case Team = 'team';
    case Nobody = 'nobody';
}
