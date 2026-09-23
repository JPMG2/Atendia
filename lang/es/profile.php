<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Perfil de la cuenta — copy de la pantalla /profile
|--------------------------------------------------------------------------
|
| Base NEUTRA (tuteo). Los verbos en segunda persona tienen override de
| voseo en `lang/es_AR/profile.php`.
|
*/

return [

    'devices' => [
        'title' => 'Dispositivos conectados',
        'sub' => 'Las sesiones abiertas de tu cuenta. Si no reconoces un dispositivo, ciérralo y cambia tu contraseña.',
        'current' => 'Este dispositivo',
        'revoke_all' => 'Cerrar las otras sesiones',
        'revoke_all_hint' => 'Confirma con tu contraseña y todas las sesiones menos esta quedan fuera.',
        'confirm_password' => 'Tu contraseña',
        'revoke_all_confirm' => 'Cerrar sesiones',
        'cancel' => 'Cancelar',
        'revoke_all_done' => 'Listo. Todas las otras sesiones quedaron fuera.',
        'last_seen' => 'Última entrada',
        'unusual' => 'Ubicación inusual',
        'revoke' => 'Cerrar sesión',
        'empty' => 'Todavía no hay dispositivos registrados: van a aparecer con el próximo inicio de sesión.',
        'revoked' => 'Listo. Ese dispositivo quedó fuera de tu cuenta.',
        'current_refused' => 'Este es el dispositivo que estás usando: para salir, usa "Cerrar sesión" del menú.',
        'confirm_title' => '¿Cerrar la sesión de ese dispositivo?',
        'confirm_message' => 'Va a tener que iniciar sesión de nuevo para volver a entrar.',
        'confirm_accept' => 'Cerrar la sesión',
        'new_device_toast' => 'Tu cuenta inició sesión en un dispositivo nuevo (:label). Si no fuiste tú, revisa tus dispositivos.',
        'review_action' => 'Revisar dispositivos',
    ],

    'checkup' => [
        'title' => 'Chequeo de seguridad',
        'email_ok' => 'Correo verificado',
        'email_ok_detail' => 'Tu dirección está confirmada.',
        'email_pending' => 'Correo sin verificar',
        'email_pending_detail' => 'Confirma tu dirección para proteger la cuenta.',
        'password' => 'Contraseña protegida',
        'password_changed' => 'La cambiaste :ago.',
        'password_since_signup' => 'Sin cambios desde que creaste la cuenta, :ago.',
        'email_verify_link' => 'Verificarlo ahora',
        'devices' => 'Dispositivos conectados',
        'devices_detail' => ':count con acceso a tu cuenta.',
        'devices_warn_detail' => 'Hay un ingreso desde una ubicación inusual: revísalo abajo.',
        'score' => ':score de :total al día',
        'last_login' => 'Último inicio de sesión',
        'last_login_empty' => 'Sin registros todavía.',
    ],

    'activity' => [
        'title' => 'Actividad reciente',
        'sub' => 'Los últimos inicios de sesión de tu cuenta, con lugar y hora.',
        'empty' => 'Todavía no hay actividad registrada: aparece con el próximo inicio de sesión.',
    ],

];
