<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SupportReply;
use Illuminate\Database\Seeder;

class SupportReplySeeder extends Seeder
{
    /**
     * A first shelf, so the composer is not empty on day one. She renames,
     * rewrites or switches off any of them from the Catalogs hub: the seeder
     * only creates what is missing, it never overwrites her edits.
     */
    public function run(): void
    {
        $replies = [
            'Reconectar el WhatsApp' => 'Para reconectar tu WhatsApp entra a Conexión de WhatsApp, toca Reconectar y escanea el código QR con el teléfono del negocio. Si no te deja, avísanos y lo vemos juntos.',
            'Ya lo arreglamos' => 'Ya lo arreglamos. Entra de nuevo a tu panel y prueba otra vez; si sigue igual, cuéntanos qué ves y lo revisamos enseguida.',
            'Necesitamos más datos' => 'Para encontrar el problema nos falta un dato: ¿en qué pantalla te pasa y qué estabas haciendo justo antes? Si puedes, envíanos una captura.',
            'Lo estamos revisando' => 'Gracias por avisarnos. Ya lo tenemos en revisión con el equipo técnico y te escribimos apenas tengamos una respuesta.',
            'Idea recibida' => 'Gracias por la idea. La anotamos y la tenemos en cuenta para lo que viene; si la construimos, te avisamos.',
        ];

        foreach ($replies as $name => $body) {
            SupportReply::query()->firstOrCreate(['name' => $name], ['body' => $body, 'is_active' => true]);
        }
    }
}
