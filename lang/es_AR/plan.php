<?php

declare(strict_types=1);

// Solo overrides de voseo; lo que no está cae a lang/es/plan.php.
return [
    'trial_over' => 'Tu prueba terminó: seguís en el plan :plan',
    'change' => [
        'up_body' => ':plan empieza hoy con todos sus cupos. Pagás :currency :amount hoy por los :days días que quedan de tu período. Tu renovación del :date sigue igual, a :currency :price.',
        'down_body' => 'Seguís en :current con todo lo que pagaste hasta el :date. Desde ese día tu plan es :plan, a :currency :price. Podés cancelar el cambio antes de esa fecha.',
        'cancelled' => 'Listo: seguís en :plan.',
        'upgraded' => ':plan ya está activo. Subí el comprobante de :currency :amount para confirmarlo.',
    ],

    'features' => [
        'ask' => 'Preguntale a :brand: :cap consultas al mes',
    ],
];
