<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Models\Company;
use App\Models\SocialLink;

/**
 * The landing's footer as the saved company makes it, to see on the screen
 * that edits it. Read through the same functions the footer reads (`reach`,
 * `contact`, `current`), so what it shows is what a visitor gets today,
 * season and "open now" included.
 */
class CompanyFooterPreview
{
    /**
     * @return array{title: string, sub: string, tagline: string, networks: list<array{name: string, icon: string}>, lines: list<array{icon: string, text: string, tone: string}>, copyright: string}
     */
    public function toArray(): array
    {
        $company = Company::current();
        $reach = Company::reach();
        $place = Company::contact()['place'];
        $lines = [];

        if ($reach['phone']) {
            $lines[] = ['icon' => 'message-circle', 'text' => __('landing.footer.whatsapp'), 'tone' => 'body'];

            if ($reach['season']) {
                $lines[] = ['icon' => 'calendar', 'text' => __('landing.footer.season', ['name' => $reach['season']]), 'tone' => 'muted'];
            }

            if ($reach['notice']) {
                $lines[] = ['icon' => 'megaphone', 'text' => $reach['notice'], 'tone' => 'muted'];
            }

            if ($reach['hours']) {
                $lines[] = ['icon' => 'clock', 'text' => $reach['hours'], 'tone' => 'muted'];
            }

            if ($reach['open'] !== null) {
                $lines[] = ['icon' => 'circle', 'text' => $reach['open'] ? __('landing.footer.open_now') : __('landing.footer.closed_now'), 'tone' => $reach['open'] ? 'success' : 'muted'];
            }
        }

        if ($company?->email) {
            $lines[] = ['icon' => 'mail', 'text' => $company->email, 'tone' => 'body'];
        }

        if ($company?->address) {
            $lines[] = ['icon' => 'map-pin', 'text' => trim($company->address.($place ? ', '.$place : '')), 'tone' => 'body'];
        }

        return [
            'title' => __('company.preview.title'),
            'sub' => __('company.preview.sub'),
            'tagline' => $company?->tagline ?: __('landing.footer.tagline'),
            // Asked fresh: the networks are saved after the row, so a memoised copy may predate them.
            'networks' => ($company?->socialLinks()->with('socialNetwork')->get() ?? collect())
                ->map(fn (SocialLink $link): array => ['name' => (string) $link->socialNetwork?->name, 'icon' => $link->socialNetwork?->icon ?: 'link'])
                ->values()
                ->all(),
            'lines' => $lines,
            'copyright' => '© '.date('Y').' '.($company?->legal_name ?: Company::brand()).'. '.($company?->text_copyright ?: __('landing.footer.copyright')),
        ];
    }
}
