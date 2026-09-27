{{-- Imprimir + Excel + CSV side by side: the usual trio, one block, so a
footer never re-lays it out by hand. On a phone it takes its own row. --}}
@props([
    'report',
    'formats' => ['pdf', 'xlsx', 'csv'],
    'size' => 'md',
])

<div {{ $attributes->merge(['class' => 'export-group']) }}>
    @foreach ($formats as $format)
        <x-ui.export-button :report="$report" :format="$format" :size="$size" />
    @endforeach
</div>
