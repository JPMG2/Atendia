<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\HelpArticle;
use Illuminate\Database\Seeder;

/**
 * The answers to the questions the panel actually produces: the title is the
 * task as the owner would type it, the answer comes first, one action per step.
 * Everything was checked against what the product does today, with the labels as
 * the screen prints them. What is not built does not appear, and figures that
 * live in `plans` or in config are never written down: they change, articles don't.
 */
class HelpArticleSeeder extends Seeder
{
    public function run(): void
    {
        // Retired: it said products can be paused, and they cannot. Two articles replace it.
        HelpArticle::query()->where('slug', 'producto-no-aparece')->delete();

        foreach ($this->articles() as $data) {
            $article = HelpArticle::query()->firstOrNew(['slug' => $data['slug']]);
            $article->fill($data);

            // The vector was made from the OLD words: left in place it would find
            // the article by what it used to say. `atendia:embed-help` refills it.
            if ($article->isDirty(['title', 'keywords', 'body'])) {
                $article->embedding = null;
            }

            $article->save();
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
                'keywords' => 'qr codigo vincular enlazar telefono numero escanear',
                'body' => <<<'TXT'
                    Se conecta escaneando un código QR desde el WhatsApp del teléfono que atiende a tus clientes. Tarda menos de un minuto y no hace falta instalar nada.

                    1. Entra a WhatsApp en el menú de la izquierda.
                    2. Toca "Conectar mi WhatsApp": aparece el código QR.
                    3. En tu teléfono abre WhatsApp → Dispositivos vinculados → Vincular un dispositivo.
                    4. Apunta la cámara al código de la pantalla.

                    El código se renueva solo: no cierres la pantalla hasta que veas "Tu WhatsApp está conectado".

                    Usa un número exclusivo para tu negocio. Si conectas uno personal, tu asistente también le responde a tus familiares y amigos.
                    TXT,
            ],
            [
                'slug' => 'whatsapp-se-desconecto',
                'category' => 'whatsapp',
                'screen' => 'whatsapp',
                'sort_order' => 2,
                'title' => 'Mi asistente dejó de responder',
                'keywords' => 'desconectado caido no responde apagado offline sin conectar pausa',
                'body' => <<<'TXT'
                    Casi siempre es la sesión de WhatsApp que se cerró. Lo ves en la barra de arriba: si dice "WhatsApp sin conectar", tu asistente no está recibiendo mensajes. También te avisamos en la campana.

                    1. Revisa que el teléfono tenga internet.
                    2. En tu teléfono abre WhatsApp → Dispositivos vinculados. Si :brand no está en la lista, la sesión se cerró.
                    3. Entra a WhatsApp en el panel, toca "Conectar mi WhatsApp" y escanea el código otra vez.

                    Si tu pago quedó vencido más allá de los días de gracia, o tu cuenta está suspendida, el asistente también se pausa: lo ves en un aviso en la parte de arriba de la pantalla.

                    Si la barra dice "WhatsApp conectado" y aun así no responde, repórtalo: puede ser algo nuestro.
                    TXT,
            ],
            [
                'slug' => 'whatsapp-no-sale-el-qr',
                'category' => 'whatsapp',
                'screen' => 'whatsapp',
                'sort_order' => 4,
                'title' => 'No me aparece el código QR',
                'keywords' => 'qr codigo no aparece no sale en blanco cargando conectar mi whatsapp escanear',
                'body' => <<<'TXT'
                    Si tocas "Conectar mi WhatsApp" y el código QR no aparece, casi siempre es la conexión de internet o una pantalla que quedó a medias.

                    1. Revisa que tu computador o teléfono tenga internet.
                    2. Recarga la página y toca "Conectar mi WhatsApp" otra vez.
                    3. Espera unos segundos: el código tarda un momento en dibujarse.

                    Recuerda que el código se renueva solo, así que si ya lo ves, escanéalo sin cerrar la pantalla.

                    Si después de recargar sigue sin aparecer, repórtalo desde Ayuda → "Reportar un problema" y cuéntanos qué ves en lugar del código.
                    TXT,
            ],
            [
                'slug' => 'whatsapp-sin-verificar',
                'category' => 'whatsapp',
                'screen' => 'whatsapp',
                'sort_order' => 3,
                'title' => 'Mi WhatsApp dice "sin verificar"',
                'keywords' => 'no pudimos comprobar conexion verificar estado volver a comprobar',
                'body' => <<<'TXT'
                    Quiere decir que ahora mismo no pudimos comprobar si tu WhatsApp está conectado. No es lo mismo que "sin conectar": no significa que se haya cerrado la sesión.

                    1. Entra a WhatsApp en el menú de la izquierda.
                    2. Toca "Volver a comprobar".
                    3. Si sigue igual unos minutos después, repórtalo desde Ayuda → "Reportar un problema".

                    Mientras tanto, revisa en tu teléfono que :brand siga en WhatsApp → Dispositivos vinculados.
                    TXT,
            ],
            [
                'slug' => 'numero-personal-o-business',
                'category' => 'whatsapp',
                'screen' => 'whatsapp',
                'sort_order' => 4,
                'title' => 'Puedo usar mi número personal o WhatsApp Business',
                'keywords' => 'business personal numero exclusivo saludo ausencia mensaje automatico familiares',
                'body' => <<<'TXT'
                    Los dos se conectan igual, con el código QR. Para tu negocio conviene un número exclusivo: con uno personal tu asistente también responde a familiares y amigos, y los chats se mezclan.

                    1. Si usas WhatsApp Business, abre Herramientas para la empresa → Mensaje de saludo y desactívalo.
                    2. Haz lo mismo con Mensaje de ausencia: si quedan activos, pueden salir a la vez que tu asistente.
                    3. Conecta el número desde WhatsApp en el panel, con "Conectar mi WhatsApp".

                    Si vas a estrenar un número, conviene usarlo unos días antes de conectarlo.
                    TXT,
            ],

            [
                'slug' => 'asistente-responde-mal',
                'category' => 'asistente',
                'screen' => 'conversations',
                'sort_order' => 1,
                'title' => 'Mi asistente respondió algo que no corresponde',
                'keywords' => 'respuesta mala equivocada ensenar corregir entrenar error incorrecta',
                'body' => <<<'TXT'
                    Puedes enseñarle la respuesta correcta y la usa de ahí en adelante. No hace falta esperar a nadie.

                    1. Entra a Conversaciones y abre la charla donde pasó.
                    2. En la respuesta del asistente, toca el pulgar hacia abajo ("Esta respuesta estuvo mal, corregirla"). En la computadora el icono aparece al pasar el mouse sobre el mensaje; en el celular se ve siempre.
                    3. Escribe cómo debería haber contestado y guarda.

                    Cuando detectamos que una respuesta falló, además aparece un icono de birrete ("Enseñarle esto a tu asistente") que hace lo mismo.

                    Lo que le enseñas queda en Mi asistente → Conocimiento, en "Lo que le enseñas tú", donde puedes probarlo o eliminarlo.

                    Cuando una respuesta usó tu información, ves "¿De dónde salió?" para saber de qué la sacó.
                    TXT,
            ],
            [
                'slug' => 'ensenar-algo-nuevo',
                'category' => 'asistente',
                'screen' => 'assistant',
                'sort_order' => 2,
                'title' => 'Cómo le enseño algo nuevo a mi asistente',
                'keywords' => 'ensenar entrenar conocimiento pregunta respuesta aprender agregar informacion',
                'body' => <<<'TXT'
                    Le escribes una pregunta con su respuesta y la usa desde ese momento.

                    1. Entra a Mi asistente → Conocimiento.
                    2. Baja a "Lo que le enseñas tú" y toca "Enseñarle algo".
                    3. Escribe la pregunta y la respuesta, y guarda.
                    4. Toca "Probarla" en esa pregunta para ver cómo contesta de verdad tu asistente.

                    En "Tu asistente no supo esto" ves lo que preguntaron tus clientes y no supo responder: ahí puedes aprobar la respuesta que te sugerimos, editarla o descartarla.
                    TXT,
            ],
            [
                'slug' => 'que-ve-mi-cliente',
                'category' => 'asistente',
                'screen' => null,
                'sort_order' => 3,
                'title' => 'Qué ve mi cliente cuando le escribe a mi negocio',
                'keywords' => 'cliente ve prueba simulador como responde presentacion probar',
                'body' => <<<'TXT'
                    Ve una conversación normal de WhatsApp con el número que conectaste. Tu asistente le responde con lo que cargaste de tu negocio, tus servicios, tus productos y lo que le enseñaste.

                    1. Entra a Mi negocio y toca "Pruébalo ahora" para ver un ejemplo de cómo se ve una conversación.
                    2. Para ver cómo contesta tu asistente de verdad, entra a Mi asistente → Conocimiento y toca "Probarla" en una pregunta que le hayas enseñado.
                    3. Si algo no te gusta, corrige la información de tu negocio o enséñale la respuesta correcta.

                    "Pruébalo ahora" muestra una conversación de ejemplo: no le llega a nadie.
                    TXT,
            ],
            [
                'slug' => 'derivar-a-persona',
                'category' => 'asistente',
                'screen' => 'assistant.settings',
                'sort_order' => 4,
                'title' => 'Cómo hago que mi asistente me avise cuando necesita a una persona',
                'keywords' => 'derivar persona humano avisar escalar llamar equipo cuando pasa a una persona',
                'body' => <<<'TXT'
                    Tú decides cuándo tu asistente pasa una charla a una persona. Cuando lo hace, la charla queda en "Esperando a tu equipo" y tu asistente se pausa solo en ese chat.

                    1. Entra a Mi asistente → Configuración.
                    2. En "¿Cuándo llamar a una persona?" elige Enseguida, Equilibrado o Casi nunca.
                    3. En "Tus casos de derivación" escribe un caso por línea (los que siempre deben pasar a una persona) y toca "Guardar casos".
                    4. Para que el aviso te llegue también por WhatsApp, carga tu número en "WhatsApp para derivar", en Mi negocio → Contacto.

                    Siempre te avisamos en la campana. Si tu WhatsApp está conectado y cargaste el número, el aviso llega además por WhatsApp y puedes responderle al cliente desde ahí. Si usas departamentos, el aviso va primero a las personas disponibles de ese departamento, y si no hay nadie, a ese número.

                    Para cerrar el tema desde tu teléfono, escribe #resuelto: la charla queda como resuelta. Si prefieres devolvérsela ya a tu asistente, usa "Que siga el asistente" en Conversaciones.
                    TXT,
            ],
            [
                'slug' => 'responder-yo-una-conversacion',
                'category' => 'asistente',
                'screen' => 'conversations',
                'sort_order' => 5,
                'title' => 'Quiero responder yo una conversación',
                'keywords' => 'tomar charla responder yo escribir al cliente manual humano solo lectura nota',
                'body' => <<<'TXT'
                    Mientras tu asistente atiende, la charla es de solo lectura. Para escribirle tú al cliente, tomas la charla.

                    1. Entra a Conversaciones y abre la charla.
                    2. Toca "Tomo yo esta charla". Solo aparece mientras la charla está abierta: en una charla resuelta no está.
                    3. Escribe en "Escribe tu respuesta…" y toca "Enviar".
                    4. Cuando termines, toca "Que siga el asistente" para devolvérsela, o "Marcar resuelta".

                    Con "Nota" dejas un comentario interno que el cliente no ve. Para enviar, tu WhatsApp tiene que estar conectado y tu cuenta sin suspender.
                    TXT,
            ],
            [
                'slug' => 'apagar-asistente',
                'category' => 'asistente',
                'screen' => 'conversations',
                'sort_order' => 6,
                'title' => 'Quiero que mi asistente deje de responder',
                'keywords' => 'apagar pausar detener desactivar parar asistente interruptor vacaciones',
                'body' => <<<'TXT'
                    No hay un interruptor general que apague a tu asistente. Puedes pausarlo en una charla, o desvincular el dispositivo si necesitas que deje de atender del todo.

                    1. Para una sola charla: entra a Conversaciones, ábrela y toca "Tomo yo esta charla". Tu asistente no responde ahí hasta que toques "Que siga el asistente".
                    2. Para todas: en tu teléfono abre WhatsApp → Dispositivos vinculados, toca el dispositivo de :brand y cierra la sesión. Tu asistente deja de recibir mensajes.
                    3. Para volver a activarlo, entra a WhatsApp en el panel y toca "Conectar mi WhatsApp".

                    Tu asistente también se pausa solo si tu pago queda vencido más allá de los días de gracia, o si tu cuenta es suspendida.
                    TXT,
            ],
            [
                'slug' => 'asistente-listo-porcentaje',
                'category' => 'asistente',
                'screen' => 'dashboard',
                'sort_order' => 7,
                'title' => 'Qué me falta para que mi asistente esté listo',
                'keywords' => 'porcentaje listo checklist pasos inicio empezar configurar primeros pasos',
                'body' => <<<'TXT'
                    Hasta que tu asistente atiende su primera conversación real, Inicio te muestra "Tu asistente está listo al N%" con la lista de lo que falta.

                    1. Entra a Inicio.
                    2. Mira la lista: Creaste tu cuenta, Completa los datos de tu negocio, Carga tus servicios o productos, Prueba tu asistente y Conecta tu WhatsApp.
                    3. Completa lo que falta en su pantalla: Mi negocio, Catálogo o WhatsApp.

                    El paso "Prueba tu asistente" se marca cuando tu asistente le responde a alguien de verdad. Con esa primera conversación, Inicio deja de mostrar la lista y pasa a mostrarte las cifras del mes y tus últimas conversaciones.
                    TXT,
            ],
            [
                'slug' => 'ver-estadisticas',
                'category' => 'asistente',
                'screen' => 'statistics',
                'sort_order' => 8,
                'title' => 'Dónde veo cuánto hace mi asistente',
                'keywords' => 'estadisticas metricas numeros resultados conversaciones mes clientes nuevos horas pico',
                'body' => <<<'TXT'
                    En Estadísticas ves el mes de tu negocio y cómo cambió respecto al mes anterior. Solo la ve el titular de la cuenta.

                    1. Entra a Estadísticas.
                    2. Mira las tarjetas: Conversaciones del mes, Resueltas por tu asistente, Clientes nuevos, Consultas del mes, Minutos de audio y Clientes recuperados.
                    3. Más abajo, según tu plan, ves lo que te preguntan por tema, las conversaciones por día, tus horas pico y la tendencia de 6 meses.

                    Lo que tu plan no incluye aparece con un candado y el enlace "Ver los planes".
                    TXT,
            ],

            [
                'slug' => 'importar-productos-excel',
                'category' => 'catalogo',
                'screen' => 'my-products',
                'sort_order' => 1,
                'title' => 'Cómo subo mi lista de productos desde Excel',
                'keywords' => 'importar planilla csv xlsx cargar masivo lista precios excel subir archivo',
                'body' => <<<'TXT'
                    Sube la planilla tal como la tienes: leemos las columnas solas y tú confirmas antes de guardar nada.

                    1. Entra a Catálogo → Productos. Si todavía no tienes productos, la zona de carga está arriba; si ya tienes, usa la tarjeta "Importar desde Excel".
                    2. Arrastra tu planilla (.xlsx o .csv) a la zona de carga. Tiene que tener una fila de títulos, y leemos solo la primera hoja.
                    3. Revisa "Esto entendimos de tu planilla": cada columna con su destino (Nombre del producto, Código, Precio, Cantidad, Descripción o "Dato extra"). Si te proponemos corregir errores de tipeo en los nombres, ajústalos.
                    4. Toca "Confirmar e importar". Se procesa en segundo plano y tu asistente va aprendiendo los productos.

                    Si un producto ya existe con el mismo nombre, se actualiza: nunca se borra nada. Una fila sin nombre se omite.

                    El precio escríbelo solo con números y sin símbolos de moneda: solo se ignora el "$"; con "Bs", "USD" o "€" el producto queda sin precio. Para decimales usa coma (12,50). Si usas punto para los miles, pon también la coma decimal (1.500,00): un "1.500" a secas se lee como 1,5.

                    Si tu archivo es .xls, guárdalo como .xlsx o .csv. El tamaño máximo está indicado en la zona de carga.
                    TXT,
            ],
            [
                'slug' => 'servicio-no-se-ofrece',
                'category' => 'catalogo',
                'screen' => 'my-services',
                'sort_order' => 2,
                'title' => 'Mi asistente no ofrece un servicio que cargué',
                'keywords' => 'no aparece no lo dice invisible pausado inactivo servicio no menciona',
                'body' => <<<'TXT'
                    Lo más común es que el servicio esté pausado. Un servicio pausado sigue en tu lista, pero tu asistente no lo ofrece.

                    1. Entra a Catálogo → Servicios y busca el servicio por nombre.
                    2. Mira la fila: si dice "Pausado", está pausado.
                    3. Toca el icono "Reactivar" de la fila, o abre el servicio y enciende "Tu asistente lo ofrece".

                    Si dice "Activo en WhatsApp" y aun así no lo menciona, revisa que tenga descripción: con solo el nombre, tu asistente tiene poco que contar.
                    TXT,
            ],
            [
                'slug' => 'producto-sin-stock',
                'category' => 'catalogo',
                'screen' => 'my-products',
                'sort_order' => 3,
                'title' => 'Cómo marco un producto sin stock o dejo de ofrecerlo',
                'keywords' => 'sin stock agotado pausar disponible eliminar quitar producto dejar de ofrecer',
                'body' => <<<'TXT'
                    Los productos no se pausan: se marcan sin stock o se eliminan. Un producto sin stock sigue visible y tu asistente avisa que está sin stock por ahora.

                    1. Entra a Catálogo → Productos y busca el producto por nombre o código.
                    2. Para marcarlo sin stock, usa el interruptor "Cambiar disponibilidad" de su fila.
                    3. Para dejar de ofrecerlo del todo, elimínalo con "Eliminar producto".

                    Si un cliente ya consultó por ese producto no se puede eliminar: déjalo sin stock.
                    TXT,
            ],
            [
                'slug' => 'escribir-descripciones-ia',
                'category' => 'catalogo',
                'screen' => 'my-services',
                'sort_order' => 4,
                'title' => 'No sé qué escribir en las descripciones',
                'keywords' => 'descripcion texto redactar ia optimizar vacio escribir por mi inteligencia artificial',
                'body' => <<<'TXT'
                    Nuestra inteligencia artificial escribe por ti las descripciones que estén en blanco.

                    1. Entra a Catálogo → Servicios (o Productos). El recuadro aparece cuando ya tienes al menos uno cargado.
                    2. Toca "Optimizar con IA" en el recuadro de arriba.
                    3. Espera unos segundos: te decimos cuántas descripciones escribimos.

                    Solo completa las que están vacías: lo que ya escribiste tú nunca se pisa. Las descripciones se guardan directo, así que revisa cómo quedaron y edita las que quieras. Si tienes muchas en blanco, puede que haga falta tocar el botón otra vez.

                    En Mi negocio → Identidad, "Optimizar con IA" es distinto: te propone la descripción de tu negocio, pero solo queda guardada cuando tocas "Guardar".
                    TXT,
            ],
            [
                'slug' => 'cargar-servicio',
                'category' => 'catalogo',
                'screen' => 'my-services',
                'sort_order' => 5,
                'title' => 'Cómo cargo un servicio',
                'keywords' => 'agregar servicio nuevo precio duracion categoria destacado adelanto',
                'body' => <<<'TXT'
                    Cada servicio tiene su ficha: lo que cargues ahí es lo que tu asistente le cuenta a tus clientes.

                    1. Entra a Catálogo → Servicios y toca "Agregar servicio".
                    2. Completa el nombre, la categoría, la duración y el precio (Fijo, Desde, Gratis o A convenir). Si quieres, agrega adelanto, descripción y preparación.
                    3. Deja encendido "Tu asistente lo ofrece".
                    4. Toca "Guardar servicio".

                    Para que un servicio salga primero, márcalo como Destacado. Para ordenarlos, crea categorías con "Agregar categoría" y arrástralas.
                    TXT,
            ],
            [
                'slug' => 'fotos-catalogo',
                'category' => 'catalogo',
                'screen' => 'my-products',
                'sort_order' => 6,
                'title' => 'Cómo agrego fotos a un producto o servicio',
                'keywords' => 'fotos imagenes subir portada galeria catalogo imagen producto',
                'body' => <<<'TXT'
                    Las fotos se agregan en la ficha del producto o del servicio, y tu asistente las manda por WhatsApp cuando el cliente las pide.

                    1. Guarda primero el producto o servicio: hasta entonces no se le pueden agregar fotos.
                    2. Ábrelo y busca la sección de fotos.
                    3. Sube la imagen en formato JPG, PNG o WebP.
                    4. La primera foto es la "Portada".

                    Cada foto se revisa antes de guardarse. Cuántas fotos puedes cargar depende de tu plan: lo ves en Plan y pagos → Mi plan, en "Fotos de catálogo".
                    TXT,
            ],

            [
                'slug' => 'completar-mi-negocio',
                'category' => 'negocio',
                'screen' => 'my-business',
                'sort_order' => 1,
                'title' => 'Qué tengo que completar en Mi negocio',
                'keywords' => 'mi negocio perfil porcentaje obligatorio identidad datos completar guardar',
                'body' => <<<'TXT'
                    Solo el nombre es obligatorio. Todo lo demás es opcional, pero cada dato que cargas ayuda a tu asistente a presentarse y responder mejor.

                    1. Entra a Mi negocio → Identidad y escribe el nombre del negocio: es lo único que hace falta.
                    2. Escribe una descripción: tu asistente la usa para presentarse (no cuenta para el medidor, pero ayuda).
                    3. Para llegar al 100% del medidor "Tu perfil" carga cuatro cosas: el contacto (tu WhatsApp, el "WhatsApp para derivar" y el correo del negocio: los tres), la moneda, al menos un horario y al menos una red social. Puedes guardar sin ellos; solo cuentan para el medidor.
                    4. Toca "Guardar" en cada tarjeta: cada una se guarda por separado.

                    Si sales de la pantalla con cambios sin guardar, te preguntamos antes.
                    TXT,
            ],
            [
                'slug' => 'horarios-de-atencion',
                'category' => 'negocio',
                'screen' => 'my-business.horarios',
                'sort_order' => 2,
                'title' => 'Cómo cargo mis horarios y los días que no abro',
                'keywords' => 'horarios atencion dias cerrado feriados vacaciones abrir cerrar turnos partidos',
                'body' => <<<'TXT'
                    Tu asistente usa tus horarios para decirle a los clientes cuándo atiendes, y la agenda de turnos los usa para ofrecer horas libres.

                    1. Entra a Mi negocio → Horarios de atención.
                    2. Para cada día pon el horario. Con "Agregar turno" cargas más de uno (por ejemplo mañana y tarde), o toca "Cerrado".
                    3. Si repites el mismo horario de lunes a viernes, usa "Aplicar de lunes a viernes".
                    4. En "Días que no abres" agrega el día o el rango, un motivo si quieres (tu asistente se lo dice al cliente) y toca "Agregar el cierre".
                    5. Guarda.

                    Si dos turnos del mismo día se pisan, te avisamos. Los feriados que cambian de fecha los cargas tú. Si en esos días ya hay turnos confirmados, "Avisar y cancelar" los cancela todos y avisa por WhatsApp a quienes tengan un número válido.
                    TXT,
            ],
            [
                'slug' => 'ubicacion-direccion',
                'category' => 'negocio',
                'screen' => 'my-business.ubicacion',
                'sort_order' => 3,
                'title' => 'Cómo le digo a mi asistente dónde queda mi local',
                'keywords' => 'direccion ubicacion local donde queda domicilio ciudad pais sin local',
                'body' => <<<'TXT'
                    Si atiendes en un local, tu asistente pasa la dirección cuando un cliente la pide.

                    1. Entra a Mi negocio → Ubicación.
                    2. En "¿Atiendes clientes en un local?" elige "Sí, tengo local" o "No, atiendo sin local".
                    3. Completa Ciudad y Dirección.
                    4. Toca "Guardar".

                    Los campos de dirección aparecen cuando eliges "Sí, tengo local". País y Provincia / estado vienen de tu registro y no se editan aquí. Ciudad y Dirección son opcionales.
                    TXT,
            ],
            [
                'slug' => 'activar-turnos',
                'category' => 'negocio',
                'screen' => 'my-business.turnos',
                'sort_order' => 4,
                'title' => 'Cómo activo la agenda de turnos',
                'keywords' => 'turnos agenda reservas citas activar link de reservas turnos a la vez duracion',
                'body' => <<<'TXT'
                    La agenda de turnos viene apagada. Cuando la activas, tu asistente puede reservar turnos y tus clientes también pueden hacerlo desde un enlace.

                    1. Entra a Mi negocio → Turnos.
                    2. Enciende "Agenda de turnos".
                    3. Ajusta "Turnos a la vez" y "Duración" (la duración se usa cuando un servicio no trae la suya). Si quieres, pon un "Tope por día".
                    4. Guarda.

                    Las horas libres salen de tus Horarios de atención menos lo ya reservado: si no cargaste tus horarios, no hay horas para ofrecer.

                    Al encenderla aparece "Tu link de reservas" con el botón "Copiar", y "Ver mi agenda". Guarda antes de compartir el link: hasta que no guardas, el link no funciona.
                    TXT,
            ],
            [
                'slug' => 'como-reserva-mi-cliente',
                'category' => 'negocio',
                'screen' => 'agenda',
                'sort_order' => 5,
                'title' => 'Cómo reserva un turno mi cliente',
                'keywords' => 'reservar turno cliente whatsapp link reservas cancelar mover cambiar turno',
                'body' => <<<'TXT'
                    Puede reservar de dos maneras, y en las dos te avisamos en la campana.

                    1. Por WhatsApp: tu asistente mira la agenda en ese momento, ofrece horas libres, reserva cuando el cliente elige una y se lo confirma. El cliente también puede ver, cancelar o mover sus turnos por ahí.
                    2. Por tu link de reservas: el cliente elige el día y la hora, escribe su nombre y su WhatsApp, y toca "Reservar mi turno".

                    Desde el link el cliente no puede cancelar ni mover el turno: para cambiarlo tiene que escribirle a tu asistente por WhatsApp.

                    Si los turnos están apagados, el link no funciona. Se activan en Mi negocio → Turnos.
                    TXT,
            ],
            [
                'slug' => 'gestionar-turnos',
                'category' => 'negocio',
                'screen' => 'agenda',
                'sort_order' => 6,
                'title' => 'Cómo veo, muevo o cancelo un turno',
                'keywords' => 'agenda ver turnos cancelar mover cambiar horario atendido reservar turno dia semana',
                'body' => <<<'TXT'
                    En Agenda ves tus turnos por día o por semana y puedes actuar sobre cada uno.

                    1. Entra a Agenda y elige la vista "Día" o "Semana".
                    2. Para reservar tú un turno, toca "Reservar un turno": se reserva para alguien que ya está en tus clientes.
                    3. En un turno usa "Cambiar de horario", "Cancelar el turno" o "Marcar como atendido".

                    Al cancelar un turno, la hora queda libre otra vez. Los turnos pueden estar Confirmado, Cancelado, Atendido o No vino.
                    TXT,
            ],
            [
                'slug' => 'recordatorio-de-turno',
                'category' => 'negocio',
                'screen' => 'agenda',
                'sort_order' => 7,
                'title' => 'Mi cliente recibió un recordatorio de su turno',
                'keywords' => 'recordatorio turno aviso whatsapp confirmo reprogramar mensaje cliente',
                'body' => <<<'TXT'
                    Tu asistente le manda un WhatsApp de recordatorio a quien tiene un turno confirmado, aproximadamente un día antes. Le pide que responda "Confirmo" o "Reprogramar".

                    1. Mantén tu WhatsApp conectado y la agenda de turnos activa: sin eso el recordatorio no sale.
                    2. Si el cliente responde "Reprogramar", tu asistente le ofrece horas nuevas y mueve el turno.
                    3. Mira el cambio en Agenda.

                    Solo le llega a quien ya te escribió por WhatsApp o aceptó recibir mensajes: quien reservó únicamente por el link y nunca te escribió no lo recibe. Si el turno se mueve, sale un recordatorio nuevo con la hora nueva.

                    "Confirmo" es una cortesía: no cambia el estado del turno en la Agenda.
                    TXT,
            ],

            [
                'slug' => 'ver-mi-plan',
                'category' => 'plan',
                'screen' => 'my-plan',
                'sort_order' => 1,
                'title' => 'Dónde veo qué incluye mi plan y cuánto usé',
                'keywords' => 'plan incluye uso conversaciones cupo limite mi plan comparar planes medidor',
                'body' => <<<'TXT'
                    En Mi plan ves lo que incluye tu plan y cuánto llevas usado este mes.

                    1. Entra a Plan y pagos → Mi plan.
                    2. En "Tu uso este mes" mira los medidores, por ejemplo "Conversaciones con la IA", "Fotos de catálogo" y "Personas en el panel".
                    3. En "Todos los planes" compara con los demás: lo que tu plan no incluye sale con un candado y "Disponible en" el plan que lo tiene.

                    Tu plan actual lleva la insignia "Tu plan".
                    TXT,
            ],
            [
                'slug' => 'prueba-gratis',
                'category' => 'plan',
                'screen' => 'my-plan',
                'sort_order' => 2,
                'title' => 'Cómo funciona mi prueba gratis',
                'keywords' => 'prueba gratis dias trial termina prueba cuanto dura',
                'body' => <<<'TXT'
                    Mientras dura tu prueba usas tu plan sin pagar. En Mi plan ves cuántos días te quedan.

                    1. Entra a Plan y pagos → Mi plan.
                    2. Busca la insignia "Prueba gratis: te quedan N días".
                    3. En Plan y pagos → Mis pagos, en "Tu período actual", ves cuándo termina la prueba, y en "Próximo pago" cuánto sigue.

                    Cuando la prueba termina sin que hayas pagado, Mi plan lo dice ("Tu prueba terminó: sigues en el plan…") y te muestra en qué plan sigues. En Mis pagos ves tu período y el próximo pago. Cuando se acredita tu primer pago, ese aviso desaparece.
                    TXT,
            ],
            [
                'slug' => 'como-pagar',
                'category' => 'plan',
                'screen' => 'my-payments',
                'sort_order' => 3,
                'title' => 'Cómo pago mi plan y subo el comprobante',
                'keywords' => 'pagar pago transferencia comprobante subir recibo acreditar verificar mis pagos',
                'body' => <<<'TXT'
                    Pagas por transferencia y subes el comprobante en Mis pagos. Nuestro equipo lo verifica a mano y, cuando queda acreditado, te avisamos por correo a tu correo de facturación.

                    1. Entra a Plan y pagos → Mis pagos.
                    2. En "Próximo pago" mira el monto; debajo, en "Cómo pagar", están los datos de la transferencia. Si ahí dice que pronto verás cómo pagar, escríbenos desde Ayuda → "Reportar un problema" y te los pasamos.
                    3. Haz la transferencia.
                    4. Toca "Subir comprobante", adjunta la foto o la captura (PNG, JPG o WebP; PDF no) y, si quieres, el número de operación.
                    5. Toca "Enviar comprobante".

                    Mientras lo verificamos, el pago aparece "Por verificar" y ves el aviso "Estamos verificando tu comprobante". Cuando se acredita pasa a "Pagado". Si ya tienes un comprobante por verificar, no subas otro: te avisamos que ya hay uno en revisión.

                    Tu correo de facturación lo ves en la tarjeta "A nombre de quién sale" y lo cambias en Mi negocio → Moneda.
                    TXT,
            ],
            [
                'slug' => 'pago-rechazado',
                'category' => 'plan',
                'screen' => 'my-payments',
                'sort_order' => 4,
                'title' => 'Mi pago aparece como rechazado',
                'keywords' => 'pago rechazado comprobante rechazado motivo no aceptaron error transferencia',
                'body' => <<<'TXT'
                    Un pago "Rechazado" quiere decir que no pudimos darlo por bueno con el comprobante que subiste. El motivo queda escrito en el historial.

                    1. Entra a Plan y pagos → Mis pagos.
                    2. En "Historial de pagos" busca el pago "Rechazado": el motivo aparece debajo. También te llega un correo a tu correo de facturación.
                    3. Corrige lo que indica el motivo y toca "Subir comprobante" para enviar uno nuevo.

                    Mientras tanto tu período sigue su curso, así que conviene resolverlo pronto. Si no entiendes el motivo, repórtalo desde Ayuda → "Reportar un problema".
                    TXT,
            ],
            [
                'slug' => 'no-pague-a-tiempo',
                'category' => 'plan',
                'screen' => 'my-payments',
                'sort_order' => 5,
                'title' => 'Qué pasa si no pago a tiempo',
                'keywords' => 'vencido vence pago atrasado gracia pausa suspendido asistente en pausa recordatorio',
                'body' => <<<'TXT'
                    Te avisamos antes de que venza y tienes unos días de gracia con tu asistente funcionando. Si pasan, tu asistente se pausa, pero no se borra nada.

                    1. Antes del vencimiento te avisamos por correo a tu correo de facturación y, si cargaste el "WhatsApp para derivar" en Mi negocio → Contacto, también por WhatsApp a ese número. En el panel ves el aviso "Tu próximo pago es en N días".
                    2. Si vence, ves "Tu pago está vencido" y tu asistente sigue atendiendo durante los días de gracia.
                    3. Terminados esos días, tu asistente queda "en pausa por falta de pago" y deja de responder. Se conservan tus conversaciones, tus clientes y tu catálogo.
                    4. Entra a Plan y pagos → Mis pagos y sube el comprobante: apenas se acredita el pago, tu asistente vuelve a atender.

                    Mientras tengas un comprobante "Por verificar", no te enviamos avisos de cobro ni se pausa tu asistente.
                    TXT,
            ],
            [
                'slug' => 'tope-de-conversaciones',
                'category' => 'plan',
                'screen' => 'my-plan',
                'sort_order' => 6,
                'title' => 'Qué pasa si llego al máximo de conversaciones de mi plan',
                'keywords' => 'cupo tope limite conversaciones mensual maximo plan excedido aviso',
                'body' => <<<'TXT'
                    Tu asistente no se corta: sigue atendiendo. Cuando te acercas al cupo del mes te mandamos un aviso por WhatsApp, una sola vez por mes.

                    1. Entra a Plan y pagos → Mi plan.
                    2. En "Tu uso este mes" mira "Conversaciones con la IA": cuántas llevas y cuántas incluye tu plan.
                    3. Si necesitas más capacidad, compara los planes en "Todos los planes".

                    El aviso solo sale si cargaste el "WhatsApp para derivar" en Mi negocio → Contacto, y llega a ese número. El cupo se cuenta por mes calendario de tu negocio.
                    TXT,
            ],
            [
                'slug' => 'datos-de-facturacion',
                'category' => 'plan',
                'screen' => 'my-business.facturacion',
                'sort_order' => 7,
                'title' => 'Cómo cargo mis datos de facturación',
                'keywords' => 'factura facturacion rif cuit numero fiscal condicion fiscal moneda a nombre de quien',
                'body' => <<<'TXT'
                    Son los datos que se usan para tu factura. Todo es opcional: la pantalla te lo aclara, "tu factura sale a tu nombre, como persona natural" si no tienes datos fiscales.

                    1. Entra a Mi negocio → Moneda.
                    2. Elige la "Moneda de tus precios" y, si quieres, una "Moneda de referencia" que se muestra junto al precio.
                    3. Completa "Condición fiscal" y "Número fiscal".
                    4. Revisa el "Correo de facturación": a ese correo llegan los avisos de cobro y los de tus pagos. Al crear tu cuenta se llena con tu correo de acceso, y aquí lo puedes cambiar.
                    5. Toca "Guardar".

                    En Plan y pagos → Mis pagos, la tarjeta "A nombre de quién sale" te muestra cómo quedó, con el enlace "Editar en Mi negocio".
                    TXT,
            ],
            // Off on purpose: the plan change is only drawn on the screen and never saved,
            // so an answer would describe a flow that does not exist yet.
            [
                'slug' => 'cambiar-plan',
                'category' => 'plan',
                'screen' => 'my-plan',
                'sort_order' => 8,
                'is_active' => false,
                'title' => 'Cómo cambio de plan',
                'keywords' => 'plan upgrade bajar subir cambiar de plan',
                'body' => <<<'TXT'
                    Desde Mi plan ves el que tienes y los demás.

                    1. Entra a Plan y pagos → Mi plan.
                    2. Compara los planes en "Todos los planes".
                    TXT,
            ],

            [
                'slug' => 'invitar-equipo',
                'category' => 'equipo',
                'screen' => 'team',
                'sort_order' => 1,
                'title' => 'Cómo invito a alguien a atender conmigo',
                'keywords' => 'equipo agente invitar sumar persona empleado invitacion agregar usuario',
                'body' => <<<'TXT'
                    Le mandas una invitación por correo y esa persona entra con su propia clave. Solo el titular de la cuenta puede invitar.

                    1. Entra a Mi negocio → Equipo y toca "Invitar a alguien".
                    2. Escribe su nombre y su correo. El WhatsApp es opcional: sirve para los avisos, no para invitar.
                    3. Si tu plan incluye departamentos, elige en cuáles atiende.
                    4. Toca "Enviar invitación": le llega un enlace por correo.

                    El enlace vence. Si venció, usa "Reenviar invitación" en esa persona. Mientras no acepte, puedes usar "Cancelar invitación".

                    Tu plan define cuántas personas pueden entrar al panel, tú incluido: lo ves en Plan y pagos → Mi plan, en "Personas en el panel". Si ya no hay lugar, al enviar la invitación te lo avisamos.

                    Un agente ve Inicio (que lo lleva a Conversaciones), Conversaciones, Clientes, Ajustes y Ayuda. No ve tu negocio, tu plan, tus pagos ni tus estadísticas.
                    TXT,
            ],
            [
                'slug' => 'quitar-del-equipo',
                'category' => 'equipo',
                'screen' => 'team',
                'sort_order' => 2,
                'title' => 'Cómo cancelo una invitación o quito a alguien del equipo',
                'keywords' => 'quitar equipo eliminar persona cancelar invitacion sacar agente baja',
                'body' => <<<'TXT'
                    Depende de si la persona ya aceptó la invitación.

                    1. Entra a Mi negocio → Equipo y busca a la persona.
                    2. Si todavía no aceptó, toca "Cancelar invitación" y confirma: el enlace deja de funcionar.
                    3. Si ya entró, toca "Quitar del equipo". Lo que respondió queda en el historial de las conversaciones.
                    TXT,
            ],

            [
                'slug' => 'cambiar-correo',
                'category' => 'cuenta',
                'screen' => 'settings.correo',
                'sort_order' => 1,
                'title' => 'Cómo cambio mi correo de acceso',
                'keywords' => 'cambiar correo email acceso nuevo correo confirmar enlace',
                'body' => <<<'TXT'
                    Cambias tu correo desde Ajustes y lo confirmas con un enlace. Hasta que lo confirmes sigues entrando con el correo actual.

                    1. Entra a Ajustes → Correo de acceso.
                    2. Escribe el "Nuevo correo" y tu contraseña actual, y toca "Cambiar correo".
                    3. Abre el enlace que te llega al correo nuevo.

                    También avisamos al correo actual del cambio. Si no te llegó el enlace, toca "Reenviar enlace"; si te arrepentiste, "Cancelar el cambio". El enlace vence en poco tiempo, por seguridad.
                    TXT,
            ],
            [
                'slug' => 'cambiar-contrasena',
                'category' => 'cuenta',
                'screen' => 'settings.contrasena',
                'sort_order' => 2,
                'title' => 'Cómo cambio mi contraseña',
                'keywords' => 'cambiar contrasena clave password nueva seguridad',
                'body' => <<<'TXT'
                    La cambias desde Ajustes. Cada cambio te lo avisamos por correo.

                    1. Entra a Ajustes → Contraseña.
                    2. Escribe tu contraseña actual, la nueva y repite la nueva. Tiene que tener mínimo 8 caracteres, con una mayúscula, un número y un símbolo.
                    3. Si quieres, marca "Cerrar la sesión en los otros dispositivos".
                    4. Toca "Cambiar contraseña".

                    No puede ser igual a la actual ni una contraseña que haya aparecido en filtraciones conocidas. Nunca te la vamos a pedir por WhatsApp.
                    TXT,
            ],
            [
                'slug' => 'olvide-mi-contrasena',
                'category' => 'cuenta',
                'screen' => null,
                'sort_order' => 3,
                'title' => 'Olvidé mi contraseña',
                'keywords' => 'olvide contrasena recuperar restablecer no puedo entrar clave perdida',
                'body' => <<<'TXT'
                    Te mandamos un enlace al correo para que elijas una contraseña nueva.

                    1. En la pantalla de inicio de sesión toca "¿Olvidaste tu contraseña?".
                    2. Escribe tu correo y toca "Enviar el enlace".
                    3. Abre el enlace que te llega y elige tu contraseña nueva.

                    El enlace vence en poco tiempo. Si venció, pide otro. Revisa también la carpeta de spam.
                    TXT,
            ],
            [
                'slug' => 'activar-dos-pasos',
                'category' => 'cuenta',
                'screen' => 'settings.dos-pasos',
                'sort_order' => 4,
                'title' => 'Cómo activo la verificación en dos pasos',
                'keywords' => 'dos pasos doble factor verificacion codigo whatsapp seguridad 2fa respaldo',
                'body' => <<<'TXT'
                    Con la verificación en dos pasos, cuando entras desde un dispositivo nuevo el código te llega a tu WhatsApp personal.

                    1. Entra a Ajustes → Verificación en dos pasos.
                    2. Si te pide tu WhatsApp personal, toca "Cargar mi WhatsApp": te lleva a Mi negocio → Contacto; guárdalo allí y vuelve.
                    3. Escribe tu contraseña actual, toca "Enviarme un código", escribe el código de 6 dígitos y toca "Activar".
                    4. Guarda tus códigos de respaldo: toca "Copiar los códigos" y luego "Ya los guardé".

                    Cada código de respaldo sirve una sola vez. Puedes pedir otros con "Generar códigos nuevos" o apagarla con "Desactivar"; las dos acciones piden tu contraseña actual.
                    TXT,
            ],
            [
                'slug' => 'cerrar-sesiones',
                'category' => 'cuenta',
                'screen' => 'settings.dispositivos',
                'sort_order' => 5,
                'title' => 'Cómo veo y cierro las sesiones abiertas de mi cuenta',
                'keywords' => 'dispositivos sesiones cerrar sesion otros dispositivos ubicacion inusual alguien entro codigo nuevo navegador',
                'body' => <<<'TXT'
                    En Dispositivos ves desde dónde entró tu cuenta y puedes cerrar cualquier sesión.

                    1. Entra a Ajustes → Dispositivos.
                    2. Para cerrar una sesión, toca "Cerrar sesión" en ese dispositivo y confirma.
                    3. Para cerrarlas todas menos esta, toca "Cerrar las otras sesiones" (aparece cuando hay más de un dispositivo), escribe tu contraseña y toca "Cerrar sesiones".

                    Si ves un dispositivo con la marca "Ubicación inusual" que no es tuyo, cierra esa sesión y cambia tu contraseña. Cuando entras desde un navegador nuevo te pedimos un código y te avisamos por correo.

                    En Ajustes → Actividad reciente ves tus últimos inicios de sesión.
                    TXT,
            ],
            [
                'slug' => 'avisos-de-la-campana',
                'category' => 'cuenta',
                'screen' => 'settings.avisos',
                'sort_order' => 6,
                'title' => 'Cómo elijo qué avisos recibo en la campana',
                'keywords' => 'avisos campana notificaciones apagar silenciar alertas elegir que avisos ver',
                'body' => <<<'TXT'
                    Cada persona elige sus avisos: lo que cambies solo afecta a tu cuenta.

                    1. Entra a Ajustes → Avisos de la campana.
                    2. Enciende o apaga cada tipo de aviso con su interruptor.

                    Puedes elegir entre estos nueve: "Un cliente espera respuesta", "Tu asistente derivó una charla al equipo", "Se reservó un turno nuevo", "Tu WhatsApp se desconectó", "Alguien de tu equipo le enseñó una respuesta", "Resolvimos un reporte que enviaste", "Te invitamos a que mencionemos tu negocio", "Falta que sigas nuestra cuenta para mencionarte" y "Mencionamos tu negocio en nuestras redes".
                    TXT,
            ],
            [
                'slug' => 'cerrar-mi-cuenta',
                'category' => 'cuenta',
                'screen' => 'settings.cuenta',
                'sort_order' => 7,
                'title' => 'Cómo elimino mi cuenta y cómo la recupero',
                'keywords' => 'eliminar cuenta cerrar cuenta borrar baja darme de baja restaurar recuperar cuenta',
                'body' => <<<'TXT'
                    Eliminar tu cuenta cierra el acceso y tu negocio, y tu asistente deja de atender. No se borra nada, y puedes recuperarla dentro de un plazo. Solo el titular puede hacerlo.

                    1. Entra a Ajustes → Eliminar mi cuenta.
                    2. Escribe ELIMINAR y tu contraseña actual, y toca "Eliminar mi cuenta".
                    3. Confirma en el diálogo "¿Eliminar tu cuenta?".
                    4. Para recuperarla, abre el enlace del correo que te enviamos al eliminarla y luego inicia sesión.

                    Pasado el plazo el enlace ya no sirve ("Ya no se puede restaurar desde aquí"): escríbenos y lo revisamos contigo. Un agente no ve esta opción.
                    TXT,
            ],

            [
                'slug' => 'reportar-un-problema',
                'category' => 'soporte',
                'screen' => 'help',
                'sort_order' => 1,
                'title' => 'Cómo reporto un problema o hago una pregunta',
                'keywords' => 'reportar problema soporte ayuda pregunta idea error falla contactar escribir al soporte',
                'body' => <<<'TXT'
                    Reportas desde dentro del panel y te respondemos por WhatsApp o por correo.

                    1. Entra a Ayuda y toca "Reportar un problema", o toca el icono del salvavidas en la barra de arriba: abre "Soporte técnico".
                    2. Cuéntanos "¿Qué pasó?" y elige el tipo: Algo no funciona, Una idea o Una pregunta.
                    3. Elige la pantalla y, si ayuda, agrega una imagen.
                    4. Toca "Enviar": te damos el código de tu reporte.

                    Mientras escribes te sugerimos respuestas de la ayuda: si resuelven lo tuyo, no hace falta enviarlo.

                    Te respondemos por WhatsApp, al número que cargaste en "WhatsApp para derivar" (Mi negocio → Contacto), y si no hay o no llega, por correo a quien envió el reporte. Cuando lo resolvemos, suena la campana.
                    TXT,
            ],
            [
                'slug' => 'asistente-suspendido',
                'category' => 'soporte',
                'screen' => null,
                'sort_order' => 2,
                'title' => 'Mi asistente está suspendido',
                'keywords' => 'suspendido suspension cuenta suspendida apelar contenido politicas bloqueado revision',
                'body' => <<<'TXT'
                    Detectamos contenido que incumple nuestras políticas de uso. Mientras dura la suspensión tu asistente no responde y no se envían mensajes a tus clientes. No se borró nada.

                    1. Lee el aviso de arriba: "Tu asistente está suspendido".
                    2. Si crees que es un error, toca "¿Crees que es un error? Apelar".
                    3. Cuéntanos por qué y toca "Enviar apelación".

                    Solo se puede enviar una apelación por suspensión. Cuando la envías, ves "Recibimos tu apelación". La suspensión solo la levanta nuestro equipo: cuando lo hace, el aviso desaparece y tu asistente vuelve a responder.
                    TXT,
            ],
            [
                'slug' => 'imagen-rechazada',
                'category' => 'soporte',
                'screen' => null,
                'sort_order' => 3,
                'title' => 'Me rechazaron una imagen',
                'keywords' => 'imagen rechazada foto rechazada logo no puedo usar esta imagen contenido adultos moderacion no pudimos revisar',
                'body' => <<<'TXT'
                    Toda imagen que subes (logo, foto de perfil, comprobante, foto de catálogo o imagen de un reporte) se revisa antes de guardarse. Si no pasa, te decimos por qué en el momento.

                    1. Elige otra imagen en formato PNG, JPG o WebP (SVG y PDF no se aceptan).
                    2. Si el mensaje dice "No pudimos revisar la imagen", inténtalo de nuevo en unos minutos: fue un problema nuestro.
                    3. Si el mensaje dice que tu cuenta quedó en revisión, mira el aviso de arriba: ahí puedes apelar.
                    TXT,
            ],
        ];
    }
}
