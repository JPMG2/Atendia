<?php

declare(strict_types=1);

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
    | AI rates (USD)
    |--------------------------------------------------------------------------
    |
    | Prices for turning measured usage into money: input/output per MILLION
    | tokens and audio transcription per minute. Null token rates mean the
    | cost report shows tokens only — set them when the provider's price for
    | the active model is confirmed.
    |
    */

    'ai_rates' => [
        'prompt_per_million' => env('AI_RATE_PROMPT'),
        'completion_per_million' => env('AI_RATE_COMPLETION'),
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
