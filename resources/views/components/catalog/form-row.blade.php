{{--
    One row of a master's form.

    Rows are DECLARED here and not left to the browser's wrap, which leaves the
    last field alone on a row of its own. A row always reaches the right edge:
    fields declare how much content they need.
--}}
<div {{ $attributes->merge(['class' => 'form-row']) }}>
    {{ $slot }}
</div>
