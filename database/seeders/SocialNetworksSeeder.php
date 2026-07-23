<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SocialNetworks;
use Illuminate\Database\Seeder;

class SocialNetworksSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $networks = [
            [
                'name' => 'Facebook',
                'url' => 'https://www.facebook.com/',
                'icon' => 'facebook',
                'abbreviation' => 'FB',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Instagram',
                'url' => 'https://www.instagram.com/',
                'icon' => 'instagram',
                'abbreviation' => 'IG',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'X (Twitter)',
                'url' => 'https://x.com/',
                'icon' => 'x-twitter',
                'abbreviation' => 'X',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'YouTube',
                'url' => 'https://www.youtube.com/',
                'icon' => 'youtube',
                'abbreviation' => 'YT',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'TikTok',
                'url' => 'https://www.tiktok.com/',
                'icon' => 'tiktok',
                'abbreviation' => 'TK',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'LinkedIn',
                'url' => 'https://www.linkedin.com/',
                'icon' => 'linkedin',
                'abbreviation' => 'LI',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Pinterest',
                'url' => 'https://www.pinterest.com/',
                'icon' => 'pinterest',
                'abbreviation' => 'PIN',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'WhatsApp',
                'url' => 'https://wa.me/',
                'icon' => 'whatsapp',
                'abbreviation' => 'WA',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Threads',
                'url' => 'https://www.threads.net/',
                'icon' => 'threads',
                'abbreviation' => 'TH',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Twitch',
                'url' => 'https://www.twitch.tv/',
                'icon' => 'twitch',
                'abbreviation' => 'TW',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($networks as $network) {
            SocialNetworks::query()->firstOrCreate(
                ['name' => $network['name']],
                [
                    'url' => $network['url'],
                    'icon' => $network['icon'],
                    'abbreviation' => $network['abbreviation'],
                    'is_active' => $network['is_active'],
                ],
            );
        }
    }
}
