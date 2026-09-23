<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Ajustes de la cuenta — copy de la pantalla /ajustes
|--------------------------------------------------------------------------
|
| Base NEUTRA (tuteo). Los verbos en segunda persona tienen override de
| voseo en `lang/es_AR/settings.php`. Dispositivos y chequeo siguen en
| `profile.php`.
|
*/

return [

    'title' => 'Ajustes',
    'sub' => 'Tu nombre, tu correo de acceso y la seguridad de tu cuenta.',
    'back' => 'Volver al inicio',
    'current_password' => 'Tu contraseña actual',
    'save' => 'Guardar cambios',
    'unsaved_pill' => 'Sin guardar',
    'on_this_page' => 'En esta página',

    'nav' => [
        'perfil' => 'Tu perfil',
        'correo' => 'Correo de acceso',
        'contrasena' => 'Contraseña',
        'dos-pasos' => 'Verificación en dos pasos',
        'dispositivos' => 'Dispositivos',
        'actividad' => 'Actividad reciente',
        'cuenta' => 'Eliminar mi cuenta',
    ],

    'profile' => [
        'title' => 'Tu perfil',
        'sub' => 'Es el nombre que ve tu equipo en el panel y con el que te saludamos en los correos.',
        'name' => 'Nombre y apellido',
        'member_since' => 'Miembro desde',
        'photo' => 'Foto de perfil',
        'photo_hint' => 'PNG, JPG o WebP, hasta 5 MB.',
        'saved' => 'Listo. Tu perfil quedó al día.',
        'photo_removed' => 'Listo. Quitamos tu foto: vuelven tus iniciales.',
        'photo_remove_title' => '¿Quitar tu foto de perfil?',
        'photo_remove_message' => 'En su lugar se van a ver tus iniciales.',
        'photo_remove_accept' => 'Quitar la foto',
    ],

    'email' => [
        'title' => 'Correo de acceso',
        'sub' => 'Con este correo entras a Atendia y te llegan los avisos de seguridad. Para cambiarlo, confirmamos que el nuevo sea tuyo.',
        'verified' => 'Verificado',
        'unverified' => 'Sin verificar',
        'current' => 'Correo actual',
        'new' => 'Nuevo correo',
        'new_placeholder' => 'nombre@tunegocio.com',
        'pending' => 'Te mandamos un enlace a :email. Hasta que lo confirmes, sigues entrando con tu correo actual.',
        'resend' => 'Reenviar enlace',
        'cancel' => 'Cancelar el cambio',
        'notice' => 'A tu correo actual le avisamos del cambio.',
        'submit' => 'Cambiar correo',
        'sent' => 'Listo. Te mandamos un enlace a :email para confirmar el cambio.',
        'cancelled' => 'Cancelaste el cambio: sigues entrando con tu correo actual.',
        'nothing_pending' => 'No hay ningún cambio de correo pendiente.',
        'verify' => 'Enviarme el enlace para verificarlo',
        'verify_sent' => 'Listo. Te mandamos el enlace a :email.',
        'already_verified' => 'Tu correo ya está verificado.',
        'resend_throttled' => 'Ya te mandamos varios enlaces. Espera :minutes min antes de pedir otro.',
    ],

    'password' => [
        'title' => 'Contraseña',
        'sub' => 'Usa al menos 8 caracteres, con una mayúscula, un número y un símbolo. Nunca te la vamos a pedir por WhatsApp.',
        'new' => 'Contraseña nueva',
        'confirm' => 'Repite la contraseña nueva',
        'logout_others' => 'Cerrar la sesión en los otros dispositivos',
        'notice' => 'Te avisamos por correo cada vez que cambie.',
        'submit' => 'Cambiar contraseña',
        'changed' => 'Listo. Tu contraseña cambió.',
        'changed_and_closed' => 'Listo. Tu contraseña cambió y cerramos las otras sesiones.',
    ],

    'two_factor' => [
        'title' => 'Verificación en dos pasos',
        'sub' => 'Cuando alguien entre a tu cuenta desde un dispositivo nuevo, el código llega a tu WhatsApp en lugar de tu correo. Aunque se filtre tu correo, sin tu teléfono no entran.',
        'active' => 'Activa',
        'inactive' => 'Apagada',
        'active_detail' => 'Los códigos llegan a tu WhatsApp :phone.',
        'target' => 'Te vamos a mandar el código a tu WhatsApp :phone.',
        'no_phone' => 'Para activarla, carga tu WhatsApp personal en Mi negocio → Contacto.',
        'go_contact' => 'Cargar mi WhatsApp',
        'send' => 'Enviarme un código',
        'code' => 'Código de 6 dígitos',
        'confirm' => 'Activar',
        'disable' => 'Desactivar',
        'code_sent' => 'Listo. Te mandamos un código por WhatsApp.',
        'codes_warning' => 'Guarda estos códigos de respaldo en un lugar seguro: sirven para entrar si pierdes el teléfono. Cada uno se usa una sola vez y no los vamos a volver a mostrar.',
        'codes_copy' => 'Copiar los códigos',
        'codes_copied' => 'Copiados',
        'codes_done' => 'Ya los guardé',
        'codes_left' => '{0} No te quedan códigos de respaldo: genera nuevos.|{1} Te queda :count código de respaldo.|[2,*] Te quedan :count códigos de respaldo.',
        'codes_regenerate' => 'Generar códigos nuevos',
        'codes_regenerated' => 'Listo. Los códigos anteriores dejaron de servir.',
        'send_failed' => 'WhatsApp no respondió y no pudimos mandarte el código. Prueba de nuevo en unos minutos.',
        'wrong_code' => 'Ese código no es o ya venció. Pide uno nuevo si hace falta.',
        'enabled' => 'Listo. La verificación en dos pasos quedó activa.',
        'disabled' => 'Desactivaste la verificación en dos pasos: los códigos vuelven a llegar por correo.',
        'send_throttled' => 'Ya te mandamos varios códigos. Espera :minutes min antes de pedir otro.',
    ],

    'close' => [
        'title' => 'Eliminar mi cuenta',
        'sub' => 'Se cierran tu acceso y tu negocio: tu asistente deja de atender. Guardamos todo durante :days días por si quieres volver.',
        'sub_no_business' => 'Se cierra tu acceso. Guardamos todo durante :days días por si quieres volver.',
        'keyword' => 'ELIMINAR',
        'confirmation' => 'Escribe :keyword para confirmar',
        'confirmation_attribute' => 'confirmación',
        'submit' => 'Eliminar mi cuenta',
        'confirm_title' => '¿Eliminar tu cuenta?',
        'confirm_message' => 'Tu asistente deja de atender ahora mismo. Tienes :days días para volver con todo tal como estaba.',
        'confirm_accept' => 'Eliminar mi cuenta',
        'done' => 'Tu cuenta quedó cerrada.',
    ],

    'links' => [
        'confirmed_title' => 'Tu correo quedó al día',
        'confirmed_body' => 'Desde ahora entras a Atendia con :email.',
        'verified_title' => 'Tu correo quedó verificado',
        'verified_body' => ':email ya está confirmado. Puedes volver a Atendia desde cualquier dispositivo.',
        'invalid_title' => 'Este enlace ya no sirve',
        'invalid_body' => 'Venció, ya se usó o hubo un cambio más nuevo. Pide otro desde tus ajustes.',
        'cancelled_title' => 'Frenamos el cambio',
        'cancelled_body' => 'Tu correo de acceso sigue igual. Si no fuiste tú quien lo pidió, cambia tu contraseña ahora.',
        'restored_title' => 'Tu cuenta volvió',
        'restored_body' => 'Restauramos tu cuenta y tu negocio tal como estaban. Inicia sesión para seguir.',
        'restore_expired_title' => 'Ya no se puede restaurar desde aquí',
        'restore_expired_body' => 'Pasó el plazo para volver solo. Escríbenos y lo revisamos contigo.',
        'to_settings' => 'Ir a mis ajustes',
        'to_login' => 'Iniciar sesión',
        'change_password' => 'Cambiar mi contraseña',
    ],

];
