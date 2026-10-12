<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Models\Company;
use Illuminate\Support\Collection;

/**
 * The /llms.txt that tells an AI assistant what the product is, for whom and
 * for how much.
 *
 * Every figure and name is read when it is asked for (plans table, company
 * row), so the day the brand or a price changes the file changes with it and
 * nobody has to remember to edit a text.
 */
final class LlmsTxt
{
    public string $text {
        get {
            $brand = Company::brand();
            $trial = Plan::trial();

            $lines = [
                "# {$brand}",
                '',
                '> '.__('landing.hero.subtitle', ['brand' => $brand]),
                '',
                __('llms.audience'),
                '',
                '## '.__('llms.plans.title'),
                '',
                __('llms.plans.currency', ['currency' => config('atendia.billing.currency')]),
                __('landing.pricing.subtitle', ['days' => $trial->trialDays, 'plan' => __('plan.names.'.$trial->code)]),
                '',
                ...$this->planLines,
                '',
                '## '.__('llms.links.title'),
                '',
                ...$this->linkLines,
            ];

            if ($this->contactLines->isNotEmpty()) {
                array_push($lines, '', '## '.__('llms.contact.title'), '', ...$this->contactLines->all());
            }

            return implode("\n", $lines)."\n";
        }
    }

    /** @var list<string> */
    private array $planLines {
        get {
            $symbol = (string) config('atendia.billing.currency_symbol');

            return collect(Plan::ladder())->map(function (Plan $plan) use ($symbol): string {
                $line = __('llms.plans.line', [
                    'name' => __('plan.names.'.$plan->code),
                    'price' => $symbol.$plan->price,
                    'yearly' => $symbol.$plan->annualMonthlyPrice,
                    'desc' => __("landing.pricing.{$plan->code}.desc"),
                ]);

                $includes = collect($plan->features)->pluck('label')->implode('; ');

                return '- '.$line.' '.__('llms.plans.includes', ['lines' => $includes]);
            })->all();
        }
    }

    /** @var list<string> */
    private array $linkLines {
        get => [
            '- ['.__('llms.links.home').']('.url('/').'): '.__('llms.links.home_note'),
            '- ['.__('llms.links.register').']('.route('register').'): '.__('llms.links.register_note'),
            '- ['.__('llms.links.login').']('.route('login').'): '.__('llms.links.login_note'),
        ];
    }

    /** @var Collection<int, string> */
    private Collection $contactLines {
        get {
            $company = Company::current();
            $whatsapp = Company::whatsapp();

            return collect([
                filled($company?->email) ? '- '.__('llms.contact.email').': '.$company->email : null,
                $whatsapp !== null ? '- '.__('llms.contact.whatsapp').': https://wa.me/'.$whatsapp : null,
                filled($company?->web) ? '- '.__('llms.contact.web').': '.$company->web : null,
            ])->filter()->values();
        }
    }
}
