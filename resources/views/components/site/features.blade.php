@php
    // Bento: the owner's panel is the star tile; the other features keep
    // their copy and gain a small vignette of the screen they describe.
    $vignette = fn (string $key): string => __('landing.features.vignettes.'.$key);
@endphp

<section id="funciones" class="flex w-full justify-center pb-16 pt-16">
    <div class="w-full px-6" style="max-width: var(--container-xl)">
        <div class="mb-11 flex flex-col items-center gap-3.5 text-center">
            <span class="eyebrow eyebrow-line">{{ __('landing.features.eyebrow') }}</span>
            <h2 class="font-display" style="font-size: var(--text-4xl); max-width: 620px">
                {{ __('landing.features.title') }}
            </h2>
            <p class="text-muted" style="font-size: var(--text-lg); max-width: 560px">
                {{ __('landing.features.subtitle') }}
            </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6" style="gap: 18px">
            {{-- Star tile: the panel the owner actually buys, mirrored live. --}}
            <x-site.feature-tile
                icon="sliders-horizontal"
                :title="__('landing.features.control.title')"
                :body="__('landing.features.control.body')"
                class="flex flex-col sm:col-span-2 lg:col-span-4 lg:row-span-2"
            >
                <div class="mini-window mt-2 flex-1" aria-hidden="true">
                    <div class="mini-window-bar">
                        <i></i><i></i><i></i>
                        <span>{{ $vignette('panel_tab') }}</span>
                    </div>
                    <div class="mini-window-body">
                        <div class="flex gap-2.5">
                            <div class="mini-stat">
                                <b class="font-mono">128</b>
                                <span>{{ $vignette('stat_conversations') }}</span>
                            </div>
                            <div class="mini-stat">
                                <b class="font-mono">92%</b>
                                <span>{{ $vignette('stat_resolution') }}</span>
                            </div>
                        </div>
                        <div class="vignette-row" style="background: var(--surface-card)">
                            <x-ui.avatar :name="$vignette('thread_1_name')" size="xs" />
                            <span class="truncate">
                                <b class="text-strong">{{ $vignette('thread_1_name') }}</b>
                                · {{ $vignette('thread_1_text') }}
                            </span>
                            <span class="row-meta">
                                <x-ui.badge variant="brand">{{ $vignette('thread_1_badge') }}</x-ui.badge>
                            </span>
                        </div>
                        <div class="vignette-row" style="background: var(--surface-card)">
                            <x-ui.avatar :name="$vignette('thread_2_name')" size="xs" />
                            <span class="truncate">
                                <b class="text-strong">{{ $vignette('thread_2_name') }}</b>
                                · {{ $vignette('thread_2_text') }}
                            </span>
                            <span class="row-meta">
                                <x-ui.badge variant="neutral">{{ $vignette('thread_2_badge') }}</x-ui.badge>
                            </span>
                        </div>
                    </div>
                </div>
            </x-site.feature-tile>

            <x-site.feature-tile
                icon="message-circle"
                :title="__('landing.features.always.title')"
                :body="__('landing.features.always.body')"
                class="flex flex-col lg:col-span-2"
            >
                <div class="mt-auto flex flex-wrap gap-1.5" aria-hidden="true">
                    @foreach (__('landing.features.vignettes.always_pills') as $pill)
                        <x-ui.badge variant="neutral">{{ $pill }}</x-ui.badge>
                    @endforeach
                </div>
            </x-site.feature-tile>

            <x-site.feature-tile
                icon="bell"
                :title="__('landing.features.alerts.title')"
                :body="__('landing.features.alerts.body')"
                class="flex flex-col lg:col-span-2"
            >
                <div class="vignette-row mt-auto" aria-hidden="true">
                    <x-icon name="bell" :size="14" style="color: var(--brand); flex-shrink: 0" />
                    <span>{{ $vignette('alert_text') }}</span>
                </div>
            </x-site.feature-tile>

            <x-site.feature-tile
                icon="calendar-check"
                :title="__('landing.features.schedule.title')"
                :body="__('landing.features.schedule.body')"
                class="flex flex-col lg:col-span-2"
            >
                <div class="vignette-row mt-auto" aria-hidden="true">
                    <x-icon name="calendar-check" :size="14" style="color: var(--brand); flex-shrink: 0" />
                    <span class="truncate">{{ $vignette('schedule_text') }}</span>
                    <span class="row-meta">
                        <x-ui.badge variant="brand">{{ $vignette('schedule_badge') }}</x-ui.badge>
                    </span>
                </div>
            </x-site.feature-tile>

            {{-- Second star tile: selling is what a bookings-only assistant
            cannot show — a whole exchange, ending where the team takes over. --}}
            <x-site.feature-tile
                icon="package"
                :title="__('landing.features.catalog.title')"
                :body="__('landing.features.catalog.body')"
                class="flex flex-col sm:col-span-2 lg:col-span-4 lg:row-span-2"
            >
                <div class="bg-sunken mt-2 flex flex-1 flex-col justify-center rounded-xl" style="gap: 8px; padding: 14px" aria-hidden="true">
                    <div class="pm-row in">
                        <div class="pm-bubble in">{{ $vignette('catalog_ask') }}</div>
                    </div>
                    <div class="pm-row out">
                        <div class="pm-bubble out flex flex-col" style="gap: 8px">
                            <span class="vignette-row" style="background: var(--surface-card); color: var(--text-body)">
                                <x-icon name="package" :size="16" style="color: var(--brand); flex-shrink: 0" />
                                <span class="flex min-w-0 flex-col">
                                    <b class="text-strong">{{ $vignette('catalog_product') }}</b>
                                    <span class="font-mono">{{ $vignette('catalog_price') }} · {{ $vignette('catalog_stock') }}</span>
                                </span>
                            </span>
                            <span>{{ $vignette('catalog_offer') }}</span>
                        </div>
                    </div>
                    <div class="pm-row in">
                        <div class="pm-bubble in">{{ $vignette('catalog_want') }}</div>
                    </div>
                    <div class="pm-row out">
                        <div class="pm-bubble out">{{ $vignette('catalog_handoff') }}</div>
                    </div>
                    <div class="flex justify-center">
                        <x-ui.badge variant="brand">{{ $vignette('catalog_badge') }}</x-ui.badge>
                    </div>
                </div>
            </x-site.feature-tile>

            <x-site.feature-tile
                icon="shield-check"
                :title="__('landing.features.brand.title')"
                :body="__('landing.features.brand.body')"
                class="flex flex-col lg:col-span-2"
            >
                {{-- The contact card the customer sees: the owner's business, not ours. --}}
                <div class="mt-auto flex flex-col gap-1.5" aria-hidden="true">
                    <div class="vignette-row">
                        <x-ui.avatar :name="$vignette('brand_name')" size="xs" />
                        <b class="text-strong truncate">{{ $vignette('brand_name') }}</b>
                        <span class="row-meta">
                            <x-ui.badge variant="brand">{{ $vignette('brand_badge') }}</x-ui.badge>
                        </span>
                    </div>
                    <div class="vignette-row">
                        <x-icon name="message-circle" :size="14" style="color: var(--brand); flex-shrink: 0" />
                        <span class="truncate">{{ $vignette('brand_greeting') }}</span>
                    </div>
                </div>
            </x-site.feature-tile>
        </div>
    </div>
</section>
