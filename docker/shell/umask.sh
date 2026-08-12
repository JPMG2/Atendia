# umask 002 para cualquier shell dentro del contenedor.
#
# Por qué: Laravel crea archivos/directorios con `0777 - umask()`. Con el umask
# por defecto (022) eso da 0755 — SIN permiso de escritura para el grupo. Si el
# archivo lo creó root (artisan/pest corrido como root desde la terminal), php-fpm
# corre como www-data y no puede escribir ahí:
#
#   ErrorException: tempnam(): file created in the system's temporary directory
#   (Filesystem.php:222 -> storage/framework/views/livewire/ es root:root 0755)
#
# Con umask 002 los archivos nacen 0775 y, como storage/ y bootstrap/cache tienen
# el bit setgid, heredan el grupo www-data. Da igual quién los cree: php-fpm
# siempre puede escribir. Es la contraparte determinística de la guía.
#
# Memorias relacionadas: atendia-livewire-cache-perms · atendia-permisos-acl
umask 002
