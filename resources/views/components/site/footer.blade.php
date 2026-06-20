@php
    $cols = [
        ['h' => 'Producto', 'links' => ['Funciones', 'Precios', 'Casos', 'Integraciones']],
        ['h' => 'Recursos', 'links' => ['Cómo funciona', 'Ayuda', 'Estado', 'Blog']],
        ['h' => 'Empresa', 'links' => ['Nosotros', 'Contacto', 'Términos', 'Privacidad']],
    ];
@endphp

<footer class="bg-card border-t bd-subtle">
    <div class="mx-auto grid gap-8 grid-cols-2 lg:grid-cols-[1.6fr_1fr_1fr_1fr]" style="max-width: var(--container-xl); padding:48px 24px 28px;">
        <div class="flex flex-col gap-3 col-span-2 lg:col-span-1" style="max-width:280px;">
            <x-site.logo :size="24" />
            <p class="text-muted" style="font-size: var(--text-sm); line-height:1.55;">Publicidad y atención automatizada por WhatsApp para cualquier negocio.</p>
        </div>
        @foreach ($cols as $col)
            <div class="flex flex-col gap-2.5">
                <div class="text-strong" style="font-weight:700;font-size:var(--text-sm);">{{ $col['h'] }}</div>
                @foreach ($col['links'] as $l)
                    <a href="#" class="text-muted hover:text-brand transition" style="font-size:var(--text-sm);">{{ $l }}</a>
                @endforeach
            </div>
        @endforeach
    </div>
    <div class="border-t bd-subtle mx-auto flex flex-wrap justify-between items-center gap-2.5 text-subtle" style="max-width: var(--container-xl); padding:16px 24px; font-size:var(--text-xs);">
        <span>© {{ date('Y') }} Atendia. Hecho para los que atienden.</span>
        <span class="flex gap-3.5">
            <a href="#" class="text-subtle">Términos</a>
            <a href="#" class="text-subtle">Privacidad</a>
        </span>
    </div>
</footer>
