<?php

declare(strict_types=1);

// Sin imperativo en segunda persona a propósito: así no necesita override de voseo en es_AR.
return [
    'title' => 'Menciones',
    'sub' => 'Los negocios que aceptaron que los mencionemos en nuestras redes. Se publica solo a quien sigue nuestra cuenta.',
    'read_at' => 'Leído a las :time',

    // Se lee "100 conversaciones atendidas en un mes" y nombra la meta en el aviso, el post y la fila.
    'achievement' => '{1}1 conversación atendida en un mes|[2,*]:count conversaciones atendidas en un mes',

    'stages' => [
        'pending' => 'Por verificar',
        'ready' => 'Por publicar',
        'not_following' => 'Esperan seguirnos',
        'published' => 'Publicadas',
    ],

    'status' => [
        'dismissed' => 'Dijo que no',
        'pending' => 'Por verificar',
        'not_following' => 'Espera seguirnos',
        'ready' => 'Por publicar',
        'published' => 'Publicada',
        'withdrawn' => 'Se retiró',
    ],

    'tiles' => [
        'pending' => 'Comprobar que siguen la cuenta',
        'ready' => 'Siguen la cuenta y esperan su post',
        'not_following' => 'Les avisamos que falta seguirnos',
        'published' => 'Menciones ya publicadas',
    ],

    'columns' => [
        'business' => 'Negocio',
        'account' => 'Su cuenta',
        'answered' => 'Fecha',
    ],

    'actions' => [
        'open_ours' => 'Nuestro :network',
        'follows' => 'Nos sigue en todas',
        'not_follows' => 'No nos sigue',
        'copy' => 'Copiar texto',
        'copied' => 'Copiado',
        'mark_published' => 'Marcar publicada',
        'post_link_placeholder' => 'https:// enlace de la publicación',
        'save_published' => 'Guardar',
        'cancel' => 'Cancelar',
        'open_post' => 'Ver publicación',
    ],

    'fields' => [
        'post_url' => 'enlace de la publicación',
    ],

    'empty' => [
        'pending' => [
            'title' => 'Nada por verificar',
            'body' => 'Cuando un negocio acepte que lo mencionemos, aparece aquí para comprobar que sigue nuestra cuenta.',
        ],
        'ready' => [
            'title' => 'Nada por publicar',
            'body' => 'Los negocios que ya comprobaste aparecen aquí con su texto listo para copiar.',
        ],
        'not_following' => [
            'title' => 'Nadie espera seguirnos',
            'body' => 'Aquí quedan los negocios a los que avisaste que falta seguir la cuenta, hasta que digan que ya lo hicieron.',
        ],
        'published' => [
            'title' => 'Todavía no hay menciones publicadas',
            'body' => 'Cada mención que marques como publicada queda aquí con su enlace.',
        ],
    ],

    'followers' => [
        'title' => 'Seguidores de nuestras cuentas',
        'sub' => 'Anota el número una vez por semana: así se ve cuántos seguidores trae cada tanda de menciones.',
        'field' => 'seguidores',
        'placeholder' => 'Seguidores hoy',
        'count' => 'Anotar',
        'save' => 'Guardar',
        'cancel' => 'Cancelar',
        'saved' => 'Conteo guardado.',
        'never' => 'Sin contar todavía',
        'as_of' => 'Al :date',
        'stale' => 'Vencido: vuelve a contar',
        'same' => 'Igual que el conteo anterior',
        'since' => ':change desde el conteo anterior',
        'first' => 'Primer conteo: falta otro para comparar',
        'mentions' => '{0}Ninguna mención publicada en ese tramo|{1}1 mención publicada en ese tramo|[2,*]:count menciones publicadas en ese tramo',
        'empty_title' => 'Aún no hay cuentas registradas',
        'empty_body' => 'Agrega las redes de la plataforma en Compañía y aquí puedes contar sus seguidores.',
    ],

    'verified' => 'Listo: pasó a por publicar.',
    'rejected' => 'Le avisamos al negocio que falta seguir la cuenta.',
    'published' => 'Mención publicada. Le avisamos al negocio.',
    'stale' => 'Esta mención ya cambió. Actualizamos la lista.',
    'failed' => 'No pudimos guardar la mención. Intenta de nuevo.',
];
