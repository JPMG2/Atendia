@php
    $clients = ['Clínica Vida', 'Kiosco Sol', 'Estudio Lex', 'Dra. Ríos', 'Pastelería Mía', 'AutoFix'];
@endphp

<section class="flex w-full justify-center pb-10 pt-2">
    <div class="w-full px-6" style="max-width: var(--container-xl)">
        <p class="text-subtle mb-4 text-center font-semibold" style="font-size: var(--text-sm)">
            {{ __('landing.logos.title') }}
        </p>
        <div class="flex flex-wrap justify-center gap-3.5" style="opacity: 0.85">
            @foreach ($clients as $c)
                <span
                    class="text-subtle font-display"
                    style="font-weight: 700; font-size: 18px; letter-spacing: -0.01em"
                >{{ $c }}</span>
            @endforeach
        </div>
    </div>
</section>
