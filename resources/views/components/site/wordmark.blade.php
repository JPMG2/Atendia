@props([
    'tag' => 'span',    // the element the accent uses, so each surface keeps its own CSS
    'accent' => null,   // inline colour, for mail clients that have no CSS variables
])

@php
    // The name was written by hand on five surfaces, split across tags so no
    // guard could see it, and it is being renamed. It comes from the row now.
    $brand = \App\Models\Company::brand();

    // The last two letters carry the accent — "Atend·ia" today, and the shape
    // survives the day that row says something else.
    $head = e(mb_substr($brand, 0, max(1, mb_strlen($brand) - 2)));
    $tail = mb_strlen($brand) > 2 ? e(mb_substr($brand, -2)) : '';

    // Built here and echoed once: a formatter that puts the two halves on
    // their own lines prints "Atend ia", with the space in the middle.
    $style = $accent === null ? '' : ' style="color: '.e($accent).'"';
    $mark = $tail === '' ? $head : $head.'<'.$tag.$style.'>'.$tail.'</'.$tag.'>';
@endphp
{{-- No wrapper on purpose: the caller owns the element and its styling, and
this only decides where the name splits. --}}
{!! $mark !!}
