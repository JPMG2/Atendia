@php
    $items = [
        ['icon' => 'calendar-check', 'title' => 'Agenda turnos sola', 'body' => 'Definí días, horarios y capacidad. El asistente ofrece huecos libres y confirma sin que muevas un dedo.'],
        ['icon' => 'package', 'title' => 'Muestra tu catálogo', 'body' => 'Cargá productos con precio y foto. Si preguntan, responde con la info y el link de compra al instante.'],
        ['icon' => 'message-circle', 'title' => 'Responde 24/7', 'body' => 'Contesta consultas frecuentes en segundos, en tu tono, incluso cuando dormís o estás con un cliente.'],
        ['icon' => 'bell', 'title' => 'Te avisa lo importante', 'body' => 'Recibís un resumen de turnos y conversaciones. Intervenís solo cuando hace falta.'],
        ['icon' => 'sliders-horizontal', 'title' => 'Vos tenés el control', 'body' => 'Todo se configura desde un panel claro: precios, horarios, mensajes y respuestas automáticas.'],
        ['icon' => 'shield-check', 'title' => 'Tu número, tu marca', 'body' => 'Funciona sobre tu propio WhatsApp. Tus clientes hablan con tu negocio, no con un bot genérico.'],
    ];
@endphp

<section id="funciones" class="w-full flex justify-center pt-16 pb-16">
    <div class="w-full px-6" style="max-width: var(--container-xl);">
        <div class="flex flex-col items-center text-center gap-3.5 mb-11">
            <span class="eyebrow eyebrow-line">Funciones</span>
            <h2 class="font-display" style="font-size: var(--text-4xl); max-width:620px;">Todo lo que tu negocio necesita para atender mejor</h2>
            <p class="text-muted" style="font-size: var(--text-lg); max-width:560px;">Sea una clínica o un kiosco, Atendia se adapta a cómo trabajás.</p>
        </div>

        <div class="grid gap-4.5 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3" style="gap:18px;">
            @foreach ($items as $it)
                <x-ui.card interactive style="padding:24px;">
                    <span class="inline-flex items-center justify-center mb-4" style="width:46px;height:46px;border-radius:var(--radius-md);background:var(--brand-soft);color:var(--brand);">
                        <x-icon :name="$it['icon']" :size="23" />
                    </span>
                    <h3 class="font-display mb-2" style="font-size: var(--text-xl);">{{ $it['title'] }}</h3>
                    <p class="text-muted" style="font-size: var(--text-sm); line-height:1.55;">{{ $it['body'] }}</p>
                </x-ui.card>
            @endforeach
        </div>
    </div>
</section>
