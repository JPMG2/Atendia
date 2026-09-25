<?php

declare(strict_types=1);

// Solo overrides de voseo; lo que no está cae a lang/es/plan.php.
return [
    'trial_over' => 'Tu prueba terminó: seguís en el plan :plan',
    'change' => [
        'up_body' => ':plan empieza hoy con todos sus cupos. Pagás USD :amount hoy por los :days días que quedan de tu período. Tu renovación del :date sigue igual, a USD :price.',
        'down_body' => 'Seguís en :current con todo lo que pagaste hasta el :date. Desde ese día tu plan es :plan, a USD :price. Podés cancelar el cambio antes de esa fecha.',
        'cancelled' => 'Listo: seguís en :plan.',
        'upgraded' => ':plan ya está activo. Subí el comprobante de USD :amount para confirmarlo.',
    ],

    'features' => [
        'ask' => 'Preguntale a AtendIa: :cap consultas al mes',
    ],
];
