@php
    $testimonials = [
        ['name' => 'Clínica Vida', 'who' => 'Centro médico', 'quote' => 'Dejamos de perder turnos por no contestar a tiempo. Ahora la agenda se llena sola.'],
        ['name' => 'Pastelería Mía', 'who' => 'Repostería', 'quote' => 'Responde por precios y sabores aunque yo esté horneando. Vendí 30% más en un mes.'],
        ['name' => 'Dr. Luis Paz', 'who' => 'Cardiología', 'quote' => 'Mis pacientes agendan por WhatsApp y reciben recordatorios. Cero ausencias.'],
        ['name' => 'Kiosco Sol', 'who' => 'Comercio', 'quote' => 'Configurarlo me llevó una tarde. Atiende consultas hasta de madrugada.'],
        ['name' => 'Estudio Lex', 'who' => 'Abogados', 'quote' => 'Filtra consultas y agenda reuniones. Llegamos solo a lo que importa.'],
        ['name' => 'AutoFix', 'who' => 'Taller', 'quote' => 'Cotiza service y reserva el turno del auto sin que levante el teléfono.'],
        ['name' => 'Dra. Ríos', 'who' => 'Odontología', 'quote' => 'El asistente habla como hablo yo. Los pacientes ni notan la diferencia.'],
        ['name' => 'Glow Spa', 'who' => 'Estética', 'quote' => 'Reservas, paquetes y promos respondidas al toque. Una genialidad.'],
    ];

    $initials = fn (string $name) => collect(explode(' ', $name))->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('');

    $rows = [
        ['items' => $testimonials, 'dur' => 46, 'reverse' => false],
        ['items' => array_reverse($testimonials), 'dur' => 54, 'reverse' => true],
    ];
@endphp

<section id="clientes" class="pt-14 pb-16 overflow-hidden">
    <div class="w-full px-6 mx-auto text-center flex flex-col items-center gap-3 mb-9" style="max-width: var(--container-xl);">
        <span class="eyebrow eyebrow-line">Clientes</span>
        <h2 class="font-display" style="font-size: var(--text-4xl); max-width:600px;">Negocios que ya no atienden solos</h2>
        <p class="text-muted" style="font-size: var(--text-lg);">Miles de conversaciones respondidas cada día, en todos los rubros.</p>
    </div>

    <div class="flex flex-col gap-4">
        @foreach ($rows as $row)
            <div class="marquee w-full">
                <div class="marquee-track" style="animation-duration: {{ $row['dur'] }}s; animation-direction: {{ $row['reverse'] ? 'reverse' : 'normal' }};">
                    @foreach (array_merge($row['items'], $row['items']) as $t)
                        <x-ui.card class="flex flex-col gap-3" style="flex:0 0 auto;width:332px;padding:20px;margin-right:16px;">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <span class="inline-flex items-center justify-center font-display" style="width:40px;height:40px;border-radius:999px;background:var(--brand-soft);color:var(--brand-soft-text);font-weight:700;font-size:14px;">{{ $initials($t['name']) }}</span>
                                    <div style="line-height:1.25;">
                                        <div class="text-strong" style="font-weight:700;font-size:var(--text-sm);">{{ $t['name'] }}</div>
                                        <div class="text-muted" style="font-size:var(--text-xs);">{{ $t['who'] }}</div>
                                    </div>
                                </div>
                                <div class="flex gap-0.5" style="color:var(--accent);">
                                    @for ($i = 0; $i < 5; $i++)<x-icon name="star" :size="14" />@endfor
                                </div>
                            </div>
                            <p class="text-body" style="font-size:var(--text-sm);line-height:1.55;">"{{ $t['quote'] }}"</p>
                        </x-ui.card>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</section>
