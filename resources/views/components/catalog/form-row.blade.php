{{--
    Una fila del formulario de un maestro.

    Las filas se DECLARAN acá, no las decide el wrap del navegador: si el corte
    lo elige el ancho de la pantalla, el mismo formulario se ve distinto en un
    monitor grande que en un notebook, y el último campo —casi siempre el
    estado— termina solo en una fila entera.

    La fila siempre llega al borde derecho: los campos declaran cuánto contenido
    necesitan (`span`) y el sobrante se lo lleva el descriptivo de la fila.
--}}
<div {{ $attributes->merge(['class' => 'form-row']) }}>
    {{ $slot }}
</div>
