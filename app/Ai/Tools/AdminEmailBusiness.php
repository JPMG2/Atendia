<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Agents\AskAdmin;
use App\Interfaces\Main\AdminSkillTool;
use App\Mail\PlatformLetter;
use App\Messaging\Channels\Email;
use App\Models\Business;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * An e-mail from the platform team to one business, drafted by the assistant.
 * It ALWAYS pauses the run for her (`Approvable`): she sees the draft, may
 * correct it, and nothing leaves until she approves. The recipients are the
 * business's own team, read from the database, never the model's words.
 */
class AdminEmailBusiness implements AdminSkillTool, Approvable
{
    use InteractsWithApprovals;

    public const int SUBJECT_MAX = 120;

    public const int MESSAGE_MAX = 2000;

    /** Whether the last run of this tool in this request handed the letter to the channel; null when it never ran. */
    public static ?bool $sent = null;

    /** Writing to a business is managing it, not just looking at it. */
    public static function forAdmin(AskAdmin $assistant): ?static
    {
        return $assistant->admin->can('businesses.manage') ? new static : null;
    }

    /**
     * Who would receive it: the team of the business, by the number the search gave.
     *
     * @return array{business: Business, emails: list<string>}|null
     */
    public static function recipients(int $businessId): ?array
    {
        $business = Business::query()->with('users:id,business_id,email')->find($businessId);

        return $business === null ? null : [
            'business' => $business,
            'emails' => $business->users->pluck('email')->filter()->unique()->values()->all(),
        ];
    }

    public function description(): Stringable|string
    {
        return 'Prepara un correo del equipo de la plataforma para UN negocio (su equipo lo recibe). Usala solo cuando ella '
            .'pide escribirle a un negocio. Con el número del negocio que dio la búsqueda, un asunto corto y el mensaje '
            .'completo en el trato indicado, sin saludo ni firma (el correo ya los trae). Ella ve el borrador y decide: '
            .'nada sale sin su aprobación.';
    }

    public function handle(Request $request): Stringable|string
    {
        $subject = trim((string) $request->string('subject'));
        $message = trim((string) $request->string('message'));
        $to = self::recipients($request->integer('business_id'));

        if ($to === null) {
            return 'No existe un negocio con ese número: buscalo primero.';
        }

        if ($subject === '' || $message === '' || mb_strlen($subject) > self::SUBJECT_MAX || mb_strlen($message) > self::MESSAGE_MAX) {
            return 'El correo necesita asunto (hasta '.self::SUBJECT_MAX.' caracteres) y mensaje (hasta '.self::MESSAGE_MAX.').';
        }

        if ($to['emails'] === []) {
            return "Nadie del equipo de {$to['business']->name} tiene correo: no se envió.";
        }

        // What the channel answered, not what was asked: the screen says "Enviado" only when this is true.
        self::$sent = (new Email($to['business'], $to['emails'], PlatformLetter::class, [$subject, $message]))->send();

        return self::$sent
            ? 'Enviado a '.count($to['emails'])." persona(s) del equipo de {$to['business']->name}."
            : 'No se pudo enviar: el canal de correo falló. Decile que lo intente de nuevo más tarde.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'business_id' => $schema->integer()->description('El número del negocio (#) que devolvió la búsqueda')->required(),
            'subject' => $schema->string()->description('Asunto, corto')->required(),
            'message' => $schema->string()->description('El texto del correo, sin saludo ni firma')->required(),
        ];
    }
}
