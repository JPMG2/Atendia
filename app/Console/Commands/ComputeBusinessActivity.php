<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Admin\RankBusinessActivity;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Feeds the admin home's "most movement" card; see {@see RankBusinessActivity}. */
#[Signature('atendia:business-activity')]
#[Description('Ranks the businesses with the most WhatsApp and AI movement for the admin home')]
class ComputeBusinessActivity extends Command
{
    public function handle(RankBusinessActivity $rank): int
    {
        $figure = $rank->handle();

        $this->info(sprintf(
            'Ranked %d by WhatsApp and %d by AI over %d days.',
            count($figure->payload['whatsapp']),
            count($figure->payload['ai']),
            $figure->payload['days'],
        ));

        return self::SUCCESS;
    }
}
