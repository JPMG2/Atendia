@php
    $steps = [
        ['n' => '01', 'icon' => 'message-square', 'title' => 'El cliente escribe', 'body' => 'Manda un mensaje a tu WhatsApp como siempre.', 'accent' => false],
        ['n' => '02', 'icon' => 'workflow', 'title' => 'Atendia lo procesa', 'body' => 'El flujo automático entiende la consulta y busca la respuesta en tu configuración.', 'accent' => false],
        ['n' => '03', 'icon' => 'sparkles', 'title' => 'Responde por vos', 'body' => 'Contesta, agenda o cotiza al instante, con tu tono y tus datos.', 'accent' => true],
        ['n' => '04', 'icon' => 'layout-dashboard', 'title' => 'Vos supervisás', 'body' => 'Revisás todo desde el panel y tomás el control cuando quieras.', 'accent' => false],
    ];
@endphp

<section id="como-funciona" class="w-full flex justify-center pt-16 pb-16">
    <div class="w-full px-6" style="max-width: var(--container-xl);">
        <x-ui.card style="border-radius:var(--radius-2xl); padding:48px 40px;">
            <div class="flex flex-col items-center text-center gap-3 mb-10">
                <span class="eyebrow eyebrow-line">Cómo funciona</span>
                <h2 class="font-display" style="font-size: var(--text-4xl); max-width:560px;">De la pregunta a la respuesta, automático</h2>
            </div>

            <div class="grid gap-3 grid-cols-1 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($steps as $s)
                    <div class="flex flex-col gap-3" style="padding:0 8px;">
                        <div class="flex items-center gap-3">
                            <span class="inline-flex items-center justify-center" style="width:48px;height:48px;border-radius:var(--radius-lg);
                                background: {{ $s['accent'] ? 'var(--accent-soft)' : 'var(--brand-soft)' }};
                                color: {{ $s['accent'] ? 'var(--accent)' : 'var(--brand)' }};">
                                <x-icon :name="$s['icon']" :size="24" />
                            </span>
                            <span class="font-mono text-subtle" style="font-size:13px;font-weight:600;">{{ $s['n'] }}</span>
                        </div>
                        <h3 class="font-display" style="font-size: var(--text-lg);">{{ $s['title'] }}</h3>
                        <p class="text-muted" style="font-size: var(--text-sm); line-height:1.5;">{{ $s['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </x-ui.card>
    </div>
</section>
