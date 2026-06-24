@php
    $bubbles = [
        ['side' => 'in',  'time' => '09:41', 'text' => __('landing.phone.b1')],
        ['side' => 'out', 'time' => '09:41', 'text' => __('landing.phone.b2')],
        ['side' => 'in',  'time' => '09:42', 'text' => __('landing.phone.b3')],
        ['side' => 'out', 'time' => '09:42', 'text' => __('landing.phone.b4')],
    ];
@endphp

<div class="relative">
    {{-- glow jade --}}
    <div class="absolute" style="inset:-12% -8%; background: radial-gradient(60% 55% at 60% 35%, color-mix(in srgb, var(--brand) 28%, transparent), transparent 70%); filter: blur(8px); z-index:0;"></div>

    <div class="relative mx-auto" style="z-index:1; width:300px; background: var(--ink-900); border-radius:36px; padding:10px; box-shadow: var(--shadow-xl);">
        <div style="background: var(--chat-canvas); border-radius:28px; overflow:hidden;">
            {{-- header --}}
            <div class="flex items-center gap-2.5" style="background: var(--bubble-out); padding:14px 14px 12px;">
                <span class="inline-flex items-center justify-center" style="width:36px;height:36px;border-radius:50%;background:rgba(255,255,255,.18);">
                    <x-icon name="bot" :size="20" style="color:#fff;" />
                </span>
                <div style="line-height:1.2;">
                    <div style="color:#fff;font-weight:700;font-size:14px;">{{ __('landing.phone.header') }}</div>
                    <div class="flex items-center gap-1.5" style="color:rgba(255,255,255,.8);font-size:11px;">
                        <span style="width:7px;height:7px;border-radius:50%;background:#7CFFC4;"></span>{{ __('landing.phone.online') }}
                    </div>
                </div>
            </div>

            {{-- mensajes --}}
            <div class="flex flex-col" style="padding:14px 12px; gap:9px; min-height:300px;">
                @foreach ($bubbles as $b)
                    @php $out = $b['side'] === 'out'; @endphp
                    <div class="flex {{ $out ? 'justify-end' : 'justify-start' }}">
                        <div style="max-width:78%; padding:9px 12px 7px; border-radius:14px;
                            border-bottom-right-radius: {{ $out ? '4px' : '14px' }}; border-bottom-left-radius: {{ $out ? '14px' : '4px' }};
                            background: {{ $out ? 'var(--bubble-out)' : 'var(--bubble-in)' }};
                            color: {{ $out ? 'var(--bubble-out-text)' : 'var(--bubble-in-text)' }};
                            box-shadow: var(--shadow-xs); font-size: var(--text-sm); line-height:1.4;">
                            {!! $b['text'] !!}
                            <span style="display:block;text-align:right;font-size:10px;opacity:.7;margin-top:2px;">{{ $b['time'] }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
