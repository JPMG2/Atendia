<?php

declare(strict_types=1);

namespace App\Enums;

/** Whose document it is: a business's own report, or one the platform issues about itself. */
enum ReportAudience: string
{
    /** Printed for a business: its data heads the page, under Aliadito's letterhead. */
    case Client = 'client';

    /** Printed for the platform's admin: Aliadito is the issuer and there is no client block. */
    case Platform = 'platform';
}
