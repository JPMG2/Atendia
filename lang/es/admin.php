<?php

declare(strict_types=1);

return [

    'ai' => [
        'title' => 'Modelos de IA',
        'sub' => 'Qué modelo responde cada tarea, en qué proveedor y a qué precio.',

        'tasks' => 'Tareas',
        'tasks_sub' => 'Sin asignar, la tarea corre con el modelo escrito en su agente.',
        'tasks_empty_title' => 'Todavía no hay tareas',
        'tasks_empty' => 'Las tareas se siembran con AiModelSeeder: una por agente.',
        'mechanical' => 'Mecánica',
        'running' => 'Hoy corre',
        'provider_default' => 'el que trae el proveedor',
        'no_agent' => 'El agente de esta fila ya no existe en el código.',
        'model' => 'Modelo',
        'fallback' => 'Respaldo',
        'unassigned' => 'El del agente',
        'no_fallback' => 'Sin respaldo',
        'save' => 'Guardar las asignaciones',
        'save_hint' => 'Se guardan juntas las tareas que hayas cambiado.',
        'saved' => 'Listo. La tarea ya corre con ese modelo.',
        'saved_count' => 'Listo. :count tarea ya corre con el modelo que elegiste.|Listo. :count tareas ya corren con los modelos que elegiste.',
        'nothing_changed' => 'No cambiaste ninguna tarea.',

        'models' => 'Modelos y precios',
        'models_sub' => 'Un precio nuevo es una fila nueva: lo ya consumido conserva el suyo.',
        'models_empty_title' => 'Todavía no hay modelos',
        'models_empty' => 'Cargá el primero con su precio y desde qué día rige.',
        'new' => 'Nuevo precio',
        'edit' => 'Editar',
        'save_model' => 'Guardar el modelo',
        'cancel' => 'Cancelar',
        'model_saved' => 'Listo. El modelo quedó guardado.',
        'active' => 'Activo',
        'inactive' => 'Inactivo',

        'columns' => [
            'task' => 'Tarea',
            'model' => 'Modelo',
            'prompt' => 'Entrada',
            'cached' => 'Cacheada',
            'completion' => 'Salida',
            'effective_from' => 'Rige desde',
        ],

        'fields' => [
            'provider' => 'Proveedor',
            'code' => 'Código del modelo',
            'code_hint' => 'Tal cual lo nombra el proveedor, ej. gpt-6-astra.',
            'label' => 'Nombre',
            'prompt' => 'Entrada (USD/millón)',
            'cached' => 'Cacheada (USD/millón)',
            'completion' => 'Salida (USD/millón)',
            'effective_from' => 'Rige desde',
            'source' => 'De dónde salió el precio',
            'source_hint' => 'Para poder auditarlo después, ej. "Precio publicado, verificado el 04/10".',
            'status' => 'Estado',
        ],
    ],

    'audit' => [
        'title' => 'Auditoría',
        'sub' => 'Quién hizo qué. Abre en lo que no se deshace, no en el ruido de todos los días.',
        'count' => ':count movimiento|:count movimientos',
        'empty' => 'No hay movimientos con ese filtro.',

        'who' => 'Quién',
        'what' => 'Qué pasó',
        'when' => 'Cuándo',
        'on' => 'Sobre qué',
        'anybody' => 'Cualquiera',
        'system' => 'El sistema',
        'strong_only' => 'Qué mostrar',
        'strong_on' => 'Solo lo fuerte',
        'strong_off' => 'Todo',

        'actions' => [
            'created' => 'Creó',
            'updated' => 'Modificó',
            'deleted' => 'Eliminó',
            'restored' => 'Restauró',
            'granted' => 'Dio acceso',
            'revoked' => 'Quitó acceso',
        ],

        'subjects' => [
            'Business' => 'Negocio',
            'User' => 'Persona',
            'Role' => 'Rol',
            'Product' => 'Producto',
            'Service' => 'Servicio',
            'SuggestedService' => 'Servicio sugerido',
            'Currency' => 'Moneda',
            'ServiceType' => 'Tipo de servicio',
            'ServiceModality' => 'Modalidad de servicio',
            'ServiceAttribute' => 'Atributo de servicio',
        ],
    ],

    'roles' => [
        'title' => 'Roles y permisos',
        'sub' => 'Qué puertas abre cada trabajo. Un rol nuevo nace acá, sin tocar código.',
        'new' => 'Nuevo rol',
        'edit' => 'Editar',
        'delete' => 'Eliminar',
        'save' => 'Guardar el rol',
        'cancel' => 'Cancelar',
        'saved' => 'Listo. El rol :role quedó guardado.',
        'deleted' => 'El rol :role se eliminó.',
        'in_use' => 'No se puede eliminar: lo tienen :count persona(s). Primero cambiales el rol.',
        'protected' => 'Ese rol no se edita desde una pantalla.',
        'protected_tag' => 'No se edita',
        'panel_implicit' => 'Entrar al panel va incluido en todo rol: lo que elegís es qué ve adentro.',
        'opens_everything' => 'Abre todo, incluso lo que se agregue mañana.',
        'opens_count' => 'Abre :count área|Abre :count áreas',

        'delete_title' => 'Eliminar el rol',
        'delete_body' => '¿Eliminamos el rol :role? No lo tiene nadie, así que no deja a ninguna persona afuera.',
        'delete_accept' => 'Eliminar el rol',

        'columns' => [
            'role' => 'Rol',
            'people' => 'Personas',
            'opens' => 'Qué abre',
        ],

        'fields' => [
            'name' => 'Clave del rol',
            'name_hint' => 'En minúsculas y sin espacios, como la usa el código: soporte, diseno, call-center.',
            'permissions' => 'Permisos',
        ],

        'areas' => [
            'businesses' => 'Negocios',
            'payments' => 'Cobros',
            'support' => 'Soporte',
            'moderation' => 'Moderación',
            'testimonials' => 'Testimonios',
            'ai' => 'Inteligencia artificial',
            'adoption' => 'Adopción',
            'catalog' => 'Catálogos',
            'company' => 'Compañía',
            'integrations' => 'Integraciones',
            'settings' => 'Ajustes de la plataforma',
            'users' => 'Usuarios',
            'roles' => 'Roles y permisos',
            'logs' => 'Logs del sistema',
        ],

        // Anidadas: la clave de un permiso lleva punto y `__()` lo lee como
        // profundidad, así que 'businesses.view' nunca se encontraría.
        'permissions' => [
            'businesses' => [
                'view' => 'Ver los negocios y su ficha',
                'manage' => 'Suspender o reactivar un negocio',
            ],
            'payments' => [
                'view' => 'Ver los pagos y sus comprobantes',
                'verify' => 'Acreditar o rechazar un pago',
            ],
            'support' => ['view' => 'Atender los reportes de los negocios'],
            'moderation' => ['view' => 'Ver lo que atrapó el filtro de contenido'],
            'testimonials' => ['moderate' => 'Aprobar o rechazar testimonios'],
            'ai' => [
                'view' => 'Ver cuánto consume la IA y qué cuesta',
                'manage' => 'Elegir el modelo de cada tarea y sus precios',
            ],
            'adoption' => ['view' => 'Ver dónde se traba cada negocio nuevo'],
            'catalogs' => ['manage' => 'Entrar al hub de catálogos'],
            'catalog' => [
                'country' => 'Países',
                'province' => 'Provincias',
                'region' => 'Regiones',
                'currency' => 'Monedas',
                'tax-condition' => 'Condiciones fiscales',
                'status' => 'Estados',
                'social-network' => 'Redes sociales',
                'business-sector' => 'Rubros',
                'business-activity' => 'Actividades',
                'service-modality' => 'Modalidades de servicio',
                'service-attribute' => 'Atributos de servicio',
                'service-type' => 'Tipos de servicio',
            ],
            'company' => ['manage' => 'Editar los datos de la compañía'],
            'integrations' => ['view' => 'Ver el estado de las integraciones'],
            'settings' => ['manage' => 'Cambiar los ajustes de la plataforma'],
            'users' => ['view' => 'Ver quién puede entrar al panel'],
            'manage-admin-users' => 'Crear usuarios y darles acceso',
            'roles' => ['manage' => 'Crear y editar roles'],
            'logs' => ['view' => 'Leer los logs del sistema'],
        ],
    ],

    'users' => [
        'title' => 'Usuarios y accesos',
        'sub' => 'Quién puede entrar a la plataforma, y quién entra de verdad.',
        'count' => ':count persona|:count personas',
        'empty' => 'Todavía no hay ninguna cuenta.',
        'no_match' => 'Ninguna persona coincide con lo que buscaste.',

        'search' => 'Buscar',
        'search_placeholder' => 'Nombre o correo',
        'all_states' => 'Todos los estados',

        'person' => 'Persona',
        'role' => 'Rol',
        'state' => 'Estado',
        'two_factor' => 'Doble factor',
        'two_factor_on' => 'Activo',
        'last_login' => 'Último acceso',
        'never' => 'Nunca entró',

        'new' => 'Nuevo usuario',
        'save' => 'Crear el usuario',
        'cancel' => 'Cancelar',
        'no_password' => 'No se elige contraseña: la persona recibe un correo y la define ella.',
        'created' => 'Listo. Le mandamos el correo de acceso a :email.',
        'resend' => 'Reenviar acceso',
        'verification_sent' => 'Correo reenviado a :email.',

        'fields' => [
            'name' => 'Nombre',
            'email' => 'Correo',
            'email_hint' => 'Ahí le llega el acceso. Una dirección de una cuenta cerrada sigue reservada.',
            'role' => 'Rol',
        ],

        'roles' => [
            'admin' => 'Super - Admin',
            'support' => 'Soporte',
        ],

        'states' => [
            'active' => 'Activa',
            'unverified' => 'Sin verificar',
            'closed' => 'Cerrada',
        ],
    ],

    'settings' => [
        'title' => 'Ajustes de la plataforma',
        'sub' => 'Lo que cambia cómo se comporta el producto, sin tocar código ni esperar un deploy.',
        'save' => 'Guardar los ajustes',
        'save_hint' => 'Se guardan juntos los que hayas cambiado.',
        'restore' => 'Volver a los valores del código',
        'saved' => 'Listo. :count ajuste ya está en vigencia.|Listo. :count ajustes ya están en vigencia.',
        'nothing_changed' => 'No cambiaste ningún ajuste.',
        'moved' => ':count ajuste cambiado|:count ajustes cambiados',

        'columns' => [
            'name' => 'Ajuste',
            'value' => 'Valor',
            'default' => 'Trae el código',
        ],

        'weekdays' => [
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
            7 => 'Domingo',
        ],

        'groups' => [
            'sends' => [
                'title' => 'Cuándo salen los mensajes automáticos',
                'sub' => 'Cada hora es la del negocio, no la del servidor: las 09:15 en Caracas son las 09:15 en Caracas.',
            ],
            'analysis' => [
                'title' => 'Cuándo se da una charla por terminada',
                'sub' => 'Una charla terminada es la que la IA lee entera para sacar temas y ánimo.',
            ],
            'handoff' => [
                'title' => 'Cuando la IA le pasa la charla a una persona',
                'sub' => 'Qué tan encima se le recuerda al equipo, y cuándo se da por ida a la persona.',
            ],
            'billing' => [
                'title' => 'Cobros',
                'sub' => 'Cuánto aire tiene un negocio después de la fecha de pago antes de que su asistente calle.',
            ],
            'referral' => [
                'title' => 'Referidos',
                'sub' => 'Lo que recibe un negocio que llega invitado por otro.',
            ],
        ],

        // Anidadas, no con el punto en la clave: `__()` lee el punto como
        // profundidad, así que 'analysis.idle_hours' nunca se encontraría.
        'keys' => [
            'schedule' => [
                'birthday_greetings' => [
                    'label' => 'Saludo de cumpleaños',
                    'hint' => 'A qué hora le llega el saludo al cliente del negocio que cumple años.',
                ],
                'whatsapp_digest' => [
                    'label' => 'Resumen del día',
                    'hint' => 'A qué hora recibe el negocio por WhatsApp el resumen de su jornada.',
                ],
                'billing_cycle' => [
                    'label' => 'Avisos de pago',
                    'hint' => 'A qué hora salen los recordatorios de pago y los avisos de gracia.',
                ],
                'knowledge_digest' => [
                    'time' => [
                        'label' => 'Resumen semanal: hora',
                        'hint' => 'A qué hora llega el recap de lo que la IA aprendió esa semana.',
                    ],
                    'weekday' => [
                        'label' => 'Resumen semanal: día',
                        'hint' => 'Qué día de la semana llega ese recap.',
                    ],
                ],
                'appointment_reminder_hours' => [
                    'label' => 'Recordatorio de turno',
                    'hint' => 'Cuántas horas antes del turno se le avisa al cliente.',
                ],
            ],
            'analysis' => [
                'idle_hours' => [
                    'label' => 'Silencio para dar por terminada una charla',
                    'hint' => 'Horas sin que nadie escriba. Más bajo, la IA analiza antes pero puede cortar una charla viva; más alto, los temas del día aparecen más tarde.',
                ],
            ],
            'handoff' => [
                'reminder_minutes' => [
                    'label' => 'Recordatorio al equipo',
                    'hint' => 'Minutos sin que nadie conteste una charla derivada antes de volver a avisarle al equipo.',
                ],
                'customer_idle_hours' => [
                    'label' => 'Cliente que no volvió',
                    'hint' => 'Horas sin respuesta del cliente para dar la derivación por cerrada.',
                ],
            ],
            'billing' => [
                'grace_days' => [
                    'label' => 'Días de gracia',
                    'hint' => 'Días después del vencimiento en los que el asistente sigue atendiendo. Pasados, se pausa.',
                ],
            ],
            'referral' => [
                'invited_trial_days' => [
                    'label' => 'Prueba del invitado',
                    'hint' => 'Días de prueba que recibe un negocio que llega por el link de otro.',
                ],
                'founders' => [
                    'label' => 'Cupos de fundador',
                    'hint' => 'Cuántos negocios entran como fundadores antes de que el beneficio se cierre.',
                ],
            ],
        ],
    ],

    'ai_usage' => [
        'title' => 'Consumo de IA',
        'sub' => 'Qué consumió cada negocio en el mes y cuánto te costó atenderlo.',
        'month' => 'Mes',
        'measured' => 'Medido al :when',
        'previous' => 'Mes anterior',
        'no_price' => 'El total deja afuera :count llamada: su modelo no tiene precio publicado.|El total deja afuera :count llamadas: su modelo no tiene precio publicado.',
        'no_price_short' => ':count llamada sin precio|:count llamadas sin precio',
        'empty_title' => 'Nada medido en este mes',
        'empty' => 'Cuando un negocio use su asistente, su consumo aparece acá.',
        'platform' => 'Plataforma',
        'platform_hint' => 'La demo del sitio y lo que corre la plataforma.',
        'unpriced' => 'sin precio',
        'business' => 'Negocio',
        'per_thread' => 'Por conversación',
        'over_plan' => 'Se come :share% de su plan',
        'threads' => 'Conversaciones',
        'messages' => 'Mensajes',
        'audio' => 'Audio',
        'calls' => 'Llamadas',
        'tokens' => 'Tokens',
        'cached' => 'Cacheado',
        'cost' => 'Costo',
        'month_total' => 'Total del mes',
        'saved' => '−:amount',
        'saved_hint' => 'Bajo cada % está lo que el caché le quitó a la factura.',
        'trend_label' => 'Consumo de los últimos 12 meses de :name',
        'trend_since' => 'La tendencia cubre :count meses, desde :month',
        'previous_is' => 'Mes anterior: :amount',
        'tokens_in' => 'Entrada',
        'tokens_cached' => 'Cacheada',
        'tokens_out' => 'Salida',
        'by_kind' => 'Desglose por tipo de llamada',
        'kind' => 'Tipo',

        'mail' => [
            'eyebrow' => 'Consumo de IA',
            'subject' => ':count negocio se está comiendo su plan|:count negocios se están comiendo su plan',
            'preheader' => 'La IA de estos negocios pasó el umbral de su plan.',
            'intro' => 'En :month, estos negocios pasaron el umbral de consumo que tiene su plan.',
            'line' => ':name — :cost, el :share% de su plan (umbral: :limit%)',
            'cta' => 'Ver el consumo',
            'closing' => 'Mientras sigan arriba del umbral, este aviso vuelve cada lunes.',
        ],
    ],

    'businesses' => [
        'title' => 'Negocios',
        'sub' => 'Todos los que atiende la plataforma, con su plan y su plata.',
        'empty' => 'Todavía no hay ningún negocio dado de alta.',
        'count' => ':count negocio|:count negocios',

        'name' => 'Negocio',
        'plan' => 'Plan',
        'state' => 'Estado',
        'due' => 'Vence',
        'owes' => 'Debe',
        'open' => 'Ver la ficha',
        'close' => 'Cerrar la ficha',

        'all_states' => 'Todos los estados',
        'search' => 'Buscar',
        'search_placeholder' => 'Nombre del negocio',
        'no_match' => 'Ningún negocio coincide con lo que buscaste.',

        'states' => [
            'problem' => 'En gracia o pausado',
            'trialing' => 'En prueba',
            'active' => 'Al día',
            'past_due' => 'En gracia',
            'paused' => 'Pausado',
            'suspended' => 'Suspendido',
            'canceling' => 'Se da de baja',
            'ended' => 'Dado de baja',
            'none' => 'Sin plan',
        ],

        'card' => [
            'plan' => 'Plan y cobro',
            'cycle' => 'Ciclo',
            'monthly' => 'Mensual',
            'yearly' => 'Anual',
            'next_amount' => 'Próximo cobro',
            'next_date' => 'Próximo vencimiento',
            'payments' => 'Últimos pagos',
            'payments_empty' => 'Todavía no pagó nada.',
            'date' => 'Fecha',
            'concept' => 'Concepto',
            'amount' => 'Monto',
            'status' => 'Estado',
        ],

        'cancel' => [
            'title' => 'Baja',
            'hint' => 'Sigue andando hasta el :date, que es lo que ya pagó.',
            'hint_no_date' => 'No tiene un período pagado en curso.',
            'action' => 'Registrar la baja',
            'accept' => 'Registrar la baja',
            'confirm' => '¿Registrás la baja de :name? Su asistente sigue respondiendo hasta el :date.',
            'confirm_no_date' => '¿Registrás la baja de :name?',
            'asked_on' => 'Pidió la baja el :date.',
            'ends_on' => 'Termina el :date.',
            'ended_on' => 'Terminó el :date.',
            'undo' => 'Deshacer la baja',
            'undo_confirm' => '¿Deshacés la baja de :name? Vuelve a ser un cliente normal.',
            'done' => 'Baja registrada.',
            'undone' => 'Baja deshecha.',
            'no_subscription' => 'Este negocio no tiene plan: no hay baja que registrar.',
        ],
    ],

    'home' => [
        'title' => 'Inicio',
        'sub' => 'Lo que está esperando una decisión tuya, ahora mismo.',
        'as_of' => 'Al :date',

        'tiles' => [
            'receipts' => 'Comprobantes por verificar',
            'receipts_none' => 'Nada por verificar',
            'receipts_order' => 'El que pagó primero, primero',
            'receipts_waiting' => 'El más viejo espera hace :days día|El más viejo espera hace :days días',
            'receipts_waiting_today' => 'El más viejo llegó hoy',
            'mrr_collected' => 'Cobrado en 30 días: USD :amount',
            'leaving' => 'Bajas programadas',
            'leaving_none' => 'Nadie se está yendo',
            'leaving_amount' => 'Se deja de cobrar USD :amount por mes',
            'plan_changes' => 'Cambios de plan por verificar',
            'plan_changes_hint' => 'De esos comprobantes, los que mueven de plan',
            'mrr' => 'Ingreso mensual recurrente',
            'mrr_from' => ':count suscripciones pagando',
            'mrr_none' => 'Todavía nadie paga: :count en prueba',
        ],

        'queue' => [
            'title' => 'Comprobantes esperando',
            'sub' => 'Acreditá acá mismo. Rechazar pide un motivo y se hace en Pagos.',
            'empty' => 'No hay comprobantes esperando.',
            'date' => 'Llegó',
            'business' => 'Negocio',
            'plan' => 'Plan',
            'amount' => 'Monto',
            'action' => 'Acción',
            'approve' => 'Acreditar',
            'see_all' => 'Ver los :count en Pagos',
        ],

        'renewals' => [
            'tab' => 'Renovaciones',
            'title' => 'Renovaciones de esta semana',
            'sub' => 'Lo que vence en los próximos 7 días, lo más cerca primero.',
            'vs_last_week' => 'La semana pasada se cobró USD :amount',
            'expected' => 'Se esperan USD :amount',
            'empty' => 'Nada vence esta semana.',
            'business' => 'Negocio',
            'plan' => 'Plan',
            'cycle' => 'Ciclo',
            'due' => 'Vence',
            'amount' => 'Monto',
            'total' => 'Total de la semana',
            'monthly' => 'Mensual',
            'yearly' => 'Anual',
            'trial' => 'En prueba',
        ],

        'leaving' => [
            'tab' => 'Bajas',
            'title' => 'Bajas programadas',
            'sub' => 'Pidieron la baja y siguen andando hasta la fecha que ya pagaron.',
            'empty' => 'Ningún negocio pidió la baja.',
            'business' => 'Negocio',
            'plan' => 'Plan',
            'amount' => 'Monto',
            'ends' => 'Termina',
            'asked' => 'Pidió',
        ],

        'struggling' => [
            'tab' => 'Vencidos',
            'see_all' => 'Ver los vencidos y pausados en Negocios',
            'title' => 'Vencidos y pausados',
            'sub' => 'No pagaron: en gracia, o ya con el asistente callado.',
            'empty' => 'Ningún negocio vencido ni pausado.',
            'business' => 'Negocio',
            'plan' => 'Plan',
            'state' => 'Estado',
            'since' => 'Venció',
            'past_due' => 'En gracia',
            'paused' => 'Pausado',
        ],
    ],

];
