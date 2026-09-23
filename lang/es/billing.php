<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Mis pagos — copy del módulo de pagos del cliente a Atendia
|--------------------------------------------------------------------------
|
| Base NEUTRA (tuteo). Voseo en `lang/es_AR/billing.php`.
|
*/

return [

    'title' => 'Mis pagos',
    'sub' => 'Tu período actual, tu próximo pago y el historial de todo lo que pagaste.',

    'period' => [
        'title' => 'Tu período actual',
        'trial' => 'Prueba gratis del plan :plan',
        'monthly' => 'Plan :plan · mensual',
        'yearly' => 'Plan :plan · anual',
        'of_days' => 'de :days días',
        'started' => 'Empezó',
        'renews' => 'Se renueva',
        'trial_ends' => 'Termina la prueba',
        'used' => 'Días usados',
        'left' => 'Te quedan',
        'left_days' => '{1} :count día|[2,*] :count días',
        'overdue' => 'Vencido',
    ],

    'next' => [
        'title' => 'Próximo pago',
        'due' => 'Se paga el :date',
        'line' => 'Plan :plan · :cycle',
        'cycle_monthly' => 'un mes',
        'cycle_yearly' => 'un año',
        'upload' => 'Subir comprobante',
        'how' => 'Cómo pagar',
        'no_instructions' => 'Pronto vas a ver aquí cómo pagar. Mientras, escríbenos y te pasamos los datos.',
    ],

    'history' => [
        'title' => 'Historial de pagos',
        'sub' => 'Todo lo que le pagaste a Atendia, con su comprobante.',
        'date' => 'Fecha',
        'concept' => 'Concepto',
        'period' => 'Período',
        'amount' => 'Monto',
        'method' => 'Medio',
        'status' => 'Estado',
        'receipt' => 'Comprobante',
        'concept_line' => 'Plan :plan',
        'methods' => ['transfer' => 'Transferencia'],
        'statuses' => ['pending' => 'Por verificar', 'paid' => 'Pagado', 'rejected' => 'Rechazado'],
        'empty' => 'Todavía no hay pagos. El primero aparece aquí apenas subas tu comprobante.',
    ],

    'billing_data' => [
        'title' => 'A nombre de quién sale',
        'business' => 'Negocio',
        'tax_id' => 'Número fiscal',
        'tax_condition' => 'Condición fiscal',
        'email' => 'Correo de facturación',
        'missing' => 'Sin cargar',
        'edit' => 'Editar en Mi negocio',
    ],

    'yearly' => [
        'title' => 'Pásate a anual y ahorra',
        'body' => 'Pagando el año son 2 meses gratis: :amount menos.',
        'per_month' => '/ mes, facturado anual',
        'cta' => 'Ver planes',
    ],

    'receipt' => [
        'title' => 'Subir comprobante',
        'sub' => 'Lo verificamos y te avisamos por correo apenas se acredite.',
        'file' => 'Comprobante',
        'file_note' => 'Foto o PDF, hasta 5 MB.',
        'reference' => 'Número de operación (opcional)',
        'amount' => 'Monto a pagar',
        'submit' => 'Enviar comprobante',
        'cancel' => 'Cancelar',
        'sent' => 'Listo. Recibimos tu comprobante y lo estamos verificando.',
        'already_pending' => 'Ya tienes un comprobante en verificación: te avisamos apenas lo revisemos.',
    ],

    'banner' => [
        'upcoming' => '{1} Tu próximo pago es mañana.|[2,*] Tu próximo pago es en :count días.',
        'upcoming_body' => 'Págalo antes del :date para que tu asistente siga atendiendo sin cortes.',
        'overdue' => 'Tu pago está vencido.',
        'overdue_body' => '{0} Hoy es el último día antes de que tu asistente se pause.|{1} Tu asistente sigue atendiendo 1 día más.|[2,*] Tu asistente sigue atendiendo :count días más.',
        'paused' => 'Tu asistente está en pausa por falta de pago.',
        'paused_body' => 'No se borró nada. Apenas se acredite el pago, vuelve a atender.',
        'verifying' => 'Estamos verificando tu comprobante.',
        'verifying_body' => 'Te avisamos por correo apenas se acredite.',
        'cta' => 'Ir a Mis pagos',
        'cta_upload' => 'Subir comprobante',
    ],

    'badge' => '{0} hoy|{1} 1 d|[2,*] :count d',

    'whatsapp' => [
        'upcoming' => '🔔 *Atendia*: tu próximo pago del plan :plan (:amount) vence el :date, en :days días. Págalo y sube el comprobante para que tu asistente siga atendiendo sin cortes: :url',
        'overdue' => '⚠️ *Atendia*: el pago del plan :plan (:amount) está vencido. Tu asistente sigue atendiendo :days días más; después se pausa hasta que lo recibamos: :url',
        'paused' => '⏸️ *Atendia*: pausamos tu asistente porque no recibimos el pago del plan :plan (:amount). No se borró nada: apenas se acredite, vuelve a atender: :url',
    ],

    'admin' => [
        'title' => 'Pagos',
        'sub' => 'Comprobantes que esperan verificación y los últimos pagos.',
        'pending' => 'Por verificar',
        'recent' => 'Últimos pagos',
        'business' => 'Negocio',
        'approve' => 'Acreditar',
        'reject' => 'Rechazar',
        'reason' => 'Motivo del rechazo',
        'reason_placeholder' => 'Ej.: el comprobante no se lee',
        'approved' => 'Pago acreditado: el plan quedó al día.',
        'rejected' => 'Pago rechazado: le avisamos al cliente.',
        'empty' => 'No hay comprobantes por verificar.',
        'recent_empty' => 'Todavía no se revisó ningún pago.',
        'reference' => 'N.º de operación',
        'approve_title' => '¿Acreditar este pago?',
        'approve_message' => 'El plan de :business queda al día y le avisamos por correo.',
    ],

];
