@php
    $clients = ['Clínica Vida', 'Kiosco Sol', 'Estudio Lex', 'Dra. Ríos', 'Pastelería Mía', 'AutoFix'];
@endphp

<section class="w-full flex justify-center pt-2 pb-10">
    <div class="w-full px-6" style="max-width: var(--container-xl);">
        <p class="text-center text-subtle font-semibold mb-4" style="font-size: var(--text-sm);">
            {{ __('landing.logos.title') }}
        </p>
        <div class="flex flex-wrap gap-3.5 justify-center" style="opacity:.85;">
            @foreach ($clients as $c)
                <span class="font-display text-subtle" style="font-weight:700;font-size:18px;letter-spacing:-0.01em;">{{ $c }}</span>
            @endforeach
        </div>
    </div>
</section>
