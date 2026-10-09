<?php

declare(strict_types=1);
use App\Ai\Tools\BookCustomerAppointment;
use App\Ai\Tools\CheckBusinessHours;
use App\Ai\Tools\CheckFreeSlots;
use App\Ai\Tools\EscalateToHuman;
use App\Ai\Tools\GetBusinessContact;
use App\Ai\Tools\ManageCustomerAppointments;
use App\Ai\Tools\OwnerAppointments;
use App\Ai\Tools\OwnerBirthdays;
use App\Ai\Tools\OwnerConversations;
use App\Ai\Tools\OwnerFreeSlots;
use App\Ai\Tools\OwnerPlanUsage;
use App\Ai\Tools\OwnerStatistics;
use App\Ai\Tools\OwnerTeamStatus;
use App\Ai\Tools\PanelGuide;
use App\Ai\Tools\RememberCustomerFact;
use App\Ai\Tools\SearchBusinessKnowledge;
use App\Ai\Tools\SearchCatalog;
use App\Ai\Tools\SendCatalogPhotos;
use App\Classes\Report\AiSpendReport;
use App\Classes\Report\CompanyReport;
use App\Classes\Search\ConversationSource;
use App\Classes\Search\CustomerSource;
use App\Classes\Search\HelpSource;
use App\Classes\Search\KnowledgeSource;
use App\Classes\Search\ProductSource;
use App\Classes\Search\ScreenSource;
use App\Classes\Search\ServiceSource;

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
    | Digits only, with country code (e.g. 5491100000000). Powers the Premium
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
    | Seasons
    |--------------------------------------------------------------------------
    |
    | seasonal_timezone: the ONE clock a seasonal window is read against. The
    | app runs on UTC and visitors arrive from anywhere, so without a fixed
    | reference a season would start at a different hour for each of them.
    | Editable from Admin → Ajustes once it is worth a row there.
    |
    */

    'seasonal_timezone' => env('SEASONAL_TIMEZONE', 'America/Argentina/Buenos_Aires'),

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
    | Content moderation of what a business uploads or writes
    |--------------------------------------------------------------------------
    |
    | severe_score: an adult-content score this sure suspends the business on
    | the spot; below it a flagged image is only refused and the admin reviews
    | (a lingerie catalog can trip the filter). Minors always suspend.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Reports (PDF, Excel, CSV)
    |--------------------------------------------------------------------------
    |
    | key → the App\Interfaces\Main\Report that builds its content. Every
    | Imprimir / Excel / CSV button asks for one of these keys; the format is
    | the exporter's job (App\Enums\ReportFormat).
    |
    */

    'reports' => [
        'company' => CompanyReport::class,
        'ai-spend' => AiSpendReport::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Búsqueda global (Ctrl/⌘ + K)
    |--------------------------------------------------------------------------
    |
    | Where the palette looks, in the order the groups are shown. The order is
    | the contract with the eye: groups never reorder while typing, so a place
    | learned once stays learned. Each class is an App\Interfaces\Main\SearchSource
    | and answers two lanes — words and meaning — which GlobalSearch fuses.
    |
    */

    'search' => [
        'sources' => [
            'screens' => ScreenSource::class,
            'services' => ServiceSource::class,
            'products' => ProductSource::class,
            'customers' => CustomerSource::class,
            'conversations' => ConversationSource::class,
            'knowledge' => KnowledgeSource::class,
            'help' => HelpSource::class,
        ],

        'per_group' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Catalog photos
    |--------------------------------------------------------------------------
    |
    | disk: `public` (this server) until the DigitalOcean Space exists; then
    | CATALOG_DISK=spaces moves new photos there with no code change. Sizes:
    | 1080 px JPEG q80 is what WhatsApp shows sharp (~150 KB), plus a 320 px
    | WebP thumbnail for the panel; the original is never kept.
    |
    */

    'catalog' => [
        'disk' => env('CATALOG_DISK', 'public'),
        'max_side' => 1080,
        'jpeg_quality' => 80,
        'thumb_side' => 320,
        'photos_per_reply' => 5,
    ],

    'moderation' => [
        'severe_score' => 0.9,
        // Refused images that, together, suspend even when none alone was severe.
        'repeat_limit' => 3,
        'repeat_days' => 7,
    ],

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
        'currency_symbol' => '$',
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

    // The alert threshold is NOT here: it rides each plan row
    // (`plans.ai_alert_share`), because the same 40 USD of AI is a rounding
    // error on Premium and a loss on the floor plan. Read it by `Plan`.

    // Hours a report may wait for our answer before the support queue paints it:
    // a problem (high) waits less than a question (normal); an idea runs no clock.
    'support' => [
        'overdue_hours' => 24,
        'overdue_hours_high' => 8,
    ],

    // Second step for the platform's own staff (admin and support). The grace
    // runs from `since` or from the account's creation, whichever is later, so
    // nobody is locked out on the day the rule is born. Zero days = not required.
    'security' => [
        'staff_two_factor' => [
            'grace_days' => 7,
            'since' => '2026-10-09',
        ],
    ],

    // Days an account may sit on each step of the adoption ladder before it
    // counts as stalled; before that it is still getting started. Per step:
    // making the business is a few minutes, connecting WhatsApp takes a phone.
    'adoption' => [
        'stall_days' => [
            'registered' => 3,
            'business' => 7,
            'catalog' => 7,
            'whatsapp' => 10,
            'conversation' => 7,
        ],
    ],

    // What the platform costs a month apart from the AI (server, WhatsApp,
    // domain), in USD. Zero means "not loaded", never "free": the net result
    // is not shown until it is filled in from the platform settings.
    'costs' => [
        'fixed_monthly_usd' => 0,
    ],

    'ai_rates' => [
        'prompt_per_million' => env('AI_RATE_PROMPT'),
        'cached_per_million' => env('AI_RATE_CACHED'),
        'completion_per_million' => env('AI_RATE_COMPLETION'),
        'embedding_per_million' => env('AI_RATE_EMBEDDING', 0.02),
        'audio_per_minute' => env('AI_RATE_AUDIO', 0.003),
    ],

    /*
    |--------------------------------------------------------------------------
    | Handoff — the forgotten thread
    |--------------------------------------------------------------------------
    | Grace minutes waiting for the team before re-pinging the owner and
    | telling the customer, once, that a person is on the way.
    */

    'team' => [
        // Days an "Equipo" invitation link stays valid; resending restarts it.
        'invitation_days' => env('TEAM_INVITATION_DAYS', 7),
    ],

    'bell' => [
        // Days a panel notice stays in the inbox before it is pruned. Past it
        // the row is history: what it pointed at was solved or is long gone.
        'keep_days' => env('BELL_KEEP_DAYS', 60),
    ],

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

    // Automated sends go out at these LOCAL times, on each business's clock
    // (they went out in UTC: birthdays at 05:15 in Caracas). The scheduler
    // ticks every 15 minutes. Moves to the admin settings later.
    'schedule' => [
        'birthday_greetings' => '09:15',
        'whatsapp_digest' => '20:30',
        'billing_cycle' => '09:00',
        // The day-before nudge travels this many hours before the slot.
        'appointment_reminder_hours' => 24,
        'knowledge_digest' => ['weekday' => 1, 'time' => '09:30'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Incidents
    |--------------------------------------------------------------------------
    |
    | unanswered_minutes: how long a customer's last word may sit with no reply
    | before the admin desk calls it a failure. Short enough to still be worth
    | saving the conversation, long enough not to flag a reply in flight.
    | She turns it from Admin → Ajustes (`incidents.unanswered_minutes`).
    |
    */

    'incidents' => [
        'unanswered_minutes' => env('INCIDENTS_UNANSWERED_MINUTES', 15),
        // When the day's incidents reach her by mail. Evening on purpose: it
        // is a read-and-plan list, not an alarm — an alarm would be a push the
        // moment the first customer went unanswered, and that is another tool.
        'digest_time' => env('INCIDENTS_DIGEST_TIME', '20:00'),
    ],

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
            'catalog_photos' => SendCatalogPhotos::class,
            'agenda_slots' => CheckFreeSlots::class,
            'agenda_book' => BookCustomerAppointment::class,
            'agenda_my_appointments' => ManageCustomerAppointments::class,
            'owner_conversations' => OwnerConversations::class,
            'owner_appointments' => OwnerAppointments::class,
            'owner_free_slots' => OwnerFreeSlots::class,
            'owner_birthdays' => OwnerBirthdays::class,
            'owner_statistics' => OwnerStatistics::class,
            'owner_plan' => OwnerPlanUsage::class,
            'owner_team' => OwnerTeamStatus::class,
            'panel_guide' => PanelGuide::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | "Ask AtendIa" — the owner's panel assistant
    |--------------------------------------------------------------------------
    | The panel guide: route name => [menu label key, translation group of the
    | screen, business column that turns the screen on]. The assistant explains
    | a module from the very texts it prints, and only the modules this
    | business has: the third element mirrors the gate of its menu item.
    */

    'owner_assistant' => [
        'guide' => [
            'dashboard' => ['menu.home', 'client.home'],
            'my-business' => ['menu.my_business', 'client.business'],
            'my-services' => ['menu.services', 'client.services'],
            'my-products' => ['menu.products', 'client.products'],
            'assistant' => ['menu.assistant_knowledge', 'client.assistant'],
            'assistant.settings' => ['menu.assistant_settings', 'assistant.handoff'],
            'conversations' => ['menu.conversations', 'client.conversations'],
            'customers' => ['menu.customers', 'client.customers'],
            'agenda' => ['menu.agenda', 'agenda', 'appointments_enabled'],
            'statistics' => ['menu.statistics', 'statistics'],
            'whatsapp' => ['menu.whatsapp', 'whatsapp'],
            'my-plan' => ['menu.plan', 'plan'],
            'my-payments' => ['menu.my_payments', 'billing'],
            'referrals' => ['menu.referrals', 'referrals'],
            'team' => ['menu.team', 'team'],
            'settings' => ['menu.settings', 'settings'],
            'help' => ['menu.help', 'help'],
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

];
