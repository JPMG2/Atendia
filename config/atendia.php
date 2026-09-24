<?php

declare(strict_types=1);
use App\Ai\Tools\CheckBusinessHours;
use App\Ai\Tools\EscalateToHuman;
use App\Ai\Tools\GetBusinessContact;
use App\Ai\Tools\RememberCustomerFact;
use App\Ai\Tools\SearchBusinessKnowledge;
use App\Ai\Tools\SearchCatalog;

return [

    /*
    |--------------------------------------------------------------------------
    | Email del administrador
    |--------------------------------------------------------------------------
    |
    | Usuario que el AdminUserSeeder promueve al rol "admin" (y degrada a los
    | demás admins). El rol admin NUNCA se asigna por la web pública. Cuando
    | cambie el dominio, actualizar ADMIN_EMAIL en .env y re-correr el seeder.
    |
    */

    'admin_email' => env('ADMIN_EMAIL'),

    /*
    |--------------------------------------------------------------------------
    | Sales WhatsApp
    |--------------------------------------------------------------------------
    |
    | Digits only, with country code (e.g. 5491100000000). Powers the Pro
    | plan's "talk to sales" CTA on the landing; while unset, that CTA
    | falls back to the register route.
    |
    */

    'sales_whatsapp' => env('SALES_WHATSAPP'),

    /*
    |--------------------------------------------------------------------------
    | Local-currency pricing reference
    |--------------------------------------------------------------------------
    |
    | Prices stay in USD (LatAm buyers read USD as stable). Per visitor
    | locale, an optional rate (local units per USD) adds one reference
    | line under the landing pricing. Null rate = no line, nothing shown.
    |
    */

    'pricing_reference' => [
        'es_AR' => ['symbol' => 'AR$', 'rate' => env('PRICING_REFERENCE_RATE_AR')],
        'es_VE' => ['symbol' => 'Bs.', 'rate' => env('PRICING_REFERENCE_RATE_VE')],
    ],

    /*
    |--------------------------------------------------------------------------
    | Landing persuasion knobs
    |--------------------------------------------------------------------------
    |
    | calculator_minutes: assumed owner minutes per answered enquiry, shown
    | as the estimation note. tally_floor: the live weekly-replies line stays
    | hidden until the real number reaches it (a small count un-charms).
    |
    */

    'calculator_minutes' => env('LANDING_CALCULATOR_MINUTES', 3),
    'tally_floor' => env('LANDING_TALLY_FLOOR', 50),

    /*
    |--------------------------------------------------------------------------
    | Account settings
    |--------------------------------------------------------------------------
    |
    | account_restore_days: a closed account (soft-deleted, never erased) can
    | be restored by its owner for this many days. email_change_minutes: the
    | lifetime of the link that confirms a new login email.
    |
    */

    'account_restore_days' => 30,

    /*
    |--------------------------------------------------------------------------
    | Billing ("Mis pagos")
    |--------------------------------------------------------------------------
    |
    | reminder_days: how many days before the payment date the owner is told.
    | grace_days: days past it, reminded daily, before the assistant pauses.
    | Payment-rail agnostic until the fiscal meeting settles the gateway.
    |
    */

    'billing' => [
        'currency' => 'USD',
        'reminder_days' => [10, 5],
        'grace_days' => 5,
    ],
    'email_change_minutes' => 60,

    /*
    |--------------------------------------------------------------------------
    | Landing demo chat
    |--------------------------------------------------------------------------
    |
    | The hero's interactive demo answers with the REAL assistant over the
    | seeded demo business, so every message costs tokens. Session cap is
    | the per-visitor budget before the register invite; daily cap is the
    | global fuse for the whole landing.
    |
    */

    'demo' => [
        'session_cap' => env('DEMO_SESSION_CAP', 4),
        'daily_cap' => env('DEMO_DAILY_CAP', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI rates (USD)
    |--------------------------------------------------------------------------
    |
    | Prices for turning measured usage into money: input/cached/output per
    | MILLION tokens, embeddings per million and audio per minute. Null token
    | rates mean the cost report shows tokens only — set them when the
    | provider's price for the active model is confirmed.
    |
    */

    'ai_rates' => [
        'prompt_per_million' => env('AI_RATE_PROMPT'),
        'cached_per_million' => env('AI_RATE_CACHED'),
        'completion_per_million' => env('AI_RATE_COMPLETION'),
        'embedding_per_million' => env('AI_RATE_EMBEDDING', 0.02),
        'audio_per_minute' => env('AI_RATE_AUDIO', 0.003),
    ],

    /*
    |--------------------------------------------------------------------------
    | Plans (entitlements)
    |--------------------------------------------------------------------------
    |
    | The single source of truth every gate asks. Prices are the working
    | hypothesis until the fiscal meeting; caps are the provisional ones
    | already published on the landing. A business without a subscription
    | falls to the FIRST plan: the floor, never a giveaway.
    |
    */

    'trial' => [
        'plan' => 'negocio',
        'days' => 14,
    ],

    /*
    |--------------------------------------------------------------------------
    | Handoff — the forgotten thread
    |--------------------------------------------------------------------------
    | Grace minutes waiting for the team before re-pinging the owner and
    | telling the customer, once, that a person is on the way.
    */

    'handoff' => [
        'reminder_minutes' => env('HANDOFF_REMINDER_MINUTES', 20),
        // Hours with no human word before the assistant takes the thread
        // back. Null = off: resuming over a human is the owner's call.
        'auto_resume_hours' => env('HANDOFF_AUTO_RESUME_HOURS'),
        // Hours a thread waits on a silent customer before closing itself as
        // resolved: the team answered, nobody replied — that IS a resolution.
        'customer_idle_hours' => env('HANDOFF_CUSTOMER_IDLE_HOURS', 24),
    ],

    /*
    |--------------------------------------------------------------------------
    | Conversation analysis
    |--------------------------------------------------------------------------
    | A thread nobody marked resolved counts as finished after this many quiet
    | hours, and only then is it analyzed. The similarity bars were measured
    | on real vectors (2026-09-24); a proposal becomes its trade's own once
    | that many businesses see it. All move to the admin settings later.
    */

    'analysis' => [
        'idle_hours' => env('ANALYSIS_IDLE_HOURS', 2),
        'same_intent_similarity' => 0.75,
        'catalog_similarity' => 0.58,
        'promote_after_businesses' => 3,
        // Same unanswered question: above the first bar without asking, below
        // the second never; in between the matcher judges.
        'same_question_similarity' => 0.93,
        'question_candidate_similarity' => 0.5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Assistant skills
    |--------------------------------------------------------------------------
    | Skill key (the assistant_skills row) => the tool that performs it. A
    | trade's own skill joins here and in its seeder; which trades get it is
    | data. The catalog search bar is lower than the analysis link on purpose:
    | here the model sees the candidates and judges them itself.
    */

    'assistant' => [
        'catalog_search_similarity' => 0.45,
        'skills' => [
            'catalog' => SearchCatalog::class,
            'hours' => CheckBusinessHours::class,
            'contact' => GetBusinessContact::class,
            'knowledge' => SearchBusinessKnowledge::class,
            'escalate' => EscalateToHuman::class,
            'remember_customer' => RememberCustomerFact::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Referral program ("Gana con AtendIa")
    |--------------------------------------------------------------------------
    |
    | Payment-agnostic on purpose: the reward is a percent off the next
    | invoice, whatever the payment rail turns out to be. These numbers are
    | the launch defaults; the admin dashboard will manage them later.
    |
    */

    'referral' => [
        'reward_percent' => 25,
        'invited_trial_days' => 21,
        'founders' => 10,
    ],

    'plans' => [
        'emprende' => [
            'price' => 29,
            'conversations_per_month' => 300,
            'whatsapp_numbers' => 1,
            'messages_per_hour' => 30,
            'audio_minutes_per_month' => 0,
            'statistics' => 'counts',
        ],
        'negocio' => [
            'price' => 79,
            'conversations_per_month' => 1000,
            'whatsapp_numbers' => 2,
            'messages_per_hour' => 60,
            'audio_minutes_per_month' => 200,
            'statistics' => 'patterns',
        ],
        'premium' => [
            'price' => 149,
            'conversations_per_month' => 3000,
            'whatsapp_numbers' => 4,
            'messages_per_hour' => 120,
            'audio_minutes_per_month' => 600,
            'statistics' => 'trends',
        ],
    ],

];
