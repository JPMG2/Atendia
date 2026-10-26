<?php

declare(strict_types=1);
use App\Ai\Tools\AdminAlerts;
use App\Ai\Tools\AdminBusinessFile;
use App\Ai\Tools\AdminFindBusiness;
use App\Ai\Tools\AdminPaymentQueue;
use App\Ai\Tools\AdminPlatformPulse;
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
use App\Classes\Report\AdoptionReport;
use App\Classes\Report\AgendaReport;
use App\Classes\Report\AiModelsReport;
use App\Classes\Report\AiQualityReport;
use App\Classes\Report\AiSpendReport;
use App\Classes\Report\AlertsReport;
use App\Classes\Report\AuditReport;
use App\Classes\Report\BusinessesReport;
use App\Classes\Report\CatalogReport;
use App\Classes\Report\CollectionsReport;
use App\Classes\Report\CompanyReport;
use App\Classes\Report\ComplianceReport;
use App\Classes\Report\ContactsReport;
use App\Classes\Report\ConversationsReport;
use App\Classes\Report\CustomersReport;
use App\Classes\Report\IncidentsReport;
use App\Classes\Report\MentionsReport;
use App\Classes\Report\ModerationReport;
use App\Classes\Report\PaymentsReport;
use App\Classes\Report\PlatformPaymentsReport;
use App\Classes\Report\ProductsReport;
use App\Classes\Report\ServicesReport;
use App\Classes\Report\StaffReport;
use App\Classes\Report\SupportReport;
use App\Classes\Report\TeamReport;
use App\Classes\Report\TestimonialsReport;
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

    /*
    | Starting points for a new staff role in the role editor: the screens a
    | job usually opens. They only preload the switches; nothing is saved
    | until the role is.
    */
    'role_templates' => [
        'support' => ['support.view', 'businesses.view'],
        'billing' => ['payments.view', 'payments.verify', 'businesses.view'],
        'moderation' => ['moderation.view', 'testimonials.moderate', 'mentions.manage'],
    ],

    'reports' => [
        'company' => CompanyReport::class,
        'ai-spend' => AiSpendReport::class,
        'clientes' => CustomersReport::class,
        'conversaciones' => ConversationsReport::class,
        'agenda' => AgendaReport::class,
        'servicios' => ServicesReport::class,
        'productos' => ProductsReport::class,
        'equipo' => TeamReport::class,
        'pagos' => PaymentsReport::class,
        'negocios' => BusinessesReport::class,
        'adopcion' => AdoptionReport::class,
        'incidencias' => IncidentsReport::class,
        'radar' => ContactsReport::class,
        'cumplimiento' => ComplianceReport::class,
        'cobranza' => CollectionsReport::class,
        'cobros' => PlatformPaymentsReport::class,
        'calidad-ia' => AiQualityReport::class,
        'moderacion' => ModerationReport::class,
        'testimonios' => TestimonialsReport::class,
        'soporte' => SupportReport::class,
        'usuarios' => StaffReport::class,
        'auditoria' => AuditReport::class,
        'avisos' => AlertsReport::class,
        'menciones' => MentionsReport::class,
        'modelos-ia' => AiModelsReport::class,
        'catalogo' => CatalogReport::class,
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
        // 1 = the AI prepares a draft and a reading of each new report for the team; 0 = off.
        'ai_suggestions' => 1,
    ],

    // Second step for the platform's own staff (admin and support). The grace
    // runs from `since` or from the account's creation, whichever is later, so
    // nobody is locked out on the day the rule is born. Zero days = not required.
    'security' => [
        // Minutes without a real action before an admin or support session
        // ends; remember-me never revives it. Zero = no limit.
        'staff_idle_minutes' => 20,
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

    // Messages a business starts on its own (reminders, greetings) are the ones
    // WhatsApp reads as spam, so they are paced: a pause between sends, a daily
    // cap that grows while the number is young (days linked => cap) and a
    // stop after this many unanswered ones in a row. Replies are never counted.
    'outbound' => [
        'gap_seconds' => [(int) env('OUTBOUND_GAP_MIN', 4), (int) env('OUTBOUND_GAP_MAX', 12)],
        'max_unanswered' => 2,
        'daily_cap' => 150,
        'warmup' => [7 => 20, 14 => 40, 28 => 80],
    ],

    // Automated sends go out at these LOCAL times, on each business's clock
    // (they went out in UTC: birthdays at 05:15 in Caracas). The scheduler
    // ticks every 15 minutes. Moves to the admin settings later.
    'schedule' => [
        'birthday_greetings' => '09:15',
        'whatsapp_digest' => '20:30',
        'billing_cycle' => '09:00',
        // The platform's own clock is the company's country. Admin sends leave
        // every day; a customer-facing one rests on Sunday (Carbon: 0 = Sunday).
        'admin_platform_digest' => '20:00',
        'admin_adoption_alert' => '09:00',
        'admin_staff_two_factor' => '09:05',
        'admin_ai_alert' => ['weekday' => 1, 'time' => '09:30'],
        'customer_days' => [1, 2, 3, 4, 5, 6],
        // Nothing automated lands outside these local hours, on either side.
        'send_window' => ['from' => '08:00', 'until' => '21:00'],
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
            'admin_pulse' => AdminPlatformPulse::class,
            'admin_find_business' => AdminFindBusiness::class,
            'admin_business_file' => AdminBusinessFile::class,
            'admin_alerts' => AdminAlerts::class,
            'admin_payment_queue' => AdminPaymentQueue::class,
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
    | "Optimizar con IA"
    |--------------------------------------------------------------------------
    | `daily_uses`: how many times a business may press it per day, counted
    | across its identity card, services and products (a platform setting
    | overrides it). The platform pays for these calls, so the ceiling is a
    | good-use limit, not a quota the plan sells.
    */

    'ai_optimize' => [
        'daily_uses' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | The admin's panel assistant
    |--------------------------------------------------------------------------
    | `daily_questions`: how many questions the admin side may ask per day (it
    | is paid by the platform; a platform setting overrides it). `guide`: admin
    | route name => [menu label key, translation path of the screen]. Each
    | becomes an SDK skill the model loads on demand, read from the very texts
    | the screen prints, so a new screen without a line here fails its test.
    */

    'admin_assistant' => [
        'daily_questions' => 100,
        'guide' => [
            'admin.dashboard' => ['menu.admin_home', 'admin.home'],
            'admin.alerts' => ['menu.admin_alerts', 'platform_events'],
            'admin.businesses' => ['menu.admin_all_businesses', 'admin.businesses'],
            'admin.adoption' => ['menu.admin_adoption', 'adoption'],
            'admin.incidents' => ['menu.admin_incidents', 'incidents'],
            'admin.contacts' => ['menu.admin_contacts', 'contacts'],
            'admin.compliance' => ['menu.admin_compliance', 'compliance'],
            'admin.payments' => ['menu.admin_payments', 'billing'],
            'admin.collections' => ['menu.admin_collections', 'collections'],
            'admin.ai' => ['menu.admin_ai', 'admin.ai'],
            'admin.ai-usage' => ['menu.admin_ai_usage', 'admin.ai_usage'],
            'admin.ai-quality' => ['menu.admin_ai_quality', 'ai_quality'],
            'admin.moderation' => ['menu.admin_content', 'moderation'],
            'admin.testimonials' => ['menu.admin_testimonials', 'testimonials'],
            'admin.mentions' => ['menu.admin_mentions', 'mentions'],
            'admin.support' => ['menu.admin_support', 'support'],
            'admin.company' => ['menu.admin_company', 'company'],
            'admin.catalogs' => ['menu.admin_catalogs', 'catalog'],
            'admin.integrations' => ['menu.admin_integrations', 'integrations'],
            'admin.platform-whatsapp' => ['menu.admin_platform_whatsapp', 'platform_whatsapp'],
            'admin.settings' => ['menu.admin_settings', 'admin.settings'],
            'admin.users' => ['menu.admin_users', 'admin.users'],
            'admin.roles' => ['menu.admin_roles', 'admin.roles'],
            'admin.audit' => ['menu.admin_audit', 'admin.audit'],
            'admin.logs' => ['menu.admin_logs', 'logs'],
            'admin.security' => ['menu.admin_security', 'security'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Referral program ("Gana con AtendIa")
    |--------------------------------------------------------------------------
    |
    | The reward is a discount on the next invoice, and nothing applies it yet:
    | the amount is not promised anywhere until the lawyer and the accountant
    | settle it (R-11). These numbers are the launch defaults.
    |
    */

    'app' => [
        // Client-menu route names the mobile app already has a screen for.
        'screens' => ['conversations', 'customers', 'my-services', 'my-products', 'agenda', 'whatsapp', 'my-plan', 'my-business'],
        // The same for the platform's staff: admin-menu route names the app has a screen for. It grows with each delivery.
        'admin_screens' => ['admin.dashboard', 'admin.alerts', 'admin.businesses', 'admin.payments'],
        'conversations_per_page' => 20,
        'messages_per_page' => 30,

        'push' => [
            // Expo's relay; it forwards to Google's FCM with the key stored in EAS.
            'url' => env('EXPO_PUSH_URL', 'https://exp.host/--/api/v2/push/send'),
            // Seconds a person is left alone after a push, so a burst buzzes once.
            'window_seconds' => 30,
        ],
    ],

    'referral' => [
        'invited_trial_days' => 21,
        'founders' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Mentions on the platform's own networks
    |--------------------------------------------------------------------------
    |
    | The offer to mention a business waits `pause_months` after a "no", and
    | stops for good after `max_refusals` of them. The goals themselves live in
    | the `mention_goals` master.
    |
    */

    'mentions' => [
        'pause_months' => 3,
        'max_refusals' => 2,
        'followers_stale_days' => 7,
    ],

    /*
    |--------------------------------------------------------------------------
    | Platform health (the admin's board)
    |--------------------------------------------------------------------------
    |
    | What the sweep tolerates before a sensor changes colour. The numbers are
    | launch defaults; they move from here until the admin settings screen
    | takes them, the way the incident thresholds did.
    |
    */

    // https://www.cloudflare.com/ips-v4 and /ips-v6, as published on 2026-10-16.
    // They change rarely; a range missing here only means that visitors' IP stays
    // Cloudflare's, never that a forged one is believed.
    'cloudflare' => [
        'ranges' => [
            '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18',
            '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17',
            '162.158.0.0/15', '104.16.0.0/13', '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
            '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32',
            '2a06:98c0::/29', '2c0f:f248::/32',
        ],
    ],

    'csp' => [
        // 'report-only' tells /csp-report what it WOULD block and blocks nothing;
        // 'enforce' blocks. The suite runs enforced, so a screen that needs an
        // origin the policy forgot fails there before it fails for a visitor.
        'mode' => env('CSP_MODE', 'report-only'),

        // Alpine builds code from x-data text (unsafe-eval) and 27 views carry
        // their own script (unsafe-inline), so this policy fixes WHERE things may
        // load from and who may frame or post to the page. It does not stop a
        // script injected into the page itself: that needs nonces and the CSP
        // build of Alpine, a job of days left for after launch.
        // The WebSocket origin is added per request, from the host asked.
        'directives' => [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'unsafe-inline'", "'unsafe-eval'"],
            'style-src' => ["'self'", "'unsafe-inline'", 'https://fonts.googleapis.com'],
            'font-src' => ["'self'", 'data:', 'https://fonts.gstatic.com'],
            // The map of a location message is nine OpenStreetMap tiles the browser loads itself.
            // pps.whatsapp.net serves the profile picture of the number that just linked.
            'img-src' => ["'self'", 'data:', 'blob:', 'https://tile.openstreetmap.org', 'https://pps.whatsapp.net'],
            'media-src' => ["'self'", 'data:', 'blob:'],
            // blob: is the avatar cropper reading the picture the person just picked.
            'connect-src' => ["'self'", 'blob:'],
            'frame-ancestors' => ["'self'"],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
        ],
    ],

    'health' => [
        // A look older than this is shown as stale, not as green.
        'stale_minutes' => 6,
        // Consecutive bad looks before the feed hears about a system sensor:
        // a blip is not an outage (Datadog's recovery threshold, PRTG's scans).
        'failures_to_raise' => 2,
        'disk_warn_percent' => 80,
        'disk_crit_percent' => 90,
        'queue_warn_minutes' => 5,
        'queue_crit_minutes' => 15,
        'backup_warn_hours' => 26,
        'backup_crit_hours' => 50,
        // Traefik asks Let's Encrypt for a new certificate about 30 days before
        // the old one ends, so 21 days left means a renewal has been failing for
        // over a week, and 7 leaves just enough time to fix it by hand.
        'certificate_warn_days' => 21,
        'certificate_crit_days' => 7,
        // Where the certificate is read: Traefik itself, because Cloudflare
        // answers the public name with its own certificate and the origin one
        // can expire unseen (every visitor would then get a 526).
        'certificate_origin' => env('HEALTH_CERT_ORIGIN', 'easypanel-traefik'),
        // An outside service that raises the alarm when THIS server stops
        // pinging it: a dead server cannot report its own death.
        'heartbeat_url' => env('HEALTH_HEARTBEAT_URL'),
    ],

];
