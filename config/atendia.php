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

];
