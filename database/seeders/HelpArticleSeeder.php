<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\HelpArticle;
use Illuminate\Database\Seeder;

/**
 * The answers to the questions the panel actually produces. Each title is the
 * task as the owner would type it, the answer lands in the first lines and the
 * steps are one action each — that is the shape that deflects a ticket.
 */
class HelpArticleSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->articles() as $article) {
            HelpArticle::query()->updateOrCreate(['slug' => $article['slug']], $article);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function articles(): array
    {
        return [
            [
                'slug' => 'conectar-whatsapp',
                'category' => 'whatsapp',
                'screen' => 'whatsapp',
                'sort_order' => 1,
                'title' => 'Cómo conecto mi WhatsApp',
                'keywords' => 'qr codigo vincular enlazar telefono numero',
                'body' => <<<'TXT'
                    Se conecta escaneando un código QR desde el WhatsApp del teléfono que atiende a tus clientes. Tarda menos de un minuto y no hace falta instalar nada.

                    1. Entra a WhatsApp en el menú de la izquierda.
                    2. Toca "Conectar mi WhatsApp": aparece el código QR.
                    3. En tu teléfono abre WhatsApp → Dispositivos vinculados → Vincular un dispositivo.
                    4. Apunta la cámara al código de la pantalla.

                    El código se renueva cada 45 segundos. Si se venció, toca "Generar otro" y repite el paso 4.
                    TXT,
            ],
            [
                'slug' => 'whatsapp-se-desconecto',
                'category' => 'whatsapp',
                'screen' => 'whatsapp',
                'sort_order' => 2,
                'title' => 'Mi asistente dejó de responder',
                'keywords' => 'desconectado caido no responde apagado offline',
                'body' => <<<'TXT'
                    Casi siempre es la sesión de WhatsApp que se cerró. Lo ves en la barra de arriba: si dice "WhatsApp sin conectar", tu asistente no está recibiendo mensajes.

                    1. Revisa que el teléfono tenga internet y batería.
                    2. En tu teléfono: WhatsApp → Dispositivos vinculados. Si AtendIa no está en la lista, la sesión se cerró.
                    3. Entra a WhatsApp en el panel y vuelve a escanear el código.

                    Si el chip está verde y aun así no responde, repórtalo: puede ser algo nuestro.
                    TXT,
            ],
            [
                'slug' => 'importar-productos-excel',
                'category' => 'catalogo',
                'screen' => 'my-products',
                'sort_order' => 1,
                'title' => 'Cómo subo mi lista de productos desde Excel',
                'keywords' => 'importar planilla csv xlsx cargar masivo lista precios',
                'body' => <<<'TXT'
                    Sube la planilla tal como la tienes: leemos las columnas solas y tú confirmas antes de guardar nada.

                    1. Entra a Productos y baja hasta "Importar desde Excel".
                    2. Arrastra el archivo (.xlsx o .csv, hasta 10 MB).
                    3. Revisa cómo interpretamos cada columna y corrige lo que haga falta.
                    4. Confirma: los productos quedan cargados y tu asistente ya los conoce.

                    Si una fila no tiene nombre, se saltea. El precio puede venir con símbolos o puntos: lo entendemos igual.
                    TXT,
            ],
            [
                'slug' => 'producto-no-aparece',
                'category' => 'catalogo',
                'screen' => 'my-products',
                'sort_order' => 2,
                'title' => 'Cargué un producto y mi asistente no lo menciona',
                'keywords' => 'no aparece no lo dice invisible pausado inactivo',
                'body' => <<<'TXT'
                    Lo más común es que el producto esté pausado. Un producto pausado sigue en tu lista pero tu asistente no lo ofrece.

                    1. Entra a Productos y busca el producto por nombre o código.
                    2. Mira el interruptor de la fila: si está apagado, está pausado.
                    3. Enciéndelo y listo.

                    Si está activo y aun así no lo menciona, revisa que tenga descripción: con solo el nombre, tu asistente tiene poco que contar.
                    TXT,
            ],
            [
                'slug' => 'escribir-descripciones-ia',
                'category' => 'catalogo',
                'screen' => 'my-services',
                'sort_order' => 1,
                'title' => 'No sé qué escribir en las descripciones',
                'keywords' => 'descripcion texto redactar ia optimizar vacio',
                'body' => <<<'TXT'
                    Nuestra inteligencia artificial las escribe por ti, para todos los que estén en blanco, en una sola pasada.

                    1. Entra a Servicios (o a Productos).
                    2. Toca "Optimizar con IA" en el recuadro de arriba.
                    3. Espera unos segundos: te decimos cuántas escribimos.

                    Solo completa las que están vacías: lo que ya escribiste tú nunca se pisa. Puedes editar cualquiera después.
                    TXT,
            ],
            [
                'slug' => 'invitar-equipo',
                'category' => 'equipo',
                'screen' => 'team',
                'sort_order' => 1,
                'title' => 'Cómo invito a alguien a atender conmigo',
                'keywords' => 'equipo agente invitar sumar persona empleado',
                'body' => <<<'TXT'
                    Le mandas una invitación por correo o WhatsApp y esa persona entra con su propia clave. Ve las conversaciones y los clientes; no ve tu plan, tus pagos ni tus estadísticas.

                    1. Entra a Equipo y toca "Invitar".
                    2. Escribe su nombre y su correo, y elige en qué departamentos atiende.
                    3. Envía: le llega un enlace que vence a los 7 días.

                    Mientras no acepte, puedes cancelar la invitación desde la misma pantalla.
                    TXT,
            ],
            [
                'slug' => 'cambiar-plan',
                'category' => 'plan',
                'screen' => 'plan',
                'sort_order' => 1,
                'title' => 'Cómo cambio de plan',
                'keywords' => 'plan upgrade bajar subir precio mensual anual pagar',
                'body' => <<<'TXT'
                    Desde Mi plan ves el que tienes y los demás con su precio. El cambio toma efecto cuando confirmamos tu pago.

                    1. Entra a Plan y pagos → Mi plan.
                    2. Elige el plan y si lo quieres mensual o anual.
                    3. Haz la transferencia y sube el comprobante.

                    Verificamos el comprobante a mano: te avisamos por aquí mismo cuando quede acreditado.
                    TXT,
            ],
            [
                'slug' => 'asistente-responde-mal',
                'category' => 'asistente',
                'screen' => 'assistant',
                'sort_order' => 1,
                'title' => 'Mi asistente respondió algo que no corresponde',
                'keywords' => 'respuesta mala equivocada ensenar corregir entrenar',
                'body' => <<<'TXT'
                    Puedes enseñarle la respuesta correcta y la usa de ahí en adelante. No hace falta esperar a nadie.

                    1. Entra a Conversaciones y abre la charla donde pasó.
                    2. Busca el mensaje y toca "Enseñar respuesta".
                    3. Escribe cómo debería haber contestado y guarda.

                    Lo que le enseñas queda en "Lo que sabe tu asistente", donde puedes editarlo o borrarlo cuando quieras.
                    TXT,
            ],
            [
                'slug' => 'que-ve-mi-cliente',
                'category' => 'asistente',
                'screen' => null,
                'sort_order' => 1,
                'title' => 'Qué ve mi cliente cuando le escribe a mi negocio',
                'keywords' => 'cliente ve prueba simulador como responde presentacion',
                'body' => <<<'TXT'
                    Ve una conversación normal de WhatsApp con tu nombre y tu foto. Tu asistente se presenta con la descripción que cargaste en Mi negocio.

                    1. Entra a Mi negocio y toca "Pruébalo ahora".
                    2. Escribe como escribiría un cliente y mira cómo responde.
                    3. Si algo no te gusta, edita la descripción o los horarios y vuelve a probar.

                    Lo que pruebas ahí no le llega a nadie: es una simulación con tus datos reales.
                    TXT,
            ],
        ];
    }
}
